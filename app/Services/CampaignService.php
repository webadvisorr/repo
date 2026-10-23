<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Mailer;
use App\Core\Settings;
use App\Core\Translator;

/**
 * Kampanya / reklam maili (Faz 4).
 *
 * Kampanya gövdesi panelden yazılır; hedef kitle `audience_json` içinde saklanır.
 * Gönderim mevcut bildirim kuyruğunu kullanır (`notifications`, template_key='campaign'):
 * böylece yeniden deneme + geri çekilme, günlük limit, açılma pikseli, abonelikten çıkma
 * ve Bildirim Günlüğü görünürlüğü hatırlatma motoruyla aynen paylaşılır.
 *
 * Kuyruk kaydı `campaign_recipients` tablosunda tutulur (alıcı bazlı durum + açılma).
 */
final class CampaignService
{
    public const STATUSES = ['draft', 'scheduled', 'sending', 'sent', 'cancelled'];
    public const DEDUPE_PREFIX = 'campaign:';
    public const MAX_RECIPIENTS = 20000;

    /** Şablonda kullanılabilen değişkenler. @return array<string,string> */
    public static function variables(): array
    {
        return [
            '{ad}'              => 'Alıcının adı',
            '{eposta}'          => 'Alıcının e-posta adresi',
            '{kurum}'           => 'Kurum (firma) adı',
            '{plan}'            => 'Abonelik planı adı',
            '{marka_adi}'       => 'Site markası',
            '{site_url}'        => 'Site adresi',
            '{yil}'             => 'Geçerli yıl',
            '{abonelikten_cik}' => 'Abonelikten çıkma bağlantısı',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Liste / kayıt                                                       */
    /* ------------------------------------------------------------------ */

    /** @return array<int,array> */
    public static function all(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        return Database::select(
            'SELECT c.*, u.full_name AS created_by_name
             FROM campaigns c
             LEFT JOIN users u ON u.id = c.created_by
             ORDER BY c.id DESC LIMIT ' . $limit
        );
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM campaigns WHERE id = ? LIMIT 1', [$id]);
    }

    /** @return array<int,array> */
    public static function organizations(): array
    {
        return Database::select('SELECT id, name FROM organizations ORDER BY name ASC LIMIT 500');
    }

    /** @return array<int,array> */
    public static function plans(): array
    {
        return Database::select('SELECT id, `key`, name FROM plans ORDER BY sort_order ASC, id ASC');
    }

    /** @return array{plan_key:string,org_id:int,locale:string,only_marketing_opt_in:bool} */
    public static function normalizeAudience(array $filters): array
    {
        $locale = (string) ($filters['locale'] ?? '');
        return [
            'plan_key'              => trim((string) ($filters['plan_key'] ?? '')),
            'org_id'                => max(0, (int) ($filters['org_id'] ?? 0)),
            'locale'                => in_array($locale, Translator::SUPPORTED, true) ? $locale : '',
            'only_marketing_opt_in' => !empty($filters['only_marketing_opt_in']),
        ];
    }

    /** @return array{plan_key:string,org_id:int,locale:string,only_marketing_opt_in:bool} */
    public static function audienceOf(array $campaign): array
    {
        $decoded = json_decode((string) ($campaign['audience_json'] ?? ''), true);
        return self::normalizeAudience(is_array($decoded) ? $decoded : []);
    }

    /**
     * @param array{name?:string,subject?:string,body_html?:string,audience?:array,scheduled_at?:string} $data
     * @return array{ok:bool,id?:int,error?:string}
     */
    public static function save(?int $id, array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $subject = trim((string) ($data['subject'] ?? ''));
        $body = (string) ($data['body_html'] ?? '');

        if ($name === '' || $subject === '' || trim($body) === '') {
            return ['ok' => false, 'error' => 'Kampanya adı, konu ve içerik zorunludur.'];
        }

        $now = date('Y-m-d H:i:s');
        $scheduledAt = self::parseSchedule((string) ($data['scheduled_at'] ?? ''));
        $row = [
            'name'          => mb_substr($name, 0, 160),
            'subject'       => mb_substr($subject, 0, 255),
            'body_html'     => $body,
            'audience_json' => (string) json_encode(self::normalizeAudience((array) ($data['audience'] ?? [])), JSON_UNESCAPED_UNICODE),
            'scheduled_at'  => $scheduledAt,
            'updated_at'    => $now,
        ];

        if ($id === null) {
            $row['status'] = $scheduledAt !== null ? 'scheduled' : 'draft';
            $row['created_by'] = Auth::id();
            $row['created_at'] = $now;
            $newId = Database::insert('campaigns', $row);
            Audit::log('campaign.create', 'campaign', $newId, ['name' => $row['name']]);
            return ['ok' => true, 'id' => $newId];
        }

        $existing = self::find($id);
        if ($existing === null) {
            return ['ok' => false, 'error' => 'Kampanya bulunamadı.'];
        }
        if ((string) $existing['status'] === 'sending') {
            return ['ok' => false, 'error' => 'Gönderim sürerken kampanya düzenlenemez; önce iptal edin.'];
        }

        $row['status'] = match (true) {
            (string) $existing['status'] === 'sent' => 'sent',
            $scheduledAt !== null                   => 'scheduled',
            default                                 => 'draft',
        };

        Database::update('campaigns', $row, 'id = :id', ['id' => $id]);
        Audit::log('campaign.update', 'campaign', $id, ['name' => $row['name']]);
        return ['ok' => true, 'id' => $id];
    }

    /** @return array{ok:bool,error?:string,cancelled?:int} */
    public static function cancel(int $id): array
    {
        $campaign = self::find($id);
        if ($campaign === null) {
            return ['ok' => false, 'error' => 'Kampanya bulunamadı.'];
        }
        if (!in_array((string) $campaign['status'], ['draft', 'scheduled', 'sending'], true)) {
            return ['ok' => false, 'error' => 'Bu kampanya iptal edilemez.'];
        }

        $cancelled = Database::run(
            "UPDATE notifications SET status = 'cancelled' WHERE status = 'queued' AND dedupe_key LIKE ?",
            [self::DEDUPE_PREFIX . $id . ':%']
        )->rowCount();

        Database::update('campaigns', [
            'status'      => 'cancelled',
            'finished_at' => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);

        Audit::log('campaign.cancel', 'campaign', $id, ['cancelled_notifications' => $cancelled]);
        return ['ok' => true, 'cancelled' => (int) $cancelled];
    }

    /** @return array{ok:bool,error?:string} */
    public static function destroy(int $id): array
    {
        $campaign = self::find($id);
        if ($campaign === null) {
            return ['ok' => false, 'error' => 'Kampanya bulunamadı.'];
        }
        if (!in_array((string) $campaign['status'], ['draft', 'scheduled', 'cancelled'], true)) {
            return ['ok' => false, 'error' => 'Gönderilmiş kampanya silinemez (gönderim kaydı saklanır).'];
        }

        Database::run('DELETE FROM notifications WHERE dedupe_key LIKE ?', [self::DEDUPE_PREFIX . $id . ':%']);
        Database::delete('campaigns', 'id = ?', [$id]); // campaign_recipients CASCADE
        Audit::log('campaign.delete', 'campaign', $id, ['name' => (string) $campaign['name']]);
        return ['ok' => true];
    }

    /* ------------------------------------------------------------------ */
    /* Hedef kitle                                                         */
    /* ------------------------------------------------------------------ */

    /**
     * Kitle sorgusu: aktif + e-posta izni olan kullanıcılar; abonelikten çıkanlar hariç.
     * @param array{plan_key:string,org_id:int,locale:string,only_marketing_opt_in:bool} $audience
     * @return array{0:string,1:array<int,mixed>}
     */
    private static function audienceSql(array $audience, bool $count): array
    {
        $where = ["u.status = 'active'", 'u.notify_email = 1'];
        $params = [];

        if ($audience['only_marketing_opt_in']) {
            $where[] = 'u.marketing_opt_in = 1';
        }
        if ($audience['locale'] !== '') {
            $where[] = 'u.locale = ?';
            $params[] = $audience['locale'];
        }
        if ($audience['org_id'] > 0) {
            $where[] = 'm.organization_id = ?';
            $params[] = $audience['org_id'];
        }
        if ($audience['plan_key'] !== '') {
            $where[] = 'p.`key` = ?';
            $params[] = $audience['plan_key'];
        }
        $where[] = 'NOT EXISTS (SELECT 1 FROM unsubscribes un WHERE un.email = u.email)';

        $select = $count
            ? 'SELECT COUNT(DISTINCT u.id)'
            : 'SELECT u.id, MIN(u.full_name) AS full_name, MIN(u.email) AS email, MIN(u.locale) AS locale,
                      MIN(m.organization_id) AS organization_id, MIN(o.name) AS org_name, MIN(p.name) AS plan_name';

        $sql = $select . '
                FROM users u
                JOIN organization_members m ON m.user_id = u.id
                JOIN organizations o ON o.id = m.organization_id
                LEFT JOIN subscriptions s ON s.organization_id = m.organization_id AND s.status IN (\'active\', \'trialing\')
                LEFT JOIN plans p ON p.id = s.plan_id
                WHERE ' . implode(' AND ', $where);

        if (!$count) {
            $sql .= ' GROUP BY u.id ORDER BY u.id ASC';
        }
        return [$sql, $params];
    }

    public static function audienceCount(array $audience): int
    {
        [$sql, $params] = self::audienceSql(self::normalizeAudience($audience), true);
        return (int) Database::scalar($sql, $params);
    }

    /** @return array<int,array> */
    public static function audienceUsers(array $audience, int $limit = 0, int $offset = 0): array
    {
        [$sql, $params] = self::audienceSql(self::normalizeAudience($audience), false);
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . max(0, (int) $offset);
        }
        return Database::select($sql, $params);
    }

    /* ------------------------------------------------------------------ */
    /* Kuyruk & gönderim                                                   */
    /* ------------------------------------------------------------------ */

    public static function dedupeKey(int $campaignId, int $userId): string
    {
        return self::DEDUPE_PREFIX . $campaignId . ':user:' . $userId;
    }

    public static function campaignIdFromDedupeKey(string $key): ?int
    {
        return preg_match('/^' . preg_quote(self::DEDUPE_PREFIX, '/') . '(\d+):user:\d+$/', $key, $m) === 1 ? (int) $m[1] : null;
    }

    /**
     * Hedef kitleyi kampanya kuyruğuna alır (idempotent: aynı alıcı iki kez kuyruğa girmez).
     * @return array{ok:bool,queued:int,total:int,error:?string}
     */
    public static function queue(int $campaignId): array
    {
        $campaign = self::find($campaignId);
        if ($campaign === null) {
            return ['ok' => false, 'queued' => 0, 'total' => 0, 'error' => 'Kampanya bulunamadı.'];
        }
        if ((string) $campaign['status'] === 'cancelled') {
            return ['ok' => false, 'queued' => 0, 'total' => 0, 'error' => 'İptal edilmiş kampanya gönderilemez.'];
        }

        $users = self::audienceUsers(self::audienceOf($campaign), self::MAX_RECIPIENTS);
        if ($users === []) {
            return ['ok' => false, 'queued' => 0, 'total' => 0, 'error' => 'Hedef kitlede uygun alıcı yok (aktif, e-posta izni olan kullanıcı gerekir).'];
        }

        $now = date('Y-m-d H:i:s');
        $scheduledFor = ($campaign['scheduled_at'] !== null && (string) $campaign['scheduled_at'] > $now)
            ? (string) $campaign['scheduled_at']
            : null;

        $queued = 0;
        foreach ($users as $user) {
            $vars = self::varsFor($user);
            $rendered = self::render((string) $campaign['subject'], (string) $campaign['body_html'], $vars);

            Database::run(
                "INSERT IGNORE INTO campaign_recipients (campaign_id, user_id, email, status) VALUES (?, ?, ?, 'pending')",
                [$campaignId, (int) $user['id'], (string) $user['email']]
            );

            $statement = Database::run(
                "INSERT IGNORE INTO notifications
                    (user_id, organization_id, vehicle_id, document_id, channel, template_key, recipient,
                     subject, subject_rendered, body_html, status, dedupe_key, attempts, created_at, scheduled_for)
                 VALUES (?, ?, NULL, NULL, 'email', 'campaign', ?, ?, ?, ?, 'queued', ?, 0, ?, ?)",
                [
                    (int) $user['id'],
                    (int) $user['organization_id'],
                    (string) $user['email'],
                    mb_substr($rendered['subject'], 0, 255),
                    mb_substr($rendered['subject'], 0, 255),
                    $rendered['html'],
                    self::dedupeKey($campaignId, (int) $user['id']),
                    $now,
                    $scheduledFor,
                ]
            );
            if ($statement->rowCount() > 0) {
                $queued++;
            }
        }

        Database::update('campaigns', [
            'recipient_count' => count($users),
            'status'          => $scheduledFor !== null ? 'scheduled' : 'sending',
            'started_at'      => $scheduledFor !== null ? null : $now,
            'updated_at'      => $now,
        ], 'id = :id', ['id' => $campaignId]);

        self::refreshCounters($campaignId);
        Audit::log('campaign.queue', 'campaign', $campaignId, ['recipients' => count($users), 'queued' => $queued]);

        return ['ok' => true, 'queued' => $queued, 'total' => count($users), 'error' => null];
    }

    /**
     * Kuyruğa al + hemen gönder.
     * @return array{ok:bool,error:?string,queued:int,dispatch:?array}
     */
    public static function sendNow(int $campaignId, ?int $limit = null): array
    {
        $queued = self::queue($campaignId);
        if (!$queued['ok']) {
            return ['ok' => false, 'error' => $queued['error'], 'queued' => 0, 'dispatch' => null];
        }

        $dispatch = ReminderService::dispatch($limit);
        Audit::log('campaign.send', 'campaign', $campaignId, [
            'queued' => $queued['queued'], 'sent' => $dispatch['sent'], 'failed' => $dispatch['failed'],
        ]);

        return ['ok' => true, 'error' => null, 'queued' => $queued['queued'], 'dispatch' => $dispatch];
    }

    /** Zamanı gelmiş planlı kampanyaları kuyruğa alır (cron). */
    public static function queueDue(?string $now = null): int
    {
        $now = $now ?? date('Y-m-d H:i:s');
        $rows = Database::select(
            "SELECT id FROM campaigns
             WHERE status = 'scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= ?
             ORDER BY scheduled_at ASC LIMIT 20",
            [$now]
        );

        $queued = 0;
        foreach ($rows as $row) {
            $result = self::queue((int) $row['id']);
            if ($result['ok']) {
                $queued += (int) $result['queued'];
            } else {
                Logger::warning('Planlı kampanya kuyruğa alınamadı: ' . (string) $result['error'], ['campaign' => (int) $row['id']]);
            }
        }
        return $queued;
    }

    /**
     * Tek kampanya bildirimini gönderir (ReminderService::sendOne buraya yönlendirir).
     * Sonuç: sent|failed|skipped
     */
    public static function sendNotification(Mailer $mailer, array $notification): string
    {
        $id = (int) $notification['id'];
        $attempts = (int) $notification['attempts'] + 1;
        $campaignId = self::campaignIdFromDedupeKey((string) ($notification['dedupe_key'] ?? ''));
        $userId = (int) $notification['user_id'];

        if ($campaignId === null || self::find($campaignId) === null) {
            Database::update('notifications', [
                'status' => 'failed', 'attempts' => $attempts, 'error' => 'Kampanya kaydı bulunamadı.',
            ], 'id = :id', ['id' => $id]);
            return 'failed';
        }

        $user = Database::first(
            'SELECT id, full_name, email, locale, notify_email, marketing_opt_in, status FROM users WHERE id = ? LIMIT 1',
            [$userId]
        );

        if ($user === null || (int) $user['notify_email'] !== 1 || (string) $user['status'] !== 'active') {
            self::finishRecipient($campaignId, $userId, 'failed', 'Alıcı artık uygun değil.');
            Database::update('notifications', [
                'status' => 'skipped', 'attempts' => $attempts, 'error' => 'Alıcı veya kurum artık uygun değil.',
            ], 'id = :id', ['id' => $id]);
            self::refreshCounters($campaignId);
            return 'skipped';
        }

        if ((int) $user['marketing_opt_in'] !== 1 || MailTemplateService::isUnsubscribed((string) $user['email'])) {
            self::finishRecipient($campaignId, $userId, 'unsubscribed', 'Pazarlama e-postası izni yok.');
            Database::update('notifications', [
                'status' => 'skipped', 'attempts' => $attempts, 'error' => 'Alıcı pazarlama e-postalarını almıyor.',
            ], 'id = :id', ['id' => $id]);
            self::refreshCounters($campaignId);
            return 'skipped';
        }

        $locale = in_array((string) $user['locale'], MailTemplateService::LOCALES, true) ? (string) $user['locale'] : 'tr';
        $subject = (string) ($notification['subject_rendered'] ?? '');
        if ($subject === '') {
            $subject = (string) ($notification['subject'] ?? '');
        }
        $html = self::withFooter((string) ($notification['body_html'] ?? ''), $id, (string) $user['email'], $locale);

        $send = $mailer->send((string) $user['email'], $subject, $html, self::toText($html), [], self::headers((string) $user['email']));

        if ($send['ok']) {
            Database::update('notifications', [
                'status'           => 'sent',
                'subject_rendered' => mb_substr($subject, 0, 255),
                'body_html'        => $html,
                'attempts'         => $attempts,
                'error'            => null,
                'sent_at'          => date('Y-m-d H:i:s'),
                'scheduled_for'    => null,
            ], 'id = :id', ['id' => $id]);
            self::finishRecipient($campaignId, $userId, 'sent', null);
            self::refreshCounters($campaignId);
            return 'sent';
        }

        $maxAttempts = max(1, (int) Settings::get('reminders.max_attempts', 3));
        $error = mb_substr((string) ($send['error'] ?? 'Bilinmeyen gönderim hatası'), 0, 255);

        if ($attempts >= $maxAttempts) {
            Database::update('notifications', [
                'status' => 'failed', 'attempts' => $attempts, 'error' => $error, 'scheduled_for' => null,
            ], 'id = :id', ['id' => $id]);
            self::finishRecipient($campaignId, $userId, 'failed', $error);
            self::refreshCounters($campaignId);
            Logger::error('Kampanya e-postası gönderilemedi (kalıcı): ' . $error, ['notification' => $id, 'to' => (string) $user['email']]);
            return 'failed';
        }

        $delayMinutes = 15 * (2 ** ($attempts - 1));
        Database::update('notifications', [
            'status'        => 'queued',
            'attempts'      => $attempts,
            'error'         => $error,
            'scheduled_for' => date('Y-m-d H:i:s', time() + $delayMinutes * 60),
        ], 'id = :id', ['id' => $id]);
        self::finishRecipient($campaignId, $userId, 'pending', $error);
        Logger::warning('Kampanya e-postası yeniden denenecek: ' . $error, ['notification' => $id, 'attempt' => $attempts]);
        return 'skipped';
    }

    /** Test gönderimi (kuyruğa girmez). @return array{ok:bool,error?:string,subject?:string} */
    public static function test(array $campaign, string $toEmail): array
    {
        $toEmail = trim($toEmail);
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Geçerli bir e-posta adresi girin.'];
        }

        $mailer = new Mailer();
        if (!$mailer->isConfigured()) {
            return ['ok' => false, 'error' => 'SMTP ayarları eksik; test e-postası gönderilemedi.'];
        }

        $user = Database::first(
            'SELECT u.id, u.full_name, u.email, u.locale, o.name AS org_name, p.name AS plan_name
             FROM users u
             LEFT JOIN organization_members m ON m.user_id = u.id
             LEFT JOIN organizations o ON o.id = m.organization_id
             LEFT JOIN subscriptions s ON s.organization_id = m.organization_id AND s.status IN (\'active\', \'trialing\')
             LEFT JOIN plans p ON p.id = s.plan_id
             WHERE u.email = ? LIMIT 1',
            [$toEmail]
        );

        $vars = self::varsFor($user ?? ['email' => $toEmail, 'full_name' => 'Test Alıcı', 'locale' => 'tr']);
        $rendered = self::render((string) ($campaign['subject'] ?? ''), (string) ($campaign['body_html'] ?? ''), $vars);
        $locale = in_array((string) ($user['locale'] ?? 'tr'), MailTemplateService::LOCALES, true) ? (string) ($user['locale'] ?? 'tr') : 'tr';
        $html = self::withFooter($rendered['html'], 0, $toEmail, $locale);
        $subject = '[TEST] ' . $rendered['subject'];

        $send = $mailer->send($toEmail, $subject, $html, self::toText($html), [], self::headers($toEmail));
        Audit::log('campaign.test', 'campaign', (int) ($campaign['id'] ?? 0), ['to' => $toEmail, 'ok' => (bool) $send['ok']]);

        return $send['ok']
            ? ['ok' => true, 'subject' => $subject]
            : ['ok' => false, 'error' => (string) ($send['error'] ?? 'Test e-postası gönderilemedi.')];
    }

    /** @return array{pending:int,sent:int,failed:int,unsubscribed:int,total:int,opened:int} */
    public static function stats(int $campaignId): array
    {
        $rows = Database::select(
            'SELECT status, COUNT(*) AS total FROM campaign_recipients WHERE campaign_id = ? GROUP BY status',
            [$campaignId]
        );

        $out = ['pending' => 0, 'sent' => 0, 'failed' => 0, 'unsubscribed' => 0];
        foreach ($rows as $row) {
            $status = (string) $row['status'];
            if (array_key_exists($status, $out)) {
                $out[$status] = (int) $row['total'];
            }
        }
        $total = array_sum($out);
        $out['total'] = $total;
        $out['opened'] = (int) Database::scalar(
            'SELECT COUNT(*) FROM campaign_recipients WHERE campaign_id = ? AND opened_at IS NOT NULL',
            [$campaignId]
        );
        return $out;
    }

    /** @return array<int,array> */
    public static function recipients(int $campaignId, int $limit = 200, int $offset = 0): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);
        return Database::select(
            'SELECT r.*, u.full_name
             FROM campaign_recipients r
             LEFT JOIN users u ON u.id = r.user_id
             WHERE r.campaign_id = ?
             ORDER BY r.id ASC LIMIT ' . $limit . ' OFFSET ' . $offset,
            [$campaignId]
        );
    }

    /** Açılma pikseli kampanya bildirimine aitse alıcı + kampanya sayacını günceller. */
    public static function markOpened(int $notificationId): void
    {
        $notification = Database::first(
            'SELECT user_id, dedupe_key, template_key FROM notifications WHERE id = ? LIMIT 1',
            [$notificationId]
        );
        if ($notification === null || (string) $notification['template_key'] !== 'campaign') {
            return;
        }

        $campaignId = self::campaignIdFromDedupeKey((string) $notification['dedupe_key']);
        if ($campaignId === null) {
            return;
        }

        $statement = Database::run(
            'UPDATE campaign_recipients SET opened_at = ? WHERE campaign_id = ? AND user_id = ? AND opened_at IS NULL',
            [date('Y-m-d H:i:s'), $campaignId, (int) $notification['user_id']]
        );
        if ($statement->rowCount() > 0) {
            self::refreshCounters($campaignId);
        }
    }

    /** Alıcı sayaçlarını kampanya satırına yazar; bekleyen kalmazsa kampanyayı kapatır. */
    public static function refreshCounters(int $campaignId): void
    {
        $row = Database::first(
            "SELECT SUM(status = 'sent') AS sent, SUM(status = 'failed') AS failed,
                    SUM(status = 'pending') AS pending, SUM(opened_at IS NOT NULL) AS opened,
                    SUM(clicked_at IS NOT NULL) AS clicked
             FROM campaign_recipients WHERE campaign_id = ?",
            [$campaignId]
        ) ?? [];

        $sent = (int) ($row['sent'] ?? 0);
        $failed = (int) ($row['failed'] ?? 0);
        $pending = (int) ($row['pending'] ?? 0);
        $now = date('Y-m-d H:i:s');

        $update = [
            'sent_count'   => $sent,
            'failed_count' => $failed,
            'open_count'   => (int) ($row['opened'] ?? 0),
            'click_count'  => (int) ($row['clicked'] ?? 0),
            'updated_at'   => $now,
        ];

        $campaign = self::find($campaignId);
        if ($campaign !== null && (string) $campaign['status'] === 'sending' && $pending === 0 && ($sent + $failed) > 0) {
            $update['status'] = 'sent';
            $update['finished_at'] = $now;
        }

        Database::update('campaigns', $update, 'id = :id', ['id' => $campaignId]);
    }

    /* ------------------------------------------------------------------ */
    /* Şablon / yardımcılar                                                */
    /* ------------------------------------------------------------------ */

    /** @return array{subject:string,html:string} */
    public static function render(string $subject, string $html, array $vars): array
    {
        $map = [];
        foreach ($vars as $key => $value) {
            $map['{' . trim((string) $key, '{}') . '}'] = (string) $value;
        }
        return ['subject' => strtr($subject, $map), 'html' => strtr($html, $map)];
    }

    /** @return array<string,string> */
    public static function varsFor(array $user): array
    {
        $email = (string) ($user['email'] ?? '');
        return [
            'ad'              => (string) ($user['full_name'] ?? ''),
            'eposta'          => $email,
            'kurum'           => (string) ($user['org_name'] ?? ''),
            'plan'            => (string) ($user['plan_name'] ?? ''),
            'marka_adi'       => (string) Settings::get('brand.title', 'Evrako'),
            'site_url'        => url('/'),
            'yil'             => date('Y'),
            'abonelikten_cik' => $email === '' ? '' : MailTemplateService::unsubscribeUrl($email),
        ];
    }

    /** Kampanya gövdesine abonelikten çıkma alt bilgisi + açılma pikseli ekler. */
    private static function withFooter(string $html, int $notificationId, string $email, string $locale): string
    {
        $previous = Translator::locale();
        Translator::setLocale($locale);
        $note = t('campaign.unsub_note');
        $link = t('campaign.unsub');
        $rights = t('campaign.rights');
        Translator::setLocale($previous);

        $footer = '<div style="max-width:600px;margin:0 auto;padding:16px 20px;font:13px/1.6 Arial,Helvetica,sans-serif;color:#6B7C93;border-top:1px solid #E3E9F2">'
            . '<p style="margin:0 0 8px">' . e($note) . ' <a href="' . e(MailTemplateService::unsubscribeUrl($email)) . '" style="color:#00C48C;text-decoration:underline">' . e($link) . '</a>.</p>'
            . '<p style="margin:0">&copy; ' . date('Y') . ' ' . e((string) Settings::get('brand.title', 'Evrako')) . '. ' . e($rights) . '</p>'
            . '</div>';

        if ($notificationId > 0) {
            $footer .= '<img src="' . e(ReminderService::openPixelUrl($notificationId)) . '" width="1" height="1" alt="" style="display:block;border:0;outline:none">';
        }

        return '<div style="background:#F7F9FC;padding:22px 0">' . $html . '</div>' . $footer;
    }

    /** @return array<string,string> */
    private static function headers(string $email): array
    {
        $headers = [
            'List-Unsubscribe'      => '<' . MailTemplateService::oneClickUrl($email) . '>, <' . MailTemplateService::unsubscribeUrl($email) . '>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ];
        if ((string) Settings::get('brand.email', '') !== '') {
            $headers['Reply-To'] = (string) Settings::get('brand.email');
        }
        return $headers;
    }

    private static function toText(string $html): string
    {
        $text = (string) preg_replace('/<(br|\/p|\/div|\/tr|\/h[1-6])\s*\/?>/i', "\n", $html);
        $text = (string) preg_replace('/<img[^>]*>/i', '', $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace("/[ \t]+\n/", "\n", $text);
        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }

    private static function finishRecipient(int $campaignId, int $userId, string $status, ?string $error): void
    {
        Database::run(
            "UPDATE campaign_recipients
             SET status = ?, error = ?, sent_at = CASE WHEN ? = 'sent' THEN ? ELSE sent_at END
             WHERE campaign_id = ? AND user_id = ?",
            [
                $status,
                $error === null ? null : mb_substr($error, 0, 255),
                $status,
                date('Y-m-d H:i:s'),
                $campaignId,
                $userId,
            ]
        );
    }

    private static function parseSchedule(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $value = str_replace('T', ' ', $value);
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $timestamp);
    }
}
