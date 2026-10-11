<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Kimlik dogrulama: kayit, giris, cikis, sifre sifirlama, rol yetkileri.
 * Sifreler bcrypt/argon2 ile hashlenir; oturumlar yeniden uretilir (fixation korumasi).
 */
final class Auth
{
    private static ?array $user = null;
    private static bool $resolved = false;

    public const SESSION_KEY = '_auth_user_id';
    public const MAX_LOGIN_ATTEMPTS = 5;
    public const LOGIN_LOCK_SECONDS = 900;

    public static function attempt(string $email, string $password, bool $remember = false): array
    {
        $email = mb_strtolower(trim($email));
        $request = new Request();

        if (RateLimiter::tooManyAttempts('login:' . $request->ip(), 20, 900)) {
            Logger::warning('Cok fazla giris denemesi (IP)', ['ip' => $request->ip()]);
            return ['ok' => false, 'error' => 'too_many_requests'];
        }
        if (RateLimiter::tooManyAttempts('login:email:' . $email, self::MAX_LOGIN_ATTEMPTS, self::LOGIN_LOCK_SECONDS)) {
            self::recordAttempt($email, false);
            return ['ok' => false, 'error' => 'account_locked'];
        }

        $user = Database::first('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1', [$email]);
        if ($user === null) {
            // Zamanlama saldirisini engelle: hash hesaplama maliyetini esit tut.
            password_verify($password, '$2y$12$uselesshashvalueuselesshashvalueuselesshashvalueuselesshashvalu');
            RateLimiter::hit('login:email:' . $email, self::LOGIN_LOCK_SECONDS);
            self::recordAttempt($email, false);
            return ['ok' => false, 'error' => 'invalid_credentials'];
        }

        if (($user['status'] ?? 'active') !== 'active') {
            self::recordAttempt($email, false);
            return ['ok' => false, 'error' => 'account_passive'];
        }

        if ($user['locked_until'] !== null && strtotime((string) $user['locked_until']) > time()) {
            self::recordAttempt($email, false);
            return ['ok' => false, 'error' => 'account_locked'];
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            RateLimiter::hit('login:email:' . $email, self::LOGIN_LOCK_SECONDS);
            self::recordAttempt($email, false);
            $failed = (int) $user['failed_logins'] + 1;
            $data = ['failed_logins' => $failed];
            if ($failed >= self::MAX_LOGIN_ATTEMPTS) {
                $data['locked_until'] = date('Y-m-d H:i:s', time() + self::LOGIN_LOCK_SECONDS);
                $data['failed_logins'] = 0;
            }
            Database::update('users', $data, 'id = :id', ['id' => (int) $user['id']]);
            return ['ok' => false, 'error' => $failed >= self::MAX_LOGIN_ATTEMPTS ? 'account_locked' : 'invalid_credentials'];
        }

        // Hash algoritmasi/eski maliyet degistiyse sessizce yeniden hashle.
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            Database::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => (int) $user['id']]);
        }

        RateLimiter::clear('login:email:' . $email);
        self::login((int) $user['id'], $remember);
        self::recordAttempt($email, true);

        Database::update('users', [
            'failed_logins' => 0,
            'locked_until'  => null,
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $request->ip(),
        ], 'id = :id', ['id' => (int) $user['id']]);

        Audit::log('auth.login', 'user', (int) $user['id']);
        return ['ok' => true, 'user' => self::user()];
    }

    public static function login(int $userId, bool $remember = false): void
    {
        Session::regenerate();
        Session::put(self::SESSION_KEY, $userId);
        Csrf::rotate();
        self::$user = null;
        self::$resolved = false;

        if ($remember) {
            $token = Str::random(32);
            Database::update('users', ['remember_token' => hash('sha256', $token)], 'id = :id', ['id' => $userId]);
            $secure = (new Request())->isSecure();
            setcookie('evrako_remember', $userId . ':' . $token, [
                'expires'  => time() + 60 * 60 * 24 * 30,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    public static function logout(): void
    {
        $id = self::id();
        if ($id !== null) {
            Database::update('users', ['remember_token' => null], 'id = :id', ['id' => $id]);
            Audit::log('auth.logout', 'user', $id);
        }
        setcookie('evrako_remember', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true]);
        self::$user = null;
        self::$resolved = false;
        Session::destroy();
    }

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;
        self::$user = null;

        $id = Session::get(self::SESSION_KEY);
        if (is_numeric($id)) {
            self::$user = self::find((int) $id);
        }

        if (self::$user === null) {
            self::$user = self::resolveRememberCookie();
        }
        return self::$user;
    }

    private static function resolveRememberCookie(): ?array
    {
        $cookie = (new Request())->cookie('evrako_remember');
        if ($cookie === null || !str_contains($cookie, ':')) {
            return null;
        }
        [$id, $token] = explode(':', $cookie, 2);
        if (!is_numeric($id) || $token === '') {
            return null;
        }
        $user = self::find((int) $id);
        if ($user === null || $user['remember_token'] === null) {
            return null;
        }
        if (!hash_equals((string) $user['remember_token'], hash('sha256', $token))) {
            return null;
        }
        // Token rotasyonu: calinan cerez tekrar kullanilamaz.
        self::login((int) $user['id'], true);
        return self::find((int) $user['id']);
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1', [$id]);
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user === null ? null : (int) $user['id'];
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        $user = self::user();
        return $user !== null && in_array((string) $user['role'], ['admin', 'editor'], true);
    }

    public static function isSuperAdmin(): bool
    {
        $user = self::user();
        return $user !== null && (string) $user['role'] === 'admin';
    }

    public static function hasRole(string ...$roles): bool
    {
        $user = self::user();
        return $user !== null && in_array((string) $user['role'], $roles, true);
    }

    public static function register(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $userId = Database::insert('users', [
            'full_name'          => $data['full_name'],
            'email'              => mb_strtolower(trim((string) $data['email'])),
            'phone'              => $data['phone'] ?? null,
            'whatsapp'           => $data['whatsapp'] ?? ($data['phone'] ?? null),
            'password_hash'      => password_hash((string) $data['password'], PASSWORD_DEFAULT),
            'role'               => $data['role'] ?? 'customer',
            'status'             => 'active',
            'locale'             => $data['locale'] ?? 'tr',
            'marketing_opt_in'   => !empty($data['marketing_opt_in']) ? 1 : 0,
            'marketing_opt_in_at' => !empty($data['marketing_opt_in']) ? $now : null,
            'created_at'         => $now,
        ]);
        Audit::log('auth.register', 'user', $userId);
        return $userId;
    }

    public static function createToken(int $userId, string $type, int $ttlSeconds): string
    {
        $token = Str::random(32);
        Database::insert('auth_tokens', [
            'user_id'    => $userId,
            'type'       => $type,
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + $ttlSeconds),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return $token;
    }

    public static function consumeToken(string $token, string $type): ?array
    {
        $row = Database::first(
            'SELECT * FROM auth_tokens WHERE token_hash = ? AND type = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1',
            [hash('sha256', $token), $type]
        );
        if ($row === null) {
            return null;
        }
        Database::update('auth_tokens', ['used_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $row['id']]);
        return self::find((int) $row['user_id']);
    }

    public static function changePassword(int $userId, string $newPassword): void
    {
        Database::update('users', [
            'password_hash'     => password_hash($newPassword, PASSWORD_DEFAULT),
            'remember_token'    => null,
            'failed_logins'     => 0,
            'locked_until'      => null,
            'updated_at'        => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $userId]);
        Audit::log('auth.password_changed', 'user', $userId);
    }

    private static function recordAttempt(string $email, bool $success): void
    {
        try {
            $request = new Request();
            Database::insert('login_attempts', [
                'email'      => Str::limit($email, 190, ''),
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
                'success'    => $success ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::warning('Giris denemesi kaydedilemedi: ' . $e->getMessage());
        }
    }
}
