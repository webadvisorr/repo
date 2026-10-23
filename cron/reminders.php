<?php
declare(strict_types=1);

/**
 * evrako — günlük hatırlatma motoru (cron).
 *
 * cPanel → Cron Jobs (günlük, örn. her gün 08:00):
 *   /usr/local/bin/php /home/KULLANICI/evrako/cron/reminders.php
 *
 * Web cron alternatifi (cron/.htaccess web erişimini kapatır, bu yüzden
 * uygulama rotası kullanılır):
 *   https://siteadresiniz/cron-hatirlatma?token=GIZLI_ANAHTAR
 *   (token: config/config.php → security.cron_token, panelde Sistem sayfasında görünür)
 *
 * Yaptığı işler: süresi geçen abonelikleri kapatır, süresi geçen ödeme taleplerini
 * kapatır ve 7/3/1/0 gün hatırlatmalarını kuyruğa alıp gönderir.
 */

$isCli = PHP_SAPI === 'cli';

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Translator;
use App\Services\CronService;

$started = microtime(true);

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
}

if (!Config::installed()) {
    if ($isCli) {
        fwrite(STDERR, "evrako kurulu değil (config/config.php yok).\n");
        exit(1);
    }
    http_response_code(503);
    echo "Kurulum tamamlanmamış.\n";
    exit(1);
}

if (!$isCli) {
    $expected = (string) Config::get('security.cron_token', '');
    $provided = (string) (new Request())->input('token', '');
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
        Logger::warning('Web cron: geçersiz token', ['ip' => (new Request())->ip()]);
        http_response_code(403);
        echo "Geçersiz veya eksik cron token.\n";
        exit(1);
    }
}

Translator::setLocale('tr');

try {
    $result = CronService::runDaily();
    $summary = CronService::summary($result);
    $errors = (array) ($result['reminders']['errors'] ?? []);
    $line = '[' . date('Y-m-d H:i:s') . '] evrako cron: ' . $summary
        . ' · süre: ' . number_format((float) $result['microtime'], 3) . ' sn';

    if ($isCli) {
        echo $line . PHP_EOL;
    } else {
        echo $line . "\n\n" . 'Cron başarıyla çalıştı.' . "\n";
    }

    // Hata varsa cron sisteminin fark etmesi için sıfırdan farklı çıkış kodu.
    exit($errors === [] ? 0 : 2);
} catch (\Throwable $e) {
    Logger::error('Cron hatası: ' . $e->getMessage());
    if ($isCli) {
        fwrite(STDERR, 'Cron hatası: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    echo 'Cron hatası: ' . $e->getMessage() . "\n";
    exit(1);
}
