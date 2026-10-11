<?php
declare(strict_types=1);

/**
 * Uygulama baslatma dosyasi.
 *  - Composer yok: kendi otomatik yukleyicimiz kullanilir.
 *  - config/config.php kurulumda uretilir (install.php).
 */

if (defined('EVRAKO_BOOTED')) {
    return;
}
define('EVRAKO_BOOTED', true);

define('BASE_PATH', dirname(__DIR__));
define('EVRAKO_START', microtime(true));

// ---- Hata yonetimi -------------------------------------------------------
$debug = false;
$configFile = BASE_PATH . '/config/config.php';
require BASE_PATH . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

if (is_file($configFile)) {
    $items = require $configFile;
    if (is_array($items)) {
        \App\Core\Config::load($items);
        $debug = (bool) \App\Core\Config::get('app.debug', false);
    }
}

error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
$logDir = BASE_PATH . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
ini_set('error_log', $logDir . '/php-error.log');

set_exception_handler(static function (\Throwable $e) use ($debug): void {
    \App\Core\Logger::error('Yakalanmamis hata: ' . $e->getMessage(), [
        'file'  => $e->getFile() . ':' . $e->getLine(),
        'trace' => $debug ? $e->getTraceAsString() : null,
    ]);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    if ($debug) {
        echo '<pre style="padding:20px;background:#0b1f3a;color:#ff8f8f;font:13px/1.5 monospace">'
            . htmlspecialchars($e->getMessage(), ENT_QUOTES) . "\n\n"
            . htmlspecialchars($e->getFile() . ':' . $e->getLine(), ENT_QUOTES) . "\n\n"
            . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES) . '</pre>';
    } else {
        echo '<!doctype html><meta charset="utf-8"><title>Hata</title>'
            . '<div style="font:16px/1.6 system-ui;padding:48px;text-align:center">'
            . '<h1>Beklenmeyen bir hata olustu</h1>'
            . '<p>Islem kaydedildi. Lutfen daha sonra tekrar deneyin.</p></div>';
    }
});

// ---- Zaman dilimi -------------------------------------------------------
date_default_timezone_set((string) \App\Core\Config::get('app.timezone', 'Europe/Nicosia'));

require BASE_PATH . '/app/Core/helpers.php';
