<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Mailer;
use App\Core\Settings;
use App\Core\Translator;

/**
 * Hatırlatma motoru.
 *
 * 1. `scan()`  — evrak bitiş tarihine göre (7/3/1/0 gün) kuyruk kaydı üretir.
 *                Idempotenttir: `notifications.dedupe_key` (kullanıcı + evrak + gün + tarih)
 *                sayesinde aynı hatırlatma iki kez kuyruğa girmez.
 * 2. `dispatch()` — kuyruğu SMTP ile gönderir, hataları geri çekilerek (backoff) yeniden dener,
 *                günlük gönderim limitine uyar.
 *
 * Tüm gönderimler `notifications` tablosunda izlenir (bildirim günlüğü).
 */
final class ReminderService
{
    public const TEMPLATE_KEY = 'reminder';

    /** Hatırlatma ayarlarının fabrika değerleri. */
    public const SETTING_DEFAULTS = [
        'reminders.enabled'      => '1',
        'reminders.offsets'      => '7,3,1,0',
        'reminders.hour'         => '8',
        'reminders.daily_limit'  => '500',
        'reminders.batch'        => '50',
        'reminders.max_attempts' => '3',
    ];

    /**
     * Tam döngü: kuyruğu doldur + gönder. Cron ve panelden manuel tetikleme bunu çağırır.
     * @param array{limit?:int,date?:string} $options
     * @return array{enabled:bool,locked:bool,scanned:int,created:int,duplicates:int,sent:int,failed:int,skipped:int,remaining:int,errors:array<int,string>}
     */
    public static function run(array $options = []): array
    {
        $result = [
            'enabled'    => true,
            'locked'     => false,
            'scanned'    => 0,
            'created'    => 0,
            'duplicates' => 0,
            'sent'       => 0,
            'failed'     => 0,
            'skipped'    => 0,
            'remaining'  => 0,
            'errors'     => [],
        ];

        if (!self::enabled()) {
            $result['enabled'] = false;
            return $result;
        }

        MailTemplateService::ensureDefaults();
        self::ensureSettings();

        // Aynı anda iki cron çalışırsa çift gönderim olmasın.
        $lock = self::lock();
        if ($lock === null) {
            $result['locked'] = true;
            return $result;
        }

        try {
            $scan = self::scan(isset($options['date']) ? (string) $options['date'] : null);
            $result['scanned']    = $scan['scanned'];
            $result['created']    = $scan['created'];
            $result['duplicates'] = $scan['duplicates'];
            $result['skipped']    = $scan['skipped'];
            foreach ($scan['errors'] as $error) {
                $result['errors'][] = $error;
            }

            $dispatch = self::dispatch(isset($options['limit']) ? (int) $options['limit'] : null);
            $result['sent']      = $dispatch['sent'];
            $result['failed']    = $dispatch['failed'];
            $result['remaining'] = $dispatch['remaining'];
            $result['skipped']  += $dispatch['skipped'];
            if (isset($dispatch['error']) && $dispatch['error'] !== null) {
                $result['errors'][] = (string) $dispatch['error'];
            }
        } finally {
            self::unlock($lock);
        }

        return $result;
    }

    /**
     * Bugünün hatırlatmalarını kuyruğa alır (mail göndermez).
     * @return array{scanned:int,created:int,duplicates:int,skipped:int,errors:array<int,string>}
     */
    public static function scan(?string $date = null): array
    {
        $result = ['scanned' => 0, 'created' => 0, 'duplicates' => 0, 'skipped' => 0, 'errors' => []];
        $today = $date ?? date('Y-m-d');
        $window = self::windowDays();

        $documents = Database::select(
            "SELECT d.id, d.vehicle_id, d.end_date, d.reminder_offsets, dt.default_offsets, dt.labels_json AS type_labels,
                    v.organization_id, v.plate
             FROM vehicle_documents d
             JOIN vehicles v ON v.id = d.vehicle_id
             JOIN document_types dt ON dt.id = d.document_type_id
             WHERE d.status = 'active' AND d.deleted_at IS NULL
               AND v.deleted_at IS NULL AND v.status = 'active'
               AND d.end_date >= ? AND d.end_date <= ?
             ORDER BY d.end_date ASC",
            [$today, date('Y-m-d', strtotime($today . ' +' . $window . ' day'))]
        );

        foreach ($documents as $document) {
            $days = days_left((string) $document['end_date']);
            if ($days === null || $days < 0) {
                continue;
            }
            if (!in_array($days, self::offsetsFor($document), true)) {
                continue; // bugün bu evrağın hatırlatma günü değil
            }
            $result['scanned']++;

            $organizationId = (int) $document['organization_id'];
            $limits = SubscriptionService::limits($organizationId);
            // İptal/bitmiş abonelikte hatırlatma gönderilmez; abonelik kaydı olmayan
            // kurumlar (ücretsiz varsayılan) hatırlatma almaya devam eder.
            if (in_array((string) $limits['status'], ['cancelled', 'expired'], true)) {
                $result['skipped']++;
                continue;
            }
            if (!self::channelAllowsEmail((string) $limits['channels'])) {
                $result['skipped']++;
                continue;
            }

            foreach (self::recipients($organizationId) as $user) {
                // Site içi bildirim: e-posta izninden ve abonelikten çıkmadan bağımsızdır.
                InboxService::reminder(
                    (int) $user['id'],
                    $document,
                    ['id' => (int) $document['vehicle_id'], 'plate' => (string) $document['plate']],
                    $days,
                    $organizationId
                );

                // E-posta yalnızca izinli, geçerli ve listeden çıkmamış adreslere gider.
                if ((int) $user['notify_email'] !== 1
                    || filter_var((string) $user['email'], FILTER_VALIDATE_EMAIL) === false
                    || MailTemplateService::isUnsubscribed((string) $user['email'])) {
                    continue;
                }

                $dedupe = sprintf('email:%s:%d:%d:%d:%s', self::TEMPLATE_KEY, (int) $user['id'], (int) $document['id'], $days, (string) $document['end_date']);
                try {
                    Database::insert('notifications', [
                        'user_id'         => (int) $user['id'],
                        'organization_id' => $organizationId,
                        'vehicle_id'      => (int) $document['vehicle_id'],
                        'document_id'     => (int) $document['id'],
                        'channel'         => 'email',
                        'template_key'    => self::TEMPLATE_KEY,
                        'recipient'       => (string) $user['email'],
                        'subject'         => (string) Settings::get('brand.title', 'Evrako') . ' — ' . t('documents.title'),
                        'status'          => 'queued',
                        'dedupe_key'      => $dedupe,
                        'attempts'        => 0,
                        'scheduled_for'   => self::scheduleAt(),
                        'created_at'      => date('Y-m-d H:i:s'),
                    ]);
                    $result['created']++;
                } catch (\Throwable $e) {
                    if (str_contains($e->getMessage(), 'Duplicate entry')) {
                        $result['duplicates']++;
                        continue;
                    }
                    Logger::error('Hatırlatma kuyruğa alınamadı: ' . $e->getMessage(), ['document' => (int) $document['id'], 'user' => (int) $user['id']]);
                    $result['errors'][] = 'Kuyruk kaydı yazılamadı (evrak #' . (int) $document['id'] . ').';
                }
            }
        }

        return $result;
    }

    /**
     * Kuyruktaki (ve zamanı gelen) bildirimleri gönderir.
     * @return array{sent:int,failed:int,skipped:int,remaining:int,error?:string|null}
     */
    public static function dispatch(?int $limit = null): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'remaining' => self::pendingCount(), 'error' => null];

        $limit = $limit ?? self::intSetting('reminders.batch', 50);
        $limit = max(1, min(500, $limit));
        $limit = min($limit, max(0, self::dailyLimit() - self::sentToday()));

        if ($limit === 0) {
            $result['error'] = 'Günlük gönderim limiti doldu (' . self::dailyLimit() . ').';
            return $result;
        }

        $mailer = new Mailer();
        if (!$mailer->isConfigured()) {
            $result['error'] = 'SMTP ayarları eksik: hatırlatma e-postaları gönderilemedi.';
            return $result;
        }

        $rows = Database::select(
            "SELECT * FROM notifications
             WHERE channel = 'email' AND status = 'queued'
               AND (scheduled_for IS NULL OR scheduled_for <= ?)
             ORDER BY COALESCE(scheduled_for, created_at) ASC, id ASC
             LIMIT " . (int) $limit,
            [date('Y-m-d H:i:s')]
        );

        foreach ($rows as $row) {
            $outcome = self::sendOne($mailer, $row);
            $result[$outcome]++;
        }

        $result['remaining'] = self::pendingCount();
        return $result;
    }

    /** Tek bildirimi gönderir; sonuç: sent|failed|skipped. */
    private static function sendOne(Mailer $mailer, array $notification): string
    {
        $id = (int) $notification['id'];
        $attempts = (int) $notification['attempts'] + 1;

        // Kampanya (reklam maili) bildirimleri evrak gerektirmez: kendi kuyruğu,
        // sayaçları ve açılma takibi CampaignService içindedir.
        if ((string) ($notification['template_key'] ?? '') === 'campaign') {
            return CampaignService::sendNotification($mailer, $notification);
        }

        $document = Database::first(
            "SELECT d.*, dt.labels_json AS type_labels
             FROM vehicle_documents d JOIN document_types dt ON dt.id = d.document_type_id
             WHERE d.id = ? LIMIT 1",
            [(int) $notification['document_id']]
        );
        if ($document === null || (string) $document['status'] !== 'active' || $document['deleted_at'] !== null) {
            Database::update('notifications', [
                'status' => 'skipped', 'attempts' => $attempts, 'error' => 'Evrak artık aktif değil.',
            ], 'id = :id', ['id' => $id]);
            return 'skipped';
        }

        if (MailTemplateService::isUnsubscribed((string) $notification['recipient'])) {
            Database::update('notifications', [
                'status' => 'skipped', 'attempts' => $attempts, 'error' => 'Alıcı e-posta listesinden çıkmış.',
            ], 'id = :id', ['id' => $id]);
            return 'skipped';
        }

        $vehicle = Database::first('SELECT id, plate, brand, model, organization_id FROM vehicles WHERE id = ? LIMIT 1', [(int) $notification['vehicle_id']]);
        $user = Database::first('SELECT id, full_name, email, locale, notify_email, status FROM users WHERE id = ? LIMIT 1', [(int) $notification['user_id']]);
        if ($vehicle === null || $user === null || (int) $user['notify_email'] !== 1 || (string) $user['status'] !== 'active') {
            Database::update('notifications', [
                'status' => 'skipped', 'attempts' => $attempts, 'error' => 'Alıcı veya araç artık uygun değil.',
            ], 'id = :id', ['id' => $id]);
            return 'skipped';
        }

        $locale = in_array((string) $user['locale'], MailTemplateService::LOCALES, true) ? (string) $user['locale'] : 'tr';
        $rendered = self::render($notification, $document, $vehicle, $user, $locale);

        $headers = [];
        $unsubscribe = MailTemplateService::unsubscribeUrl((string) $user['email']);
        $headers['List-Unsubscribe'] = '<' . MailTemplateService::oneClickUrl((string) $user['email']) . '>, <' . $unsubscribe . '>';
        $headers['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
        if ((string) Settings::get('brand.email', '') !== '') {
            $headers['Reply-To'] = (string) Settings::get('brand.email');
        }

        $send = $mailer->send((string) $user['email'], $rendered['subject'], $rendered['html'], $rendered['text'], [], $headers);

        if ($send['ok']) {
            Database::update('notifications', [
                'status'           => 'sent',
                'subject_rendered' => mb_substr($rendered['subject'], 0, 255),
                'body_html'        => $rendered['html'],
                'attempts'         => $attempts,
                'error'            => null,
                'sent_at'          => date('Y-m-d H:i:s'),
                'scheduled_for'    => null,
            ], 'id = :id', ['id' => $id]);
            return 'sent';
        }

        $maxAttempts = self::intSetting('reminders.max_attempts', 3);
        $error = mb_substr((string) ($send['error'] ?? 'Bilinmeyen gönderim hatası'), 0, 500);
        if ($attempts >= $maxAttempts) {
            Database::update('notifications', [
                'status' => 'failed', 'attempts' => $attempts, 'error' => $error, 'scheduled_for' => null,
            ], 'id = :id', ['id' => $id]);
            Logger::error('Hatırlatma gönderilemedi (kalıcı): ' . $error, ['notification' => $id, 'to' => (string) $user['email']]);
            return 'failed';
        }

        // Geri çekilerek yeniden dene: 15dk, 30dk, 60dk...
        $delayMinutes = 15 * (2 ** ($attempts - 1));
        Database::update('notifications', [
            'status'        => 'queued',
            'attempts'      => $attempts,
            'error'         => $error,
            'scheduled_for' => date('Y-m-d H:i:s', time() + $delayMinutes * 60),
        ], 'id = :id', ['id' => $id]);
        Logger::warning('Hatırlatma yeniden denenecek: ' . $error, ['notification' => $id, 'attempt' => $attempts]);
        return 'skipped';
    }

    /**
     * Bildirimi şablona göre işler (alıcının dilinde).
     * @return array{subject:string,html:string,text:string}
     */
    public static function render(array $notification, array $document, array $vehicle, array $user, string $locale): array
    {
        $previous = Translator::locale();
        Translator::setLocale($locale);

        $days = days_left((string) $document['end_date']) ?? 0;
        $light = VehicleService::light($days);
        $colors = MailTemplateService::statusColors($light);
        $brand = (string) Settings::get('brand.title', 'Evrako');

        $vars = [
            'ad'              => (string) $user['full_name'],
            'plaka'           => (string) $vehicle['plate'],
            'arac'            => trim((string) ($vehicle['brand'] ?? '') . ' ' . (string) ($vehicle['model'] ?? '')),
            'evrak'           => label((string) ($document['type_labels'] ?? ''), $locale) ?: t('documents.title'),
            'bitis'           => format_date((string) $document['end_date']),
            'kalan_gun'       => MailTemplateService::durationPhrase($days, $locale),
            'kalan_gun_metin' => $days > 0
                ? t('documents.days_left', ['days' => $days])
                : ($days === 0 ? t('documents.today') : t('documents.days_over', ['days' => abs($days)])),
            'durum_etiket'    => t('vehicles.light_' . $light),
            'durum_renk'      => $colors['bar'],
            'durum_arka'      => $colors['bg'],
            'durum_metin'     => $colors['text'],
            'link'            => url('/panel/araclar/' . (int) $vehicle['id']),
            'marka_adi'       => $brand,
            'logo_url'        => MailTemplateService::logoUrl(),
            'site_url'        => url('/'),
            'sirket'          => (string) Settings::get('brand.company', $brand),
            'adres'           => trim((string) Settings::get('brand.address', '') . ' ' . (string) Settings::get('brand.city', '') . ' ' . (string) Settings::get('brand.country', '')),
            'yil'             => date('Y'),
            'abonelikten_cik' => MailTemplateService::unsubscribeUrl((string) $user['email']),
        ];

        $rendered = MailTemplateService::render((string) ($notification['template_key'] ?: self::TEMPLATE_KEY), $locale, $vars);
        Translator::setLocale($previous);

        if (!$rendered['ok']) {
            return ['subject' => (string) ($notification['subject'] ?? ''), 'html' => '', 'text' => ''];
        }

        // Açılma takibi (1x1 saydam piksel).
        $pixel = '<img src="' . htmlspecialchars(self::openPixelUrl((int) $notification['id']), ENT_QUOTES) . '" width="1" height="1" alt="" style="display:block;border:0;outline:none;">';
        $html = str_contains($rendered['html'], '</body>')
            ? str_replace('</body>', $pixel . "\n</body>", $rendered['html'])
            : $rendered['html'] . $pixel;

        return ['subject' => $rendered['subject'], 'html' => $html, 'text' => $rendered['text']];
    }

    /** Açılma pikseli adresi (imzalı). */
    public static function openPixelUrl(int $notificationId): string
    {
        return url('/mail-piksel/' . $notificationId . '/' . self::openToken($notificationId) . '.gif');
    }

    public static function openToken(int $notificationId): string
    {
        return substr(hash_hmac('sha256', 'open:' . $notificationId, (string) \App\Core\Config::get('app.key', 'evrako')), 0, 40);
    }

    /* ---------------- Yönetim eylemleri ---------------- */

    /** Başarısız bildirimi yeniden kuyruğa alır. */
    public static function requeue(int $notificationId): array
    {
        $row = Database::first('SELECT * FROM notifications WHERE id = ? LIMIT 1', [$notificationId]);
        if ($row === null) {
            return ['ok' => false, 'error' => 'Bildirim bulunamadı.'];
        }
        if ((string) $row['status'] === 'sent') {
            return ['ok' => false, 'error' => 'Bu bildirim zaten gönderildi.'];
        }
        Database::update('notifications', [
            'status' => 'queued', 'attempts' => 0, 'error' => null, 'scheduled_for' => null,
        ], 'id = :id', ['id' => $notificationId]);
        return ['ok' => true];
    }

    /** Başarısızların tümünü (en fazla $limit) yeniden kuyruğa alır. */
    public static function requeueFailed(int $limit = 100): int
    {
        $rows = Database::select(
            "SELECT id FROM notifications WHERE status = 'failed' ORDER BY id ASC LIMIT " . max(1, min(500, $limit))
        );
        foreach ($rows as $row) {
            self::requeue((int) $row['id']);
        }
        return count($rows);
    }

    /** Kuyruktaki bildirimi iptal eder. */
    public static function cancel(int $notificationId): array
    {
        $row = Database::first('SELECT status FROM notifications WHERE id = ? LIMIT 1', [$notificationId]);
        if ($row === null) {
            return ['ok' => false, 'error' => 'Bildirim bulunamadı.'];
        }
        Database::update('notifications', ['status' => 'cancelled'], 'id = :id', ['id' => $notificationId]);
        return ['ok' => true];
    }

    /* ---------------- Sorgular / yardımcılar ---------------- */

    /**
     * Kurumun aktif üyeleri. E-posta izni/abonelikten çıkma kontrolü çağıran tarafta
     * yapılır; site içi bildirimler bu izinlerden bağımsız gönderilir.
     * @return array<int,array>
     */
    public static function recipients(int $organizationId): array
    {
        return Database::select(
            "SELECT u.id, u.full_name, u.email, u.locale, u.notify_email
             FROM organization_members m
             JOIN users u ON u.id = m.user_id
             WHERE m.organization_id = ? AND m.status = 'active'
               AND u.status = 'active' AND u.deleted_at IS NULL
             ORDER BY FIELD(m.role, 'owner', 'manager', 'viewer'), u.id ASC",
            [$organizationId]
        );
    }

    /** Evrağın hatırlatma günleri: evrak > tür > site varsayılanı. */
    public static function offsetsFor(array $document, ?array $type = null): array
    {
        foreach ([(string) ($document['reminder_offsets'] ?? ''), (string) ($document['default_offsets'] ?? ''), (string) Settings::get('reminders.offsets', (string) Settings::get('site.default_reminder_offsets', '7,3,1,0'))] as $candidate) {
            $offsets = self::parseOffsets($candidate);
            if ($offsets !== []) {
                return $offsets;
            }
        }
        return [7, 3, 1, 0];
    }

    /** @return array<int,int> */
    public static function parseOffsets(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }
        $out = [];
        foreach (explode(',', $value) as $part) {
            $part = trim($part);
            if ($part === '' || !ctype_digit($part)) {
                continue;
            }
            $days = (int) $part;
            if ($days <= 365) {
                $out[] = $days;
            }
        }
        rsort($out);
        return array_values(array_unique($out));
    }

    public static function enabled(): bool
    {
        return (string) Settings::get('reminders.enabled', '1') !== '0';
    }

    public static function dailyLimit(): int
    {
        $limit = self::intSetting('reminders.daily_limit', 0);
        if ($limit <= 0) {
            $limit = self::intSetting('smtp.daily_limit', 500);
        }
        return max(1, $limit);
    }

    public static function sentToday(): int
    {
        return (int) Database::scalar('SELECT COUNT(*) FROM notifications WHERE status = \'sent\' AND sent_at >= ?', [date('Y-m-d') . ' 00:00:00']);
    }

    public static function pendingCount(): int
    {
        return (int) Database::scalar("SELECT COUNT(*) FROM notifications WHERE status = 'queued'");
    }

    /** @return array<string,int> */
    public static function stats(): array
    {
        $counts = ['queued' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'cancelled' => 0, 'opened' => 0, 'today' => 0, 'today_failed' => 0];
        try {
            foreach (Database::select('SELECT status, COUNT(*) AS total FROM notifications GROUP BY status') as $row) {
                $counts[(string) $row['status']] = (int) $row['total'];
            }
            $counts['opened'] = (int) Database::scalar('SELECT COUNT(*) FROM notifications WHERE opened_at IS NOT NULL');
            $counts['today'] = self::sentToday();
            $counts['today_failed'] = (int) Database::scalar("SELECT COUNT(*) FROM notifications WHERE status = 'failed' AND created_at >= ?", [date('Y-m-d') . ' 00:00:00']);
        } catch (\Throwable) {
        }
        return $counts;
    }

    /** Hatırlatma ayarları eksikse varsayılanla ekler (kurulum sonrası güncellemeler için). */
    public static function ensureSettings(): void
    {
        foreach (self::SETTING_DEFAULTS as $key => $value) {
            if (Settings::get($key, null) === null) {
                Settings::set($key, $value, 'reminders');
            }
        }
    }

    /** Kuyruk kayıtlarının gönderim saati (bugün için ayarlanan saat geçtiyse hemen). */
    private static function scheduleAt(): ?string
    {
        $hour = max(0, min(23, self::intSetting('reminders.hour', 8)));
        $target = strtotime(date('Y-m-d') . ' ' . str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':00:00');
        if ($target === false || $target <= time()) {
            return null; // hemen gönderilebilir
        }
        return date('Y-m-d H:i:s', $target);
    }

    /**
     * Tarama penceresi: en büyük hatırlatma günü + 1.
     * Hem site/tür varsayılanları hem de evraklara özel hatırlatma günleri dikkate alınır
     * (ör. bir evrakta "30,7,1" tanımlıysa pencere 31 gün olmalı).
     */
    private static function windowDays(): int
    {
        $offsets = self::parseOffsets((string) Settings::get('reminders.offsets', '7,3,1,0'));
        try {
            foreach (Database::select('SELECT default_offsets FROM document_types WHERE is_active = 1') as $row) {
                foreach (self::parseOffsets((string) $row['default_offsets']) as $days) {
                    $offsets[] = $days;
                }
            }
            foreach (Database::select("SELECT DISTINCT reminder_offsets FROM vehicle_documents WHERE reminder_offsets IS NOT NULL AND reminder_offsets <> '' AND status = 'active' AND deleted_at IS NULL") as $row) {
                foreach (self::parseOffsets((string) $row['reminder_offsets']) as $days) {
                    $offsets[] = $days;
                }
            }
        } catch (\Throwable) {
        }
        return min(365, max([7, 0] + $offsets)) + 1;
    }

    private static function channelAllowsEmail(string $channels): bool
    {
        if (trim($channels) === '') {
            return true;
        }
        return in_array('email', array_map('trim', explode(',', $channels)), true);
    }

    private static function intSetting(string $key, int $default): int
    {
        $value = Settings::get($key, null);
        return $value === null || $value === '' ? $default : (int) $value;
    }

    /** @return resource|null Dosya kilidi (alınamazsa null). */
    private static function lock()
    {
        $dir = BASE_PATH . '/storage/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $handle = @fopen($dir . '/reminders.lock', 'c');
        if ($handle === false) {
            return false;
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return null;
        }
        return $handle;
    }

    /** @param resource|false|null $handle */
    private static function unlock($handle): void
    {
        if (is_resource($handle)) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
