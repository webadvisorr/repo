<?php
declare(strict_types=1);

/**
 * Test yardımcısı: hatırlatma motorunu komut satırından çağırır (JSON çıktı).
 * Kullanım: php .freebuff/rem-call.php <komut> [arg...]
 */

require dirname(__DIR__) . '/app/bootstrap.php';

$cmd = (string) ($argv[1] ?? 'help');
$json = static fn ($data): string => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';

switch ($cmd) {
    case 'scan':
        echo $json(App\Services\ReminderService::scan());
        break;
    case 'dispatch':
        echo $json(App\Services\ReminderService::dispatch());
        break;
    case 'run':
        echo $json(App\Services\ReminderService::run());
        break;
    case 'requeue':
        echo $json(App\Services\ReminderService::requeue((int) ($argv[2] ?? 0)));
        break;
    case 'requeue-failed':
        echo (string) App\Services\ReminderService::requeueFailed((int) ($argv[2] ?? 100));
        break;
    case 'cancel':
        echo $json(App\Services\ReminderService::cancel((int) ($argv[2] ?? 0)));
        break;
    case 'stats':
        echo $json(App\Services\ReminderService::stats());
        break;
    case 'sent-today':
        echo (string) App\Services\ReminderService::sentToday();
        break;
    case 'unsub-url':
        echo App\Services\MailTemplateService::unsubscribeUrl((string) ($argv[2] ?? ''));
        break;
    case 'oneclick-url':
        echo App\Services\MailTemplateService::oneClickUrl((string) ($argv[2] ?? ''));
        break;
    case 'is-unsubscribed':
        echo App\Services\MailTemplateService::isUnsubscribed((string) ($argv[2] ?? '')) ? '1' : '0';
        break;
    case 'render':
        // php rem-call.php render <key> <locale>
        $locale = (string) ($argv[3] ?? 'tr');
        $previous = App\Core\Translator::locale();
        App\Core\Translator::setLocale($locale);
        $rendered = App\Services\MailTemplateService::render($argv[2] ?? 'reminder', $locale, App\Services\MailTemplateService::sampleVars(null, null, null, $locale));
        App\Core\Translator::setLocale($previous);
        echo $rendered['subject'] . "\n---8<---\n" . $rendered['html'];
        break;
    case 'subject':
        $template = App\Services\MailTemplateService::find((string) ($argv[2] ?? 'reminder'), (string) ($argv[3] ?? 'tr'));
        echo (string) ($template['subject'] ?? '');
        break;
    case 'cron-token':
        echo (string) App\Core\Config::get('security.cron_token', '');
        break;
    case 'pixel-url':
        echo App\Services\ReminderService::openPixelUrl((int) ($argv[2] ?? 0));
        break;
    case 'templates':
        echo $json(array_map(static fn (array $r): array => [
            'key' => $r['key'], 'locale' => $r['locale'], 'subject' => $r['subject'],
            'is_active' => (int) $r['is_active'], 'len' => strlen((string) $r['body_html']),
        ], App\Services\MailTemplateService::all()));
        break;
    default:
        echo "komutlar: scan|dispatch|run|requeue|requeue-failed|cancel|stats|sent-today|unsub-url|oneclick-url|is-unsubscribed|render|subject|cron-token|pixel-url|templates\n";
}
