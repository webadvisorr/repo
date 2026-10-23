<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = (new Request())->isSecure();
        $name = (string) Config::get('app.session_name', 'evrako_session');
        if ($name !== '') {
            session_name($name);
        }
        // session_set_cookie_params yalnızca tanımlı anahtarları kabul eder;
        // oturum adı ve strict mode ayrı API'lerle ayarlanır.
        $params = [
            'lifetime' => 60 * 60 * 8,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params($params);
        } else {
            session_set_cookie_params($params['lifetime'], $params['path'] . '; samesite=' . $params['samesite'], $params['domain'], $params['secure'], $params['httponly']);
        }
        @ini_set('session.use_only_cookies', '1');
        @ini_set('session.use_strict_mode', '1');
        if ($secure) {
            @ini_set('session.cookie_secure', '1');
        }
        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true); // oturum sabitleme (fixation) korumasi
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
    }
}
