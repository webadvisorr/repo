<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Config;
use App\Core\Logger;
use App\Core\Translator;
use App\Services\CronService;

/**
 * Token'lı web cron ucu: /cron-hatirlatma?token=...
 *
 * Paylaşımlı hosting'de cPanel cron tanımlanamıyorsa (veya harici bir izleme servisi
 * düzenli çağırmak istiyorsa) kullanılır. Token `config/config.php` içindeki
 * `security.cron_token` değeridir ve panelde (Sistem) gösterilir.
 */
final class CronController extends Controller
{
    protected string $layout = 'layouts/site';

    public function run(): string
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store');

        $expected = (string) Config::get('security.cron_token', '');
        $provided = (string) $this->request->input('token', '');

        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            Logger::warning('Web cron: geçersiz token', ['ip' => $this->request->ip()]);
            http_response_code(403);
            return "Geçersiz veya eksik cron token.\n";
        }

        Translator::setLocale('tr');

        try {
            $result = CronService::runDaily();
            $errors = (array) ($result['reminders']['errors'] ?? []);
            if ($errors !== []) {
                http_response_code(200); // iş çalıştı; hatalar gövdede bildirilir
            }
            return '[' . date('Y-m-d H:i:s') . '] evrako cron: ' . CronService::summary($result)
                . ' · süre: ' . number_format((float) $result['microtime'], 3) . " sn\n";
        } catch (\Throwable $e) {
            Logger::error('Web cron hatası: ' . $e->getMessage());
            http_response_code(500);
            return 'Cron hatası: ' . $e->getMessage() . "\n";
        }
    }
}
