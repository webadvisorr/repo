<?php
declare(strict_types=1);

/**
 * SADECE YEREL GELISTIRME icin kucuk yonlendirici (php -S ile kullanilir).
 * Canli sunucuda gerek yoktur; .htaccess isi yapar.
 *   php -S 127.0.0.1:8080 -t . dev-router.php
 */
$uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$file = __DIR__ . $uri;

if ($uri !== '/' && is_file($file) && !str_contains($uri, '..')) {
    $ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, ['php'], true)) {
        return false; // statik dosyayi dogrudan sun
    }
    if (str_starts_with($uri, '/assets/')) {
        return false;
    }
}

if (str_starts_with($uri, '/yonetim')) {
    require __DIR__ . '/yonetim/index.php';
    return true;
}
if (str_starts_with($uri, '/install.php')) {
    require __DIR__ . '/install.php';
    return true;
}
require __DIR__ . '/index.php';
return true;
