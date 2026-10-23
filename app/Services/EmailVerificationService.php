<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Mailer;
use App\Core\Settings;
use App\Core\Translator;

/**
 * 6 haneli e-posta doğrulama kodu.
 *
 * Kod yalnızca hash'lenmiş olarak saklanır, 2 dakika geçerlidir ve en fazla
 * 5 hatalı denemeden sonra kullanılamaz. Yeniden gönderme 60 saniye ile
 * sınırlandırılır; her yeni kod öncekini geçersiz kılar.
 */
final class EmailVerificationService
{
    public const CODE_TTL = 120;
    public const MAX_ATTEMPTS = 5;
    public const RESEND_SECONDS = 60;

    /** @return array{ok:bool,error?:string,wait?:int,expires_in?:int} */
    public static function issue(array $user): array
    {
        $userId = (int) ($user['id'] ?? 0);
        $email = trim((string) ($user['email'] ?? ''));
        if ($userId <= 0 || $email === '') {
            return ['ok' => false, 'error' => 'user'];
        }

        $now = time();
        $existing = Database::first('SELECT * FROM email_verification_codes WHERE user_id = ? LIMIT 1', [$userId]);
        if ($existing !== null) {
            $wait = self::RESEND_SECONDS - ($now - (int) strtotime((string) $existing['last_sent_at']));
            if ($wait > 0) {
                return ['ok' => false, 'error' => 'cooldown', 'wait' => $wait];
            }
        }

        $code = (string) random_int(100000, 999999);
        $hash = password_hash($code, PASSWORD_DEFAULT);
        $stamp = date('Y-m-d H:i:s', $now);
        $expires = date('Y-m-d H:i:s', $now + self::CODE_TTL);

        if ($existing === null) {
            Database::insert('email_verification_codes', [
                'user_id'      => $userId,
                'code_hash'    => $hash,
                'expires_at'   => $expires,
                'attempts'     => 0,
                'last_sent_at' => $stamp,
                'created_at'   => $stamp,
            ]);
        } else {
            Database::update('email_verification_codes', [
                'code_hash'    => $hash,
                'expires_at'   => $expires,
                'attempts'     => 0,
                'last_sent_at' => $stamp,
                'created_at'   => $stamp,
            ], 'id = :id', ['id' => (int) $existing['id']]);
        }

        $locale = in_array((string) ($user['locale'] ?? ''), Translator::SUPPORTED, true)
            ? (string) $user['locale']
            : 'tr';
        $rendered = MailTemplateService::render('verification', $locale, self::templateVars($user, $code, $locale));
        if (!$rendered['ok']) {
            Logger::error('Doğrulama e-postası şablonu işlenemedi', ['user_id' => $userId]);
            return ['ok' => false, 'error' => 'template'];
        }

        $mailer = new Mailer();
        $result = $mailer->send($email, $rendered['subject'], $rendered['html'], $rendered['text']);
        if (!$result['ok']) {
            Logger::warning('Doğrulama e-postası gönderilemedi', [
                'user_id' => $userId,
                'error'   => (string) ($result['error'] ?? ''),
            ]);
            return ['ok' => false, 'error' => 'smtp'];
        }

        Audit::log('auth.verification_sent', 'user', $userId, ['email' => $email]);
        return ['ok' => true, 'expires_in' => self::CODE_TTL];
    }

    /** @return array{ok:bool,error?:string,user?:array} */
    public static function verify(int $userId, string $code): array
    {
        $code = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($code) !== 6) {
            return ['ok' => false, 'error' => 'invalid'];
        }

        $row = Database::first('SELECT * FROM email_verification_codes WHERE user_id = ? LIMIT 1', [$userId]);
        if ($row === null) {
            return ['ok' => false, 'error' => 'invalid'];
        }
        if ((int) strtotime((string) $row['expires_at']) <= time()) {
            Database::delete('email_verification_codes', 'user_id = ?', [$userId]);
            return ['ok' => false, 'error' => 'expired'];
        }
        if ((int) $row['attempts'] >= self::MAX_ATTEMPTS) {
            return ['ok' => false, 'error' => 'too_many'];
        }
        if (!password_verify($code, (string) $row['code_hash'])) {
            Database::run(
                'UPDATE email_verification_codes SET attempts = attempts + 1 WHERE id = ?',
                [(int) $row['id']]
            );
            return ['ok' => false, 'error' => 'invalid'];
        }

        $stamp = date('Y-m-d H:i:s');
        Database::update('users', [
            'email_verified_at' => $stamp,
            'updated_at'        => $stamp,
        ], 'id = :id', ['id' => $userId]);
        Database::delete('email_verification_codes', 'user_id = ?', [$userId]);
        // Eski bağlantı tabanlı doğrulama tokenları varsa geçersiz kıl.
        Database::run("DELETE FROM auth_tokens WHERE user_id = ? AND type = 'email_verify'", [$userId]);
        Audit::log('auth.email_verified', 'user', $userId);

        return ['ok' => true, 'user' => Database::first('SELECT * FROM users WHERE id = ? LIMIT 1', [$userId])];
    }

    /** @return array{exists:bool,remaining:int,resend_in:int,attempts_left:int} */
    public static function status(int $userId): array
    {
        $row = Database::first('SELECT * FROM email_verification_codes WHERE user_id = ? LIMIT 1', [$userId]);
        if ($row === null) {
            return ['exists' => false, 'remaining' => 0, 'resend_in' => 0, 'attempts_left' => self::MAX_ATTEMPTS];
        }
        $now = time();
        return [
            'exists'       => true,
            'remaining'    => max(0, (int) strtotime((string) $row['expires_at']) - $now),
            'resend_in'    => max(0, self::RESEND_SECONDS - ($now - (int) strtotime((string) $row['last_sent_at']))),
            'attempts_left' => max(0, self::MAX_ATTEMPTS - (int) $row['attempts']),
        ];
    }

    /** @return array<string,string> */
    private static function templateVars(array $user, string $code, string $locale): array
    {
        $brand = (string) Settings::get('brand.title', 'Evrako');
        $company = (string) Settings::get('brand.company', $brand);
        $address = trim((string) Settings::get('brand.address', '') . ' ' . (string) Settings::get('brand.city', '') . ' ' . (string) Settings::get('brand.country', ''));

        return [
            'ad'               => (string) ($user['full_name'] ?? ''),
            'eposta'           => (string) ($user['email'] ?? ''),
            'kod'              => $code,
            'gecerlilik'       => self::validityLabel($locale),
            'dogrulama_link'   => url('/dogrula'),
            'marka_adi'        => $brand,
            'logo_url'         => MailTemplateService::logoUrl(),
            'site_url'         => url('/'),
            'sirket'           => $company,
            'adres'            => $address,
            'yil'              => date('Y'),
        ];
    }

    private static function validityLabel(string $locale): string
    {
        return match ($locale) {
            'en' => '2 minutes',
            'ru' => '2 минуты',
            'ar' => 'دقيقتين',
            'fa' => '۲ دقیقه',
            'el' => '2 λεπτά',
            default => '2 dakika',
        };
    }
}
