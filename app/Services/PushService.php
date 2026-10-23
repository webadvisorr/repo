<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Settings;

/**
 * Cihaz bildirimleri (Web Push / RFC 8291 + VAPID).
 *
 * - Her tarayıcı/telefon aboneliği `push_subscriptions` tablosunda tutulur.
 * - Bildirimler önce `push_queue` kuyruğuna yazılır; cron veya duyuru gönderimi
 *   sırasında işlenir. Böylece duyuru isteği onlarca cihazı beklemek zorunda kalmaz.
 * - Şifreleme ve imza saf PHP + openssl ile yapılır; harici kütüphane gerekmez.
 * - VAPID anahtarları ilk kullanımda üretilir ve `settings` tablosunda saklanır
 *   (özel anahtar tarayıcıya asla gönderilmez).
 *
 * Önemli: Web Push yalnızca HTTPS (ve localhost) üzerinde çalışır. Özel anahtar
 * üretilemezse (openssl yapılandırması bozuksa) servis sessizce devre dışı kalır;
 * panel bildirimleri çalışmaya devam eder.
 */
final class PushService
{
    private const PUBLIC_SETTING = 'push.vapid_public';
    private const PRIVATE_SETTING = 'push.vapid_private';
    private const MAX_ATTEMPTS = 3;
    private const TTL = 86400;

    private static ?bool $available = null;

    /** Tablolar hazır mı? (migration öncesi güvenli çalışma) */
    public static function available(): bool
    {
        if (self::$available !== null) {
            return self::$available;
        }
        try {
            Database::scalar('SELECT id FROM push_subscriptions LIMIT 1');
            Database::scalar('SELECT id FROM push_queue LIMIT 1');
            self::$available = true;
        } catch (\Throwable) {
            self::$available = false;
        }
        return self::$available;
    }

    public static function enabled(): bool
    {
        return self::available() && (string) Settings::get('push.enabled', '1') !== '0';
    }

    /* ---------------- VAPID anahtarları ---------------- */

    /** Tarayıcıya verilecek açık anahtar (base64url, 65 baytlık P-256 noktası). */
    public static function publicKey(): ?string
    {
        $key = trim((string) Settings::get(self::PUBLIC_SETTING, ''));
        if ($key !== '' && trim((string) Settings::get(self::PRIVATE_SETTING, '')) !== '') {
            return $key;
        }
        return self::generateKeys() ? trim((string) Settings::get(self::PUBLIC_SETTING, '')) : null;
    }

    /** Anahtarlar hazır mı? (panelde durum göstermek için; üretmez) */
    public static function hasKeys(): bool
    {
        return trim((string) Settings::get(self::PUBLIC_SETTING, '')) !== ''
            && trim((string) Settings::get(self::PRIVATE_SETTING, '')) !== '';
    }

    private static function privateKey(): ?string
    {
        $pem = (string) Settings::get(self::PRIVATE_SETTING, '');
        return trim($pem) !== '' ? $pem : null;
    }

    /** İlk kullanımda VAPID anahtar çiftini üretir. */
    public static function generateKeys(): bool
    {
        if (!function_exists('openssl_pkey_new')) {
            Logger::error('Web Push: openssl eklentisi yok, VAPID anahtarı üretilemedi.');
            return false;
        }

        $key = @openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'prime256v1',
        ]);
        if ($key === false) {
            while (openssl_error_string() !== false) {
                // hata kuyruğunu temizle
            }
            Logger::error('Web Push: VAPID anahtarı üretilemedi (openssl yapılandırması).');
            return false;
        }

        $pem = '';
        if (!openssl_pkey_export($key, $pem) || $pem === '') {
            return false;
        }
        $details = openssl_pkey_get_details($key);
        if (!is_array($details) || !isset($details['ec']['x'], $details['ec']['y'])) {
            return false;
        }
        $public = self::base64UrlEncode("\x04" . $details['ec']['x'] . $details['ec']['y']);

        Settings::set(self::PUBLIC_SETTING, $public, 'push');
        Settings::set(self::PRIVATE_SETTING, $pem, 'push');

        return true;
    }

    /* ---------------- Abonelikler ---------------- */

    /**
     * Tarayıcı aboneliğini kaydeder (aynı endpoint varsa günceller).
     *
     * @return array{ok:bool,error?:string,devices?:int}
     */
    public static function subscribe(int $userId, string $endpoint, string $p256dh, string $auth, ?string $userAgent = null): array
    {
        if (!self::available() || $userId <= 0) {
            return ['ok' => false, 'error' => 'Cihaz bildirimleri bu sitede kullanılamıyor.'];
        }
        $endpoint = trim($endpoint);
        if (!self::validEndpoint($endpoint)) {
            return ['ok' => false, 'error' => 'Geçersiz bildirim adresi.'];
        }
        $publicPoint = self::base64UrlDecode($p256dh);
        $authSecret = self::base64UrlDecode($auth);
        if ($publicPoint === null || strlen($publicPoint) !== 65 || $publicPoint[0] !== "\x04") {
            return ['ok' => false, 'error' => 'Geçersiz cihaz anahtarı.'];
        }
        if ($authSecret === null || strlen($authSecret) !== 16) {
            return ['ok' => false, 'error' => 'Geçersiz cihaz doğrulama anahtarı.'];
        }

        $hash = hash('sha256', $endpoint);
        $now = date('Y-m-d H:i:s');
        $data = [
            'user_id'    => $userId,
            'endpoint'   => $endpoint,
            'p256dh'     => $p256dh,
            'auth'       => $auth,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
            'last_error' => null,
        ];

        try {
            $existing = Database::first('SELECT id FROM push_subscriptions WHERE endpoint_hash = ? LIMIT 1', [$hash]);
            if ($existing !== null) {
                Database::update('push_subscriptions', $data, 'id = :id', ['id' => (int) $existing['id']]);
            } else {
                $data['endpoint_hash'] = $hash;
                $data['created_at'] = $now;
                Database::insert('push_subscriptions', $data);
            }
        } catch (\Throwable $e) {
            Logger::error('Web Push aboneliği kaydedilemedi: ' . $e->getMessage(), ['user' => $userId]);
            return ['ok' => false, 'error' => 'Cihaz kaydedilemedi. Lütfen tekrar deneyin.'];
        }

        // Kullanıcı tercihi olarak da işaretle (panel/raporlar için).
        Database::run('UPDATE users SET notify_push = 1 WHERE id = ?', [$userId]);

        return ['ok' => true, 'devices' => self::deviceCount($userId)];
    }

    public static function unsubscribe(int $userId, string $endpoint): bool
    {
        if (!self::available() || $userId <= 0) {
            return false;
        }
        $hash = hash('sha256', trim($endpoint));
        $deleted = Database::delete('push_subscriptions', 'user_id = ? AND endpoint_hash = ?', [$userId, $hash]) > 0;
        if ($deleted && self::deviceCount($userId) === 0) {
            Database::run('UPDATE users SET notify_push = 0 WHERE id = ?', [$userId]);
        }
        return $deleted;
    }

    /** @return array<int,array> */
    public static function subscriptions(int $userId): array
    {
        if (!self::available() || $userId <= 0) {
            return [];
        }
        return Database::select('SELECT * FROM push_subscriptions WHERE user_id = ? ORDER BY id ASC', [$userId]);
    }

    public static function deviceCount(int $userId): int
    {
        if (!self::available() || $userId <= 0) {
            return 0;
        }
        return (int) Database::scalar('SELECT COUNT(*) FROM push_subscriptions WHERE user_id = ?', [$userId]);
    }

    public static function totalDevices(): int
    {
        if (!self::available()) {
            return 0;
        }
        try {
            return (int) Database::scalar('SELECT COUNT(*) FROM push_subscriptions');
        } catch (\Throwable) {
            return 0;
        }
    }

    /* ---------------- Kuyruk ---------------- */

    /**
     * Kullanıcıya cihaz bildirimi kuyruklar. Cihaz yoksa hiçbir şey yapmaz.
     *
     * @param array{title:string,body?:string,url?:?string,icon?:?string,image?:?string,tag?:?string} $payload
     */
    public static function queue(int $userId, array $payload): ?int
    {
        if (!self::enabled() || $userId <= 0) {
            return null;
        }
        $title = trim((string) ($payload['title'] ?? ''));
        if ($title === '' || self::deviceCount($userId) === 0) {
            return null;
        }

        try {
            return Database::insert('push_queue', [
                'user_id'    => $userId,
                'title'      => mb_substr($title, 0, 160),
                'body'       => trim((string) ($payload['body'] ?? '')) ?: null,
                'url'        => self::absoluteUrl($payload['url'] ?? null),
                'icon'       => self::absoluteUrl($payload['icon'] ?? null),
                'image'      => self::absoluteUrl($payload['image'] ?? null),
                'tag'        => mb_substr(trim((string) ($payload['tag'] ?? '')), 0, 80) ?: null,
                'status'     => 'pending',
                'attempts'   => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::warning('Web Push kuyruğa eklenemedi: ' . $e->getMessage(), ['user' => $userId]);
            return null;
        }
    }

    /**
     * Panel bildirimini (InboxService kaydı) cihazlara da yollar.
     * Panelde görünen resim/bağlantı aynen kullanılır.
     */
    public static function queueFromNotification(int $userId, string $title, string $body = '', ?string $link = null, ?string $imagePath = null, string $kind = 'announcement'): ?int
    {
        return self::queue($userId, [
            'title' => $title,
            'body'  => mb_strimwidth($body, 0, 180, '…'),
            'url'   => $link !== null && $link !== '' ? $link : '/panel/bildirimler',
            'icon'  => (string) Settings::get('brand.favicon', '') !== ''
                ? media_url((string) Settings::get('brand.favicon', ''))
                : url('/assets/brand/favicon.svg'),
            'image' => $imagePath !== null && $imagePath !== '' ? media_url($imagePath) : null,
            'tag'   => 'evrako-' . $kind,
        ]);
    }

    public static function pendingCount(): int
    {
        if (!self::available()) {
            return 0;
        }
        try {
            return (int) Database::scalar("SELECT COUNT(*) FROM push_queue WHERE status = 'pending'");
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Kuyruğu işler. Her bildirim kullanıcının tüm cihazlarına gönderilir.
     *
     * @return array{attempted:int,sent:int,failed:int,skipped:int}
     */
    public static function process(int $limit = 40): array
    {
        $result = ['attempted' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        if (!self::enabled()) {
            return $result;
        }
        $limit = max(1, min(200, $limit));

        $rows = Database::select(
            "SELECT * FROM push_queue WHERE status = 'pending' ORDER BY id ASC LIMIT " . $limit
        );

        foreach ($rows as $row) {
            $result['attempted']++;
            $userId = (int) $row['user_id'];
            $subscriptions = self::subscriptions($userId);

            if ($subscriptions === []) {
                Database::update('push_queue', [
                    'status' => 'skipped',
                    'error'  => 'Kayıtlı cihaz yok',
                    'attempts' => min(self::MAX_ATTEMPTS, (int) $row['attempts'] + 1),
                ], 'id = :id', ['id' => (int) $row['id']]);
                $result['skipped']++;
                continue;
            }

            $payload = [
                'title' => (string) $row['title'],
                'body'  => (string) ($row['body'] ?? ''),
                'url'   => (string) ($row['url'] ?? url('/panel/bildirimler')),
                'icon'  => (string) ($row['icon'] ?? ''),
                'image' => (string) ($row['image'] ?? ''),
                'tag'   => (string) ($row['tag'] ?? ''),
            ];

            $delivered = 0;
            $error = null;
            foreach ($subscriptions as $subscription) {
                $send = self::send($subscription, $payload);
                if ($send['ok']) {
                    $delivered++;
                    Database::update('push_subscriptions', [
                        'last_used_at' => date('Y-m-d H:i:s'),
                        'last_error'   => null,
                    ], 'id = :id', ['id' => (int) $subscription['id']]);
                    continue;
                }

                $error ??= $send['error'];
                if ($send['gone']) {
                    Database::delete('push_subscriptions', 'id = ?', [(int) $subscription['id']]);
                } else {
                    Database::update('push_subscriptions', [
                        'last_error' => mb_substr((string) $send['error'], 0, 255),
                    ], 'id = :id', ['id' => (int) $subscription['id']]);
                }
            }

            if ($delivered > 0) {
                Database::update('push_queue', [
                    'status'   => 'sent',
                    'sent_at'  => date('Y-m-d H:i:s'),
                    'attempts' => (int) $row['attempts'] + 1,
                    'error'    => null,
                ], 'id = :id', ['id' => (int) $row['id']]);
                $result['sent']++;
            } else {
                Database::update('push_queue', [
                    'status'   => 'failed',
                    'attempts' => (int) $row['attempts'] + 1,
                    'error'    => mb_substr((string) ($error ?? 'Gönderim başarısız'), 0, 255),
                ], 'id = :id', ['id' => (int) $row['id']]);
                $result['failed']++;
            }
        }

        if ($result['attempted'] > 0) {
            Logger::info('Web Push kuyruğu işlendi', $result);
        }
        return $result;
    }

    /* ---------------- Gönderim ---------------- */

    /**
     * @param array $subscription push_subscriptions satırı
     * @param array{title:string,body:string,url:string,icon:string,image:string,tag:string} $payload
     * @return array{ok:bool,status:int,error:string,gone:bool}
     */
    public static function send(array $subscription, array $payload): array
    {
        $fail = static fn (string $message, int $status = 0, bool $gone = false): array => [
            'ok' => false, 'status' => $status, 'error' => $message, 'gone' => $gone,
        ];

        $privatePem = self::privateKey();
        $publicKey = trim((string) Settings::get(self::PUBLIC_SETTING, ''));
        if ($privatePem === null || $publicKey === '') {
            return $fail('VAPID anahtarı hazır değil');
        }

        $body = self::encrypt($subscription, $payload);
        if ($body === null) {
            return $fail('Bildirim şifrelenemedi');
        }
        $jwt = self::vapidToken((string) $subscription['endpoint'], $privatePem);
        if ($jwt === null) {
            return $fail('VAPID imzası oluşturulamadı');
        }

        $ch = curl_init((string) $subscription['endpoint']);
        if ($ch === false) {
            return $fail('Bağlantı kurulamadı');
        }
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                'Content-Length: ' . strlen($body),
                'TTL: ' . self::TTL,
                'Urgency: normal',
                'Authorization: vapid t=' . $jwt . ', k=' . $publicKey,
            ],
            CURLOPT_POSTFIELDS     => $body,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($status >= 200 && $status < 300) {
            return ['ok' => true, 'status' => $status, 'error' => '', 'gone' => false];
        }
        if ($status === 404 || $status === 410) {
            return $fail('Abonelik artık geçerli değil', $status, true);
        }
        if ($status === 0) {
            return $fail($error !== '' ? $error : 'Push servisine ulaşılamadı');
        }
        $detail = is_string($raw) ? mb_strimwidth(trim(strip_tags($raw)), 0, 120, '…') : '';
        return $fail('Push servisi HTTP ' . $status . ($detail !== '' ? ': ' . $detail : ''), $status);
    }

    /**
     * RFC 8291 aes128gcm içerik şifrelemesi.
     *
     * @return string|null şifreli gövde (başlık + ciphertext + tag)
     */
    private static function encrypt(array $subscription, array $payload): ?string
    {
        $uaPublic = self::base64UrlDecode((string) $subscription['p256dh']);
        $authSecret = self::base64UrlDecode((string) $subscription['auth']);
        if ($uaPublic === null || strlen($uaPublic) !== 65 || $authSecret === null || strlen($authSecret) !== 16) {
            return null;
        }

        $ephemeral = @openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'prime256v1',
        ]);
        if ($ephemeral === false) {
            while (openssl_error_string() !== false) {
                // kuyruğu temizle
            }
            return null;
        }
        $details = openssl_pkey_get_details($ephemeral);
        if (!is_array($details) || !isset($details['ec']['x'], $details['ec']['y'])) {
            return null;
        }
        $asPublic = "\x04" . $details['ec']['x'] . $details['ec']['y'];

        $uaKey = openssl_pkey_get_public(self::publicKeyPem($uaPublic));
        if ($uaKey === false) {
            return null;
        }
        $shared = openssl_pkey_derive($uaKey, $ephemeral, 32);
        if (!is_string($shared) || strlen($shared) !== 32) {
            return null;
        }

        $prk = hash_hkdf('sha256', $shared, 32, "WebPush: info\0" . $uaPublic . $asPublic, $authSecret);
        $salt = random_bytes(16);
        $cek = hash_hkdf('sha256', $prk, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $prk, 12, "Content-Encoding: nonce\0", $salt);

        $plaintext = (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\x02";
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($ciphertext === false) {
            return null;
        }

        return $salt . pack('N', 4096) . chr(65) . $asPublic . $ciphertext . $tag;
    }

    /** VAPID JWT (ES256). */
    private static function vapidToken(string $endpoint, string $privatePem): ?string
    {
        $parts = parse_url($endpoint);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        $audience = $parts['scheme'] . '://' . $parts['host']
            . (isset($parts['port']) ? ':' . (int) $parts['port'] : '');

        $email = trim((string) Settings::get('brand.email', ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $host = parse_url((string) \App\Core\Config::get('app.url', ''), PHP_URL_HOST);
            $email = 'no-reply@' . ($host !== null && $host !== '' ? $host : 'localhost');
        }

        $header = self::base64UrlEncode((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::base64UrlEncode((string) json_encode([
            'aud' => $audience,
            'exp' => time() + 12 * 3600,
            'sub' => 'mailto:' . $email,
        ]));
        $input = $header . '.' . $claims;

        $key = openssl_pkey_get_private($privatePem);
        if ($key === false) {
            return null;
        }
        $der = '';
        if (!openssl_sign($input, $der, $key, OPENSSL_ALGO_SHA256)) {
            return null;
        }
        $jose = self::derToJose($der);
        if ($jose === null) {
            return null;
        }
        return $input . '.' . self::base64UrlEncode($jose);
    }

    /* ---------------- Yardımcılar ---------------- */

    /** Ham P-256 noktasından SPKI PEM üretir (openssl_pkey_get_public için). */
    private static function publicKeyPem(string $point): string
    {
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $point;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /** ECDSA DER imzasını JOSE (r||s, 64 bayt) biçimine çevirir. */
    private static function derToJose(string $der): ?string
    {
        $offset = 0;
        $length = strlen($der);
        if ($length < 8 || ord($der[0]) !== 0x30) {
            return null;
        }
        $offset = 1;
        $seqLength = ord($der[$offset++]);
        if (($seqLength & 0x80) !== 0) {
            $bytes = $seqLength & 0x7f;
            if ($bytes < 1 || $bytes > 2 || $offset + $bytes > $length) {
                return null;
            }
            $seqLength = 0;
            for ($i = 0; $i < $bytes; $i++) {
                $seqLength = ($seqLength << 8) | ord($der[$offset++]);
            }
        }
        if ($offset + $seqLength > $length) {
            return null;
        }

        $readInteger = static function () use ($der, &$offset, $length): ?string {
            if ($offset + 2 > $length || ord($der[$offset]) !== 0x02) {
                return null;
            }
            $offset++;
            $len = ord($der[$offset++]);
            if ($len === 0 || $offset + $len > $length) {
                return null;
            }
            $value = substr($der, $offset, $len);
            $offset += $len;
            return ltrim($value, "\x00");
        };

        $r = $readInteger();
        $s = $readInteger();
        if ($r === null || $s === null) {
            return null;
        }
        if (strlen($r) > 32 || strlen($s) > 32) {
            return null;
        }
        return str_pad($r, 32, "\x00", STR_PAD_LEFT) . str_pad($s, 32, "\x00", STR_PAD_LEFT);
    }

    public static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        return $decoded === false ? null : $decoded;
    }

    /** Push uç noktası doğrulaması: https zorunlu, özel ağ adresleri reddedilir. */
    private static function validEndpoint(string $endpoint): bool
    {
        if ($endpoint === '' || strlen($endpoint) > 1000) {
            return false;
        }
        $parts = parse_url($endpoint);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return false;
        }
        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower(trim((string) $parts['host'], '[]'));

        $localHosts = ['localhost', '127.0.0.1', '::1'];
        $isLocal = in_array($host, $localHosts, true) || str_ends_with($host, '.localhost');
        if ($scheme !== 'https' && !($scheme === 'http' && $isLocal)) {
            return false;
        }
        if ($isLocal) {
            return true;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $public = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if ($public === false) {
                return false;
            }
        }
        return true;
    }

    private static function absoluteUrl(mixed $value): ?string
    {
        $url = trim((string) ($value ?? ''));
        if ($url === '') {
            return null;
        }
        if (preg_match('~^https?://~i', $url) === 1) {
            return mb_substr($url, 0, 500);
        }
        return mb_substr(url($url), 0, 500);
    }
}
