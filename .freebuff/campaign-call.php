<?php
declare(strict_types=1);

/**
 * Test yardımcısı: kampanya servisini komut satırından sorgular.
 * Kullanım: php .freebuff/campaign-call.php <komut> [arg...]
 */

require dirname(__DIR__) . '/app/bootstrap.php';

$cmd = (string) ($argv[1] ?? 'help');
$json = static fn ($data): string => (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

switch ($cmd) {
    case 'audience':
        // audience <orgId> [optin|all]
        $audience = [
            'org_id'                => (int) ($argv[2] ?? 0),
            'only_marketing_opt_in' => (string) ($argv[3] ?? 'optin') === 'optin',
        ];
        echo (string) App\Services\CampaignService::audienceCount($audience);
        break;

    case 'last-id':
        echo (string) App\Core\Database::scalar('SELECT COALESCE(MAX(id), 0) FROM campaigns');
        break;

    case 'campaign':
        $row = App\Services\CampaignService::find((int) ($argv[2] ?? 0));
        echo $row === null ? 'null' : $json([
            'id'              => (int) $row['id'],
            'status'          => (string) $row['status'],
            'name'            => (string) $row['name'],
            'recipient_count' => (int) $row['recipient_count'],
            'sent_count'      => (int) $row['sent_count'],
            'failed_count'    => (int) $row['failed_count'],
            'open_count'      => (int) $row['open_count'],
            'scheduled_at'    => $row['scheduled_at'],
            'finished_at'     => $row['finished_at'],
            'audience'        => (string) $row['audience_json'],
        ]);
        break;

    case 'stats':
        echo $json(App\Services\CampaignService::stats((int) ($argv[2] ?? 0)));
        break;

    case 'notification':
        // notification <campaignId> <userId>
        $key = App\Services\CampaignService::dedupeKey((int) ($argv[2] ?? 0), (int) ($argv[3] ?? 0));
        $row = App\Core\Database::first(
            'SELECT id, user_id, recipient, status, attempts, dedupe_key, sent_at, subject_rendered, LENGTH(body_html) AS body_len
             FROM notifications WHERE dedupe_key = ? LIMIT 1',
            [$key]
        );
        echo $row === null ? 'null' : $json($row);
        break;

    case 'body-flags':
        // body-flags <notificationId>
        $row = App\Core\Database::first('SELECT body_html FROM notifications WHERE id = ? LIMIT 1', [(int) ($argv[2] ?? 0)]);
        $body = (string) ($row['body_html'] ?? '');
        echo $json([
            'len'    => strlen($body),
            'pixel'  => str_contains($body, '/mail-piksel/'),
            'unsub'  => str_contains($body, '/abonelikten-cikar'),
            'ru'     => str_contains($body, 'отписаться'),
            'tr'     => str_contains($body, 'abonelikten çıkabilirsiniz'),
            'footer' => str_contains($body, 'Tüm hakları saklıdır') || str_contains($body, 'Все права защищены'),
        ]);
        break;

    case 'pixel-url':
        echo App\Services\ReminderService::openPixelUrl((int) ($argv[2] ?? 0));
        break;

    case 'recipients':
        echo $json(array_map(static fn (array $r): array => [
            'email'     => (string) $r['email'],
            'status'    => (string) $r['status'],
            'sent_at'   => $r['sent_at'],
            'opened_at' => $r['opened_at'],
            'error'     => $r['error'],
        ], App\Services\CampaignService::recipients((int) ($argv[2] ?? 0), 50)));
        break;

    case 'queue-due':
        echo (string) App\Services\CampaignService::queueDue((string) ($argv[2] ?? date('Y-m-d H:i:s')));
        break;

    case 'notifications-count':
        // notifications-count <campaignId>
        echo (string) App\Core\Database::scalar(
            'SELECT COUNT(*) FROM notifications WHERE dedupe_key LIKE ?',
            [App\Services\CampaignService::DEDUPE_PREFIX . (int) ($argv[2] ?? 0) . ':%']
        );
        break;

    default:
        echo "komutlar: audience|last-id|campaign|stats|notification|body-flags|pixel-url|recipients|queue-due|notifications-count\n";
}
