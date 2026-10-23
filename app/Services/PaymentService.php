<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Mailer;
use App\Core\Settings;

/**
 * Havale/EFT (bank_transfer) ile ödeme akışı.
 *
 * Akış: müşteri tutar seçer -> kendisine bir ÖDEME KODU (reference) verilir ->
 * parayı IBAN'a açıklamada kod ile yatırır -> panelden "ödemeyi yaptım" bildirir ->
 * yönetici onaylar -> kredi yüklenir veya abonelik başlar.
 *
 * Ödeme sayfası sağlayıcıları (iyzico/PayTR) sonradan ekleneceği için
 * payments.gateway_token / gateway_url kolonları şimdiden hazırdır; akış aynı
 * "intent -> paid -> apply" hattından geçer.
 */
final class PaymentService
{
    public const KINDS = ['credit', 'subscription'];

    /** Ödeme kodu üretir: HV-2026-4F2A9C */
    private static function generateReference(): string
    {
        for ($i = 0; $i < 8; $i++) {
            $code = 'HV-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
            if (!Database::scalar('SELECT id FROM payments WHERE reference = ? LIMIT 1', [$code])) {
                return $code;
            }
        }
        return 'HV-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(5)));
    }

    public static function currency(): string
    {
        return (string) Settings::get('payment.currency', 'TRY');
    }

    public static function bankEnabled(): bool
    {
        return (string) Settings::get('payment.bank_enabled', '1') === '1';
    }

    public static function minTopup(): float
    {
        return max(1.0, round((float) Settings::get('payment.min_topup', 100), 2));
    }

    /** @return array<int,float> */
    public static function presets(): array
    {
        $raw = Settings::get('payment.topup_presets', []);
        if (is_string($raw)) {
            $raw = array_filter(array_map('trim', explode(',', $raw)));
        }
        $out = [];
        foreach ((array) $raw as $value) {
            $amount = round((float) $value, 2);
            if ($amount > 0) {
                $out[] = $amount;
            }
        }
        return $out === [] ? [250.0, 500.0, 1000.0] : $out;
    }

    /**
     * Havale yapılacak banka hesapları.
     * @return array<int,array{bank:string,owner:string,iban:string,branch:string,note:string}>
     */
    public static function bankAccounts(): array
    {
        $accounts = [];
        foreach ((array) Settings::get('payment.bank_accounts', []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $iban = preg_replace('/\s+/', '', (string) ($row['iban'] ?? '')) ?? '';
            if ($iban === '' && trim((string) ($row['account'] ?? '')) === '') {
                continue;
            }
            $accounts[] = [
                'bank'   => trim((string) ($row['bank'] ?? '')),
                'owner'  => trim((string) ($row['owner'] ?? '')),
                'iban'   => self::formatIban($iban),
                'account'=> trim((string) ($row['account'] ?? '')),
                'branch' => trim((string) ($row['branch'] ?? '')),
                'note'   => trim((string) ($row['note'] ?? '')),
            ];
        }

        // Eski tekil ayarlarla geriye dönük uyumluluk
        if ($accounts === []) {
            $iban = (string) Settings::get('payment.bank_iban', '');
            if ($iban !== '') {
                $accounts[] = [
                    'bank' => '', 'owner' => (string) Settings::get('payment.bank_owner', ''),
                    'iban' => self::formatIban(preg_replace('/\s+/', '', $iban) ?? ''), 'account' => '', 'branch' => '', 'note' => '',
                ];
            }
        }
        return $accounts;
    }

    private static function formatIban(string $iban): string
    {
        if ($iban === '') {
            return '';
        }
        return trim(chunk_split($iban, 4, ' '));
    }

    /**
     * Yeni ödeme niyeti (intent) oluşturur.
     * @param array<string,mixed> $meta
     * @return array{ok:bool,error?:string,payment_id?:int,reference?:string}
     */
    public static function createIntent(int $organizationId, int $userId, string $kind, float $amount, ?int $planId = null, array $meta = []): array
    {
        if (!in_array($kind, self::KINDS, true)) {
            return ['ok' => false, 'error' => 'Geçersiz ödeme türü.'];
        }
        if ((int) Database::scalar('SELECT COUNT(*) FROM organizations WHERE id = ?', [$organizationId]) === 0) {
            return ['ok' => false, 'error' => 'Kurum bulunamadı.'];
        }

        if ($kind === 'subscription') {
            // Abonelikte tutar planın SEÇİLEN DÖNEM (aylık/yıllık) fiyatıdır.
            $plan = $planId === null ? null : SubscriptionService::plan($planId);
            if ($plan === null || (int) $plan['is_active'] !== 1) {
                return ['ok' => false, 'error' => 'Seçilen plan bulunamadı.'];
            }
            $interval = SubscriptionService::intervalFor($plan, isset($meta['interval']) ? (string) $meta['interval'] : null);
            $amount = SubscriptionService::priceFor($plan, $interval);
            if ($amount <= 0) {
                return ['ok' => false, 'error' => 'Bu plan ücretsiz; ödeme gerekmez.'];
            }
            $meta['interval'] = $interval;
        } else {
            $amount = round($amount, 2);
            if ($amount <= 0) {
                return ['ok' => false, 'error' => 'Tutar sıfırdan büyük olmalıdır.'];
            }
            if ($amount < self::minTopup()) {
                return ['ok' => false, 'error' => sprintf('En az %.2f %s kredi yükleyebilirsiniz.', self::minTopup(), self::currency())];
            }
        }

        if ($amount > CreditService::MAX_SINGLE_AMOUNT) {
            return ['ok' => false, 'error' => 'Tek ödemede üst sınır aşıldı.'];
        }

        $now = time();
        $ttl = max(1, (int) Settings::get('payment.intent_ttl_hours', 72));
        $reference = self::generateReference();

        try {
            $paymentId = Database::insert('payments', [
                'organization_id' => $organizationId,
                'subscription_id' => null,
                'amount'          => $amount,
                'currency'        => self::currency(),
                'status'          => 'pending',
                'provider'        => 'bank_transfer',
                'method'          => 'bank_transfer',
                'kind'            => $kind,
                'reference'       => $reference,
                'user_id'         => $userId,
                'plan_id'         => $kind === 'subscription' ? $planId : null,
                'billing_interval'=> $kind === 'subscription' ? (string) ($meta['interval'] ?? 'month') : null,
                'meta_json'       => json_encode($meta, JSON_UNESCAPED_UNICODE),
                'expires_at'      => date('Y-m-d H:i:s', $now + $ttl * 3600),
                'notes'           => $kind === 'credit' ? 'Kredi yükleme talebi (havale)' : 'Abonelik ödemesi (havale)',
                'created_at'      => date('Y-m-d H:i:s', $now),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Odeme niyeti olusturulamadi: ' . $e->getMessage(), ['org' => $organizationId]);
            return ['ok' => false, 'error' => 'Ödeme kaydı oluşturulamadı.'];
        }

        self::event($paymentId, 'intent_created', ['kind' => $kind, 'amount' => $amount, 'plan_id' => $planId]);
        Audit::log('payment.intent_created', 'payment', $paymentId, ['kind' => $kind, 'amount' => $amount]);

        // Müşteriye panel bildirimi: havale talimatı hazır.
        InboxService::create([
            'user_id'    => $userId,
            'kind'       => 'system',
            'title'      => $kind === 'subscription' ? 'Paket ödeme talimatınız hazır' : 'Kredi yükleme talimatınız hazır',
            'body'       => number_format($amount, 2, ',', '.') . ' ' . self::currency() . ' tutarındaki ödeme için havale bilgileri oluşturuldu. Ödeme kodu: ' . $reference,
            'link_url'   => '/panel/odeme/talep/' . $paymentId,
            'link_label' => 'Havale bilgilerini gör',
            'dedupe_key' => 'payment:intent:' . $paymentId,
        ]);
        self::notifyAdmins(
            'Yeni ' . ($kind === 'subscription' ? 'paket' : 'kredi') . ' ödeme talebi',
            ['Tutar: ' . number_format($amount, 2, ',', '.') . ' ' . self::currency(), 'Ödeme kodu: ' . $reference, 'Durum: havale bekliyor'],
            '/yonetim/odemeler'
        );

        return ['ok' => true, 'payment_id' => $paymentId, 'reference' => $reference];
    }

    /** Müşteri "havaleyi yaptım" bildirimi gönderir. */
    public static function declareTransfer(int $paymentId, int $userId, array $declaration): array
    {
        $payment = Database::first('SELECT * FROM payments WHERE id = ? LIMIT 1', [$paymentId]);
        if ($payment === null || (int) $payment['user_id'] !== $userId) {
            return ['ok' => false, 'error' => 'Ödeme kaydı bulunamadı.'];
        }
        if ((string) $payment['status'] !== 'pending') {
            return ['ok' => false, 'error' => 'Bu ödeme için bildirim gönderilemez (durum: ' . (string) $payment['status'] . ').'];
        }

        $meta = json_decode((string) ($payment['meta_json'] ?? ''), true);
        $meta = is_array($meta) ? $meta : [];
        $meta['declared'] = [
            'at'       => date('Y-m-d H:i:s'),
            'sender'   => mb_substr((string) ($declaration['sender'] ?? ''), 0, 120),
            'bank'     => mb_substr((string) ($declaration['bank'] ?? ''), 0, 120),
            'date'     => mb_substr((string) ($declaration['date'] ?? ''), 0, 20),
            'note'     => mb_substr((string) ($declaration['note'] ?? ''), 0, 400),
            'user_id'  => $userId,
        ];

        Database::run('UPDATE payments SET meta_json = ? WHERE id = ?', [json_encode($meta, JSON_UNESCAPED_UNICODE), $paymentId]);

        self::event($paymentId, 'transfer_declared', $meta['declared'], $userId);
        Audit::log('payment.transfer_declared', 'payment', $paymentId);

        $sender = trim((string) ($declaration['sender'] ?? ''));
        self::notifyAdmins(
            'Havale bildirimi — onay bekliyor',
            array_values(array_filter([
                'Ödeme kodu: ' . (string) $payment['reference'],
                'Tutar: ' . number_format((float) $payment['amount'], 2, ',', '.') . ' ' . (string) $payment['currency'],
                $sender !== '' ? 'Gönderen: ' . $sender : null,
                !empty($declaration['bank']) ? 'Banka: ' . (string) $declaration['bank'] : null,
            ])),
            '/yonetim/odemeler'
        );

        return ['ok' => true];
    }

    /**
     * Yönetici ödemeyi onaylar: kredi yüklenir veya abonelik başlar/uzar.
     * Idempotent: ikinci çağrı çift işlem yapmaz.
     * @return array{ok:bool,error?:string,balance?:float,subscription_id?:int,period_end?:string}
     */
    public static function approve(int $paymentId, ?float $amount = null, ?string $note = null): array
    {
        $payment = Database::first('SELECT * FROM payments WHERE id = ? LIMIT 1', [$paymentId]);
        if ($payment === null) {
            return ['ok' => false, 'error' => 'Ödeme bulunamadı.'];
        }
        if ((string) $payment['status'] === 'paid' && $payment['applied_at'] !== null) {
            return ['ok' => true, 'already' => true];
        }
        if (!in_array((string) $payment['status'], ['pending', 'failed'], true)) {
            return ['ok' => false, 'error' => 'Bu ödeme onaylanamaz (durum: ' . (string) $payment['status'] . ').'];
        }

        $organizationId = (int) $payment['organization_id'];
        $kind = (string) $payment['kind'];
        $amount = round($amount ?? (float) $payment['amount'], 2);
        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'Geçersiz tutar.'];
        }

        // Önce atomik olarak sahiplen: pending -> paid (çift onay koruması)
        $claimed = Database::run(
            "UPDATE payments SET status = 'paid', paid_at = ?, approved_by = ?, applied_at = ?, amount = ?
             WHERE id = ? AND status IN ('pending','failed') AND applied_at IS NULL",
            [date('Y-m-d H:i:s'), Auth::id(), date('Y-m-d H:i:s'), $amount, $paymentId]
        )->rowCount();
        if ($claimed === 0) {
            return ['ok' => true, 'already' => true];
        }

        $result = ['ok' => true, 'amount' => $amount];

        if ($kind === 'credit') {
            $credit = CreditService::add(
                $organizationId, $amount, 'bank_transfer',
                $note ?? ('Havale onayı — ' . (string) $payment['reference']),
                (string) $payment['reference'], (int) ($payment['user_id'] ?? 0) ?: null
            );
            if (!$credit['ok']) {
                // geri al: tekrar pending
                Database::run("UPDATE payments SET status = 'failed', applied_at = NULL, approved_by = NULL WHERE id = ?", [$paymentId]);
                return ['ok' => false, 'error' => (string) $credit['error']];
            }
            $result['balance'] = (float) $credit['balance'];
        } elseif ($kind === 'subscription') {
            $planId = (int) ($payment['plan_id'] ?? 0);
            $plan = $planId > 0 ? SubscriptionService::plan($planId) : null;
            if ($plan === null) {
                Database::run("UPDATE payments SET status = 'failed', applied_at = NULL, approved_by = NULL WHERE id = ?", [$paymentId]);
                return ['ok' => false, 'error' => 'Plana ulaşılamadı.'];
            }

            $current = SubscriptionService::current($organizationId);
            $interval = in_array((string) ($payment['billing_interval'] ?? ''), SubscriptionService::INTERVALS, true)
                ? (string) $payment['billing_interval']
                : SubscriptionService::intervalFor($plan);
            $days = SubscriptionService::daysForInterval($interval);
            $periodStart = date('Y-m-d H:i:s');
            $periodEnd = '';

            if ($current !== null && (int) $current['plan_id'] === $planId && in_array((string) $current['status'], ['active', 'trialing', 'past_due'], true)) {
                $extend = SubscriptionService::extend((int) $current['id'], $days, false, 'Havale ile ödendi — ' . (string) $payment['reference']);
                if (!$extend['ok']) {
                    Database::run("UPDATE payments SET status = 'failed', applied_at = NULL, approved_by = NULL WHERE id = ?", [$paymentId]);
                    return ['ok' => false, 'error' => (string) $extend['error']];
                }
                $subscriptionId = (int) $current['id'];
                $periodEnd = (string) $extend['period_end'];
            } else {
                $assign = SubscriptionService::assign($organizationId, $planId, [
                    'mark_paid' => false,
                    'interval'  => $interval,
                    'provider'  => 'bank_transfer',
                    'note'      => 'Havale ile ödendi — ' . (string) $payment['reference'],
                ]);
                if (!$assign['ok']) {
                    Database::run("UPDATE payments SET status = 'failed', applied_at = NULL, approved_by = NULL WHERE id = ?", [$paymentId]);
                    return ['ok' => false, 'error' => (string) $assign['error']];
                }
                $subscriptionId = (int) $assign['subscription_id'];
                $periodEnd = (string) $assign['period_end'];
            }

            Database::run(
                'UPDATE payments SET subscription_id = ?, provider_txn_id = ?, period_start = ?, period_end = ? WHERE id = ?',
                [$subscriptionId, (string) $payment['reference'], $periodStart, $periodEnd, $paymentId]
            );
            $result['subscription_id'] = $subscriptionId;
            $result['period_end'] = $periodEnd;
        } else {
            Database::run('UPDATE payments SET applied_at = NULL WHERE id = ?', [$paymentId]);
        }

        self::event($paymentId, 'approved', ['amount' => $amount, 'kind' => $kind, 'note' => $note]);
        Audit::log('payment.approved', 'payment', $paymentId, ['amount' => $amount, 'kind' => $kind]);
        return $result;
    }

    /** Yönetici ödemeyi reddeder. */
    public static function reject(int $paymentId, string $reason = ''): array
    {
        $payment = Database::first('SELECT * FROM payments WHERE id = ? LIMIT 1', [$paymentId]);
        if ($payment === null) {
            return ['ok' => false, 'error' => 'Ödeme bulunamadı.'];
        }
        if ((string) $payment['status'] === 'paid') {
            return ['ok' => false, 'error' => 'Onaylanmış ödeme reddedilemez. Önce iade işlemi yapın.'];
        }

        Database::run(
            "UPDATE payments SET status = 'failed', notes = ? WHERE id = ?",
            [mb_substr('Reddedildi: ' . $reason, 0, 250), $paymentId]
        );
        self::event($paymentId, 'rejected', ['reason' => $reason]);
        Audit::log('payment.rejected', 'payment', $paymentId, ['reason' => $reason]);
        return ['ok' => true];
    }

    /** Müşteri kendi bekleyen talebini iptal eder. */
    public static function cancel(int $paymentId, int $userId): array
    {
        $payment = Database::first('SELECT * FROM payments WHERE id = ? LIMIT 1', [$paymentId]);
        if ($payment === null || (int) $payment['user_id'] !== $userId) {
            return ['ok' => false, 'error' => 'Ödeme bulunamadı.'];
        }
        if ((string) $payment['status'] !== 'pending') {
            return ['ok' => false, 'error' => 'Sadece bekleyen talepler iptal edilebilir.'];
        }
        Database::run("UPDATE payments SET status = 'failed', notes = 'Müşteri tarafından iptal edildi.' WHERE id = ?", [$paymentId]);
        self::event($paymentId, 'cancelled', [], $userId);
        Audit::log('payment.cancelled', 'payment', $paymentId);
        return ['ok' => true];
    }

    /** @return array<int,array> Kurumun ödeme kayıtları (üye paneli). */
    public static function forOrganization(int $organizationId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return Database::select(
            "SELECT p.*, pl.name AS plan_name FROM payments p
             LEFT JOIN plans pl ON pl.id = p.plan_id
             WHERE p.organization_id = ?
             ORDER BY p.id DESC LIMIT {$limit}",
            [$organizationId]
        );
    }

    /** @return array<int,array> Yönetici paneli: onay bekleyen havaleler. */
    public static function pendingDeclarations(int $limit = 100): array
    {
        $limit = max(1, min(300, $limit));
        return Database::select(
            "SELECT p.*, o.name AS organization_name, u.full_name AS user_name, u.email AS user_email, pl.name AS plan_name
             FROM payments p
             LEFT JOIN organizations o ON o.id = p.organization_id
             LEFT JOIN users u ON u.id = p.user_id
             LEFT JOIN plans pl ON pl.id = p.plan_id
             WHERE p.status = 'pending' AND p.meta_json LIKE '%\"declared\"%'
             ORDER BY p.id ASC LIMIT {$limit}"
        );
    }

    /** Süresi geçmiş ve bildirilmemiş talepleri kapatır (cron). */
    public static function expireStale(): int
    {
        return Database::run(
            "UPDATE payments SET status = 'failed', notes = 'Süre aşıldı — ödeme yapılmadı.'
             WHERE status = 'pending' AND expires_at IS NOT NULL AND expires_at < ?
               AND (meta_json IS NULL OR meta_json NOT LIKE '%\"declared\"%')",
            [date('Y-m-d H:i:s')]
        )->rowCount();
    }

    /** Yöneticilere e-posta ile haber verir (SMTP yoksa sessizce geçer). */
    private static function notifyAdmins(string $subject, array $lines, string $path): void
    {
        try {
            $admins = Database::select(
                "SELECT email FROM users WHERE role IN ('admin','editor') AND status = 'active' AND deleted_at IS NULL"
            );
            if ($admins === []) {
                return;
            }
            $mailer = new Mailer(['timeout' => 5]);
            if (!$mailer->isConfigured()) {
                return;
            }
            $brand = (string) Settings::get('brand.title', 'Evrako');
            $link = url($path);
            $html = '<p>' . e($subject) . '</p><ul><li>' . implode('</li><li>', array_map('e', $lines)) . '</li></ul>'
                . '<p><a href="' . e($link) . '">Yönetim panelinde aç</a></p>';
            foreach ($admins as $admin) {
                if (!filter_var((string) $admin['email'], FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                $mailer->send((string) $admin['email'], $brand . ' · ' . $subject, $html, strip_tags(str_replace(['</li>', '</p>'], "\n", $html)));
            }
        } catch (\Throwable $e) {
            Logger::warning('Ödeme yönetici bildirimi gönderilemedi: ' . $e->getMessage());
        }
    }

    /** @param array<string,mixed>|null $payload */
    public static function event(int $paymentId, string $event, ?array $payload = null, ?int $actorUserId = null): void
    {
        try {
            Database::insert('payment_events', [
                'payment_id'    => $paymentId,
                'gateway'       => 'bank_transfer',
                'event'         => mb_substr($event, 0, 60),
                'payload'       => $payload === null ? null : mb_substr((string) json_encode($payload, JSON_UNESCAPED_UNICODE), 0, 4000),
                'ip'            => (new \App\Core\Request())->ip(),
                'actor_user_id' => $actorUserId ?? Auth::id(),
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::warning('Odeme olayi yazilamadi: ' . $e->getMessage());
        }
    }

    /** @return array{paid:float,pending:float,count:int} */
    public static function summary(int $organizationId): array
    {
        $row = Database::first(
            "SELECT COALESCE(SUM(CASE WHEN status='paid' THEN amount END),0) AS paid,
                    COALESCE(SUM(CASE WHEN status='pending' THEN amount END),0) AS pending,
                    COUNT(*) AS cnt
             FROM payments WHERE organization_id = ?",
            [$organizationId]
        );
        return [
            'paid'    => round((float) ($row['paid'] ?? 0), 2),
            'pending' => round((float) ($row['pending'] ?? 0), 2),
            'count'   => (int) ($row['cnt'] ?? 0),
        ];
    }
}
