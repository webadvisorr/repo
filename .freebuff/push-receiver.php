<?php
declare(strict_types=1);

/**
 * Yalnızca test: Web Push isteğini yakalar ve diske yazar.
 *  - /push  → 201 Created
 *  - /gone  → 410 Gone (abonelik temizliği testi)
 *
 * Çalıştırma: PUSH_CAPTURE_DIR=/tmp/push_capture php -S 127.0.0.1:3111 push-receiver.php
 */

$dir = (string) (getenv('PUSH_CAPTURE_DIR') ?: '/tmp/push_capture');
@mkdir($dir, 0777, true);

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$body = (string) file_get_contents('php://input');

$headers = [];
foreach ($_SERVER as $key => $value) {
    if (str_starts_with((string) $key, 'HTTP_')) {
        $name = str_replace('_', '-', substr((string) $key, 5));
        $headers[$name] = (string) $value;
    }
}
$headers['Content-Type'] = (string) ($_SERVER['CONTENT_TYPE'] ?? '');

if (str_contains($path, 'gone')) {
    http_response_code(410);
    echo 'gone';
    return;
}

file_put_contents($dir . '/last.json', json_encode([
    'path'    => $path,
    'body'    => base64_encode($body),
    'headers' => $headers,
    'at'      => date('c'),
], JSON_UNESCAPED_SLASHES));

http_response_code(201);
echo 'created';
