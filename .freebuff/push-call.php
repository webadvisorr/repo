<?php
declare(strict_types=1);

/**
 * Web Push uçtan uca doğrulama yardımcısı (yalnızca yerel test).
 *
 * Abonelik kaydı → kuyruk → gönderim → alıcı sunucunun yakaladığı gövdenin
 * RFC 8291 ile çözülmesi ve VAPID JWT imzasının doğrulanması.
 *
 * Kullanım: php .freebuff/push-call.php run <capture-dizini> <endpoint>
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;
use App\Core\Settings;
use App\Services\PushService;

$command = $argv[1] ?? 'run';
$captureDir = rtrim($argv[2] ?? '/tmp/push_capture', '/');
$endpoint = $argv[3] ?? 'http://127.0.0.1:3111/push';

$pass = 0;
$fail = 0;
function ok(string $label): void
{
    global $pass;
    $pass++;
    echo "  ✓ $label\n";
}
function bad(string $label, string $detail = ''): void
{
    global $fail;
    $fail++;
    echo "  ✗ $label" . ($detail !== '' ? " — $detail" : '') . "\n";
}
function check(bool $condition, string $label, string $detail = ''): void
{
    $condition ? ok($label) : bad($label, $detail);
}
function b64u(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}
function pointPem(string $point): string
{
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $point;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}
function joseToDer(string $sig): string
{
    $encode = static function (string $part): string {
        $part = ltrim($part, "\x00");
        if ($part === '' || ord($part[0]) > 0x7f) {
            $part = "\x00" . $part;
        }
        return "\x02" . chr(strlen($part)) . $part;
    };
    $seq = $encode(substr($sig, 0, 32)) . $encode(substr($sig, 32, 32));
    return "\x30" . chr(strlen($seq)) . $seq;
}

if ($command === 'run') {
    echo "== Web Push: abonelik → kuyruk → şifreli gönderim ==\n";

    check(PushService::available(), 'push tabloları hazır');
    check(PushService::hasKeys() || PushService::generateKeys(), 'VAPID anahtarları hazır');
    $publicKey = (string) Settings::get('push.vapid_public', '');
    check(strlen((string) base64_decode(strtr($publicKey, '-_', '+/'))) === 65, 'VAPID açık anahtarı 65 baytlık P-256 noktası');

    // Geçici kullanıcı
    $email = 'push-test-' . bin2hex(random_bytes(4)) . '@example.test';
    $userId = Database::insert('users', [
        'full_name'          => 'Push Test',
        'email'              => $email,
        'password_hash'      => password_hash('PushTest123!', PASSWORD_DEFAULT),
        'role'               => 'customer',
        'status'             => 'active',
        'locale'             => 'tr',
        'email_verified_at'  => date('Y-m-d H:i:s'),
        'created_at'         => date('Y-m-d H:i:s'),
        'updated_at'         => date('Y-m-d H:i:s'),
    ]);
    check($userId > 0, 'geçici kullanıcı oluşturuldu', (string) $userId);

    // Tarayıcı aboneliği anahtarları
    $uaKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    $uaDetails = openssl_pkey_get_details($uaKey);
    $uaPublic = "\x04" . $uaDetails['ec']['x'] . $uaDetails['ec']['y'];
    $authSecret = random_bytes(16);
    $p256dh = b64u($uaPublic);
    $auth = b64u($authSecret);

    $subscribe = PushService::subscribe($userId, $endpoint, $p256dh, $auth, 'push-test/1.0');
    check(($subscribe['ok'] ?? false) === true, 'abonelik kaydedildi', (string) ($subscribe['error'] ?? ''));
    check(PushService::deviceCount($userId) === 1, 'cihaz sayısı 1');

    // Aynı endpoint tekrar kaydedilirse çoğaltmamalı
    PushService::subscribe($userId, $endpoint, $p256dh, $auth, 'push-test/1.0');
    check(PushService::deviceCount($userId) === 1, 'aynı cihaz ikinci kez eklenmiyor');

    $invalid = PushService::subscribe($userId, 'http://evil.example.test/push', $p256dh, $auth);
    check(($invalid['ok'] ?? true) === false, 'http (uzak) uç noktası reddedildi');

    $badKey = PushService::subscribe($userId, $endpoint . '?x=1', 'not-a-key', $auth);
    check(($badKey['ok'] ?? true) === false, 'geçersiz p256dh reddedildi');

    @unlink($captureDir . '/last.json');
    $queued = PushService::queue($userId, [
        'title' => 'Test bildirimi',
        'body'  => 'Kuyruktan gelen deneme',
        'url'   => '/panel/bildirimler',
        'image' => null,
        'tag'   => 'evrako-test',
    ]);
    check($queued !== null, 'kuyruk kaydı oluştu', (string) $queued);

    // Inbox entegrasyonu: panel bildirimi de cihaza kuyruklanmalı
    $before = PushService::pendingCount();
    $notificationId = \App\Services\InboxService::create([
        'user_id' => $userId,
        'title'   => 'Panelden bildirim',
        'body'    => 'Zil kaydı cihaza da düşer',
        'link_url' => '/panel/bildirimler',
        'dedupe_key' => 'push-test:' . $email,
    ]);
    check($notificationId !== null, 'panel bildirimi yazıldı');
    check(PushService::pendingCount() === $before + 1, 'panel bildirimi push kuyruğuna eklendi');

    $result = PushService::process(10);
    check($result['sent'] >= 1, 'kuyruk gönderildi', json_encode($result, JSON_UNESCAPED_UNICODE));
    check($result['failed'] === 0, 'gönderim hatası yok', json_encode($result, JSON_UNESCAPED_UNICODE));

    $captureFile = $captureDir . '/last.json';
    check(is_file($captureFile), 'alıcı sunucu isteği yakaladı');
    $capture = is_file($captureFile) ? json_decode((string) file_get_contents($captureFile), true) : null;
    if (is_array($capture)) {
        $headers = array_change_key_case((array) ($capture['headers'] ?? []), CASE_LOWER);
        check(($headers['content-encoding'] ?? '') === 'aes128gcm', 'Content-Encoding: aes128gcm');
        check((int) ($headers['ttl'] ?? 0) > 0, 'TTL başlığı var');
        $authorization = (string) ($headers['authorization'] ?? '');
        check(str_starts_with($authorization, 'vapid t='), 'VAPID Authorization başlığı');

        // VAPID JWT doğrulama
        if (preg_match('/^vapid t=([^,\s]+), k=([A-Za-z0-9_\-]+)$/', $authorization, $m) === 1) {
            [$jwt, $jwtKey] = [$m[1], $m[2]];
            $parts = explode('.', $jwt);
            $signed = $parts[0] . '.' . $parts[1];
            $signature = (string) base64_decode(strtr($parts[2] ?? '', '-_', '+/'));
            $verified = openssl_verify($signed, joseToDer($signature), pointPem((string) base64_decode(strtr($jwtKey, '-_', '+/'))), OPENSSL_ALGO_SHA256);
            check($verified === 1, 'VAPID ES256 imzası geçerli');
            $claims = json_decode((string) base64_decode(strtr($parts[1] ?? '', '-_', '+/')), true);
            $expectedAud = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST) . (parse_url($endpoint, PHP_URL_PORT) ? ':' . parse_url($endpoint, PHP_URL_PORT) : '');
            check(($claims['aud'] ?? '') === $expectedAud, 'JWT aud doğru', (string) ($claims['aud'] ?? ''));
            check((int) ($claims['exp'] ?? 0) > time(), 'JWT süresi geçerli');
            check(str_starts_with((string) ($claims['sub'] ?? ''), 'mailto:'), 'JWT sub mailto');
        } else {
            bad('VAPID Authorization başlığı ayrıştırılamadı');
        }

        // RFC 8291 çözme
        $record = (string) base64_decode((string) ($capture['body'] ?? ''));
        $salt = substr($record, 0, 16);
        $idLength = ord($record[20]);
        $asPublic = substr($record, 21, $idLength);
        $payload = substr($record, 21 + $idLength);
        $tag = substr($payload, -16);
        $ciphertext = substr($payload, 0, -16);

        $shared = openssl_pkey_derive(openssl_pkey_get_public(pointPem($asPublic)), $uaKey, 32);
        check(is_string($shared) && strlen($shared) === 32, 'ECDH ortak sırrı üretildi');
        $prk = hash_hkdf('sha256', (string) $shared, 32, "WebPush: info\0" . $uaPublic . $asPublic, $authSecret);
        $cek = hash_hkdf('sha256', $prk, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $prk, 12, "Content-Encoding: nonce\0", $salt);
        $plain = openssl_decrypt($ciphertext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        check(is_string($plain) && str_ends_with($plain, "\x02"), 'şifreli gövde çözüldü');
        $data = is_string($plain) ? json_decode(substr($plain, 0, -1), true) : null;
        check(is_array($data) && ($data['title'] ?? '') !== '', 'bildirim başlığı okundu');
        check(($data['body'] ?? '') !== '', 'bildirim metni okundu');
        check((string) ($data['url'] ?? '') !== '', 'bildirim bağlantısı okundu');
    }

    // 410 Gone: abonelik temizlenmeli
    $goneEndpoint = preg_replace('~/push$~', '/gone', $endpoint);
    PushService::subscribe($userId, $goneEndpoint, $p256dh, $auth, 'push-test/1.0');
    check(PushService::deviceCount($userId) === 2, 'ikinci cihaz eklendi');
    PushService::queue($userId, ['title' => 'Silinmiş abonelik', 'body' => '410 testi']);
    $goneResult = PushService::process(5);
    check(PushService::deviceCount($userId) === 1, '410 dönen abonelik silindi', json_encode($goneResult, JSON_UNESCAPED_UNICODE));

    // Temizlik
    Database::delete('users', 'id = ?', [$userId]);
    check(PushService::deviceCount($userId) === 0, 'temizlik: kullanıcı cihazları silindi');

    echo "\nSONUÇ: $pass geçti, $fail kaldı\n";
    exit($fail === 0 ? 0 : 1);
}

echo "Bilinmeyen komut: $command\n";
exit(2);
