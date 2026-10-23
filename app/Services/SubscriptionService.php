<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Settings;

/**
 * Abonelik yaşam döngüsü: plan atama, süre uzatma, iptal, plan limitleri,
 * kredi ile ödeme ve manuel ödeme kaydı.
 */
final class SubscriptionService
{
    public const STATUSES = ['trialing', 'active', 'past_due', 'cancelled', 'expired'];
    public const INTERVALS = ['month', 'year', 'lifetime'];

    /** Bir dönemin gün karşılığı (abonelik dönemleri sabit uzunlukta). */
    public static function daysForInterval(string $interval): int
    {
        return match ($interval) {
            'year'     => 365,
            'lifetime' => 36500,
            default    => 30,
        };
    }

    /** Yıllık fiyat tanımlı mı? (tanımlı değilse plan yalnız aylık satılır) */
    private static function hasYearPrice(array $plan): bool
    {
        return (float) ($plan['price_year'] ?? 0) > 0;
    }

    /**
     * Geçerli dönem: planın ömür boyu planı olup olmadığına ve yıllık fiyatın
     * tanımlı olmasına göre istenen dönemi doğrular.
     */
    public static function intervalFor(array $plan, ?string $interval = null): string
    {
        $legacy = (string) ($plan['billing_interval'] ?? 'month');
        if ($legacy === 'lifetime') {
            return 'lifetime';
        }

        $wanted = in_array((string) $interval, self::INTERVALS, true) ? (string) $interval : '';
        if ($wanted === 'year') {
            return self::hasYearPrice($plan) ? 'year' : 'month';
        }
        if ($wanted === 'month') {
            return 'month';
        }
        if ($legacy === 'year' && self::hasYearPrice($plan)) {
            return 'year';
        }
        return 'month';
    }

    /** Planın seçilen dönemdeki fiyatı (0 = ücretsiz). */
    public static function priceFor(array $plan, ?string $interval = null): float
    {
        $interval = self::intervalFor($plan, $interval);
        if ($interval === 'lifetime') {
            return round((float) ($plan['price'] ?? 0), 2);
        }
        if ($interval === 'year') {
            $year = (float) ($plan['price_year'] ?? 0);
            return round($year > 0 ? $year : (float) ($plan['price'] ?? 0) * 12, 2);
        }

        $month = (float) ($plan['price_month'] ?? 0);
        if ($month <= 0) {
            $month = (float) ($plan['price'] ?? 0);
        }
        return round($month, 2);
    }

    /**
     * Planın satılabildiği dönemler ve fiyatları (fiyatı 0 olan dönem satılmaz).
     * @return array<string,float>
     */
    public static function intervals(array $plan): array
    {
        if ((string) ($plan['billing_interval'] ?? '') === 'lifetime') {
            $price = round((float) ($plan['price'] ?? 0), 2);
            return $price > 0 ? ['lifetime' => $price] : [];
        }

        $out = [];
        $month = self::priceFor($plan, 'month');
        if ($month > 0) {
            $out['month'] = $month;
        }
        if (self::hasYearPrice($plan)) {
            $out['year'] = self::priceFor($plan, 'year');
        }
        return $out;
    }

    /** Yıllık ödemenin aylık ödemeye göre yüzde tasarrufu (0 = avantaj yok). */
    public static function yearlySavings(array $plan): int
    {
        $month = self::priceFor($plan, 'month');
        $year = self::priceFor($plan, 'year');
        if ($month <= 0 || !self::hasYearPrice($plan) || $year >= $month * 12) {
            return 0;
        }
        return (int) round((1 - $year / ($month * 12)) * 100);
    }

    public static function plan(int $planId): ?array
    {
        return Database::first('SELECT * FROM plans WHERE id = ? LIMIT 1', [$planId]);
    }

    /** @return array<int,array> */
    public static function plans(bool $onlyActive = true): array
    {
        $sql = 'SELECT * FROM plans' . ($onlyActive ? ' WHERE is_active = 1' : '')
            . ' ORDER BY sort_order ASC, COALESCE(price_month, price) ASC';
        return Database::select($sql);
    }

    /** Kurumun yürürlükteki aboneliği (plan bilgisiyle). */
    public static function current(int $organizationId): ?array
    {
        return Database::first(
            "SELECT s.*, p.`key` AS plan_key, p.name AS plan_name, p.price AS plan_price, p.currency AS plan_currency,
                    p.price_month, p.price_year, p.max_vehicles, p.max_users, p.max_documents_per_vehicle,
                    p.channels, p.features_json, p.billing_interval AS plan_interval
             FROM subscriptions s
             JOIN plans p ON p.id = s.plan_id
             WHERE s.organization_id = ?
             ORDER BY FIELD(s.status, 'active', 'trialing', 'past_due', 'cancelled', 'expired'), s.id DESC
             LIMIT 1",
            [$organizationId]
        );
    }

    /** @return array<int,array> */
    public static function history(int $organizationId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return Database::select(
            "SELECT s.*, p.name AS plan_name, p.price AS plan_price, u.full_name AS assigned_by_name
             FROM subscriptions s
             JOIN plans p ON p.id = s.plan_id
             LEFT JOIN users u ON u.id = s.assigned_by
             WHERE s.organization_id = ?
             ORDER BY s.id DESC LIMIT {$limit}",
            [$organizationId]
        );
    }

    private static function periodDays(array $plan, ?int $overrideDays = null, ?string $interval = null): int
    {
        if ($overrideDays !== null && $overrideDays > 0) {
            return $overrideDays;
        }
        return self::daysForInterval(self::intervalFor($plan, $interval));
    }

    /**
     * Plana abone yapar / aboneliği değiştirir.
     * @param array{days?:int,trial_days?:int,note?:string,mark_paid?:bool,provider?:string,status?:string} $options
     * @return array{ok:bool,error?:string,subscription_id?:int,period_end?:string}
     */
    public static function assign(int $organizationId, int $planId, array $options = []): array
    {
        $plan = self::plan($planId);
        if ($plan === null) {
            return ['ok' => false, 'error' => 'Plan bulunamadı.'];
        }
        if ((int) Database::scalar('SELECT COUNT(*) FROM organizations WHERE id = ?', [$organizationId]) === 0) {
            return ['ok' => false, 'error' => 'Kurum bulunamadı.'];
        }

        $trialDays = max(0, (int) ($options['trial_days'] ?? 0));
        $interval = self::intervalFor($plan, isset($options['interval']) ? (string) $options['interval'] : null);
        $price = self::priceFor($plan, $interval);
        $days = self::periodDays($plan, isset($options['days']) ? (int) $options['days'] : null, $interval);
        $now = time();
        $status = $trialDays > 0 ? 'trialing' : (string) ($options['status'] ?? 'active');
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'active';
        }

        $start = date('Y-m-d H:i:s', $now);
        $periodEnd = date('Y-m-d H:i:s', $now + ($status === 'trialing' ? $trialDays : $days) * 86400);

        try {
            $subscriptionId = Database::transaction(function () use ($organizationId, $plan, $status, $start, $periodEnd, $trialDays, $days, $interval, $price, $options): int {
                // Aynı kurumda aktif/trial abonelik varsa onu kapat.
                Database::run(
                    "UPDATE subscriptions SET status = 'cancelled', cancelled_at = ?, cancel_at_period_end = 0
                     WHERE organization_id = ? AND status IN ('active','trialing','past_due')",
                    [date('Y-m-d H:i:s'), $organizationId]
                );

                $id = Database::insert('subscriptions', [
                    'organization_id'      => $organizationId,
                    'plan_id'              => (int) $plan['id'],
                    'billing_interval'     => $interval,
                    'status'               => $status,
                    'provider'             => (string) ($options['provider'] ?? 'manual'),
                    'assigned_by'          => Auth::id(),
                    'current_period_start' => $start,
                    'current_period_end'   => $periodEnd,
                    'trial_ends_at'        => $trialDays > 0 ? $periodEnd : null,
                    'notes'                => isset($options['note']) ? mb_substr((string) $options['note'], 0, 250) : null,
                    'price_at_signup'      => $price,
                    'created_at'           => $start,
                ]);

                // Ödemeyi kaydet (plan ücretliyse)
                if (!empty($options['mark_paid']) && $price > 0) {
                    Database::insert('payments', [
                        'organization_id' => $organizationId,
                        'subscription_id' => $id,
                        'amount'          => $price,
                        'currency'        => (string) ($plan['currency'] ?? Settings::get('payment.currency', 'TRY')),
                        'plan_id'         => (int) $plan['id'],
                        'billing_interval' => $interval,
                        'status'          => 'paid',
                        'provider'        => (string) ($options['provider'] ?? 'manual'),
                        'method'          => (string) ($options['method'] ?? 'manual'),
                        'recorded_by'     => Auth::id(),
                        'notes'           => 'Abonelik ataması ile birlikte kaydedildi (' . $days . ' gün).',
                        'paid_at'         => $start,
                        'period_start'    => $start,
                        'period_end'      => $periodEnd,
                        'created_at'      => $start,
                    ]);
                }

                Database::update('organizations', ['updated_at' => $start], 'id = :id', ['id' => $organizationId]);
                return $id;
            });
        } catch (\Throwable $e) {
            Logger::error('Abonelik atanamadi: ' . $e->getMessage(), ['org' => $organizationId, 'plan' => $planId]);
            return ['ok' => false, 'error' => 'Abonelik kaydedilemedi.'];
        }

        Audit::log('subscription.assign', 'organization', $organizationId, [
            'plan' => $plan['key'], 'status' => $status, 'interval' => $interval, 'period_end' => $periodEnd,
        ]);

        // Deneme atamalarında ayrıca bildirim gönderme (karşılama e-postası yeterli).
        if ($status !== 'trialing') {
            AccountNotifier::subscriptionChanged($organizationId, (string) ($plan['name'] ?? ''), $periodEnd, 'assigned');
        }

        return ['ok' => true, 'subscription_id' => $subscriptionId, 'period_end' => $periodEnd, 'interval' => $interval, 'price' => $price];
    }

    /**
     * Süre uzatma.
     * @return array{ok:bool,error?:string,period_end?:string}
     */
    public static function extend(int $subscriptionId, int $days, bool $markPaid = false, ?string $note = null): array
    {
        if ($days <= 0 || $days > 3650) {
            return ['ok' => false, 'error' => 'Geçersiz süre.'];
        }

        $subscription = Database::first(
            'SELECT s.*, p.price, p.price_month, p.price_year, p.currency, p.name AS plan_name
             FROM subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.id = ? LIMIT 1',
            [$subscriptionId]
        );
        if ($subscription === null) {
            return ['ok' => false, 'error' => 'Abonelik bulunamadı.'];
        }

        // Dönem fiyatı: satın alma anındaki fiyat (fiyat değişiklikleri geriye işlemez).
        $interval = in_array((string) ($subscription['billing_interval'] ?? ''), self::INTERVALS, true)
            ? (string) $subscription['billing_interval']
            : self::intervalFor($subscription);
        $unitPrice = (float) ($subscription['price_at_signup'] ?? 0);
        if ($unitPrice <= 0) {
            $unitPrice = self::priceFor($subscription, $interval);
        }
        $units = max(1, (int) round($days / self::daysForInterval($interval)));

        $base = max(strtotime((string) ($subscription['current_period_end'] ?? 'now')) ?: time(), time());
        $newEnd = date('Y-m-d H:i:s', $base + $days * 86400);

        try {
            Database::transaction(function () use ($subscription, $subscriptionId, $newEnd, $days, $markPaid, $note, $interval, $unitPrice, $units): void {
                $status = in_array((string) $subscription['status'], ['cancelled', 'expired'], true) ? 'active' : (string) $subscription['status'];
                Database::run(
                    'UPDATE subscriptions SET current_period_end = ?, status = ?, cancel_at_period_end = 0, cancelled_at = NULL, updated_at = ? WHERE id = ?',
                    [$newEnd, $status, date('Y-m-d H:i:s'), $subscriptionId]
                );

                if ($markPaid && $unitPrice > 0) {
                    Database::insert('payments', [
                        'organization_id' => (int) $subscription['organization_id'],
                        'subscription_id' => $subscriptionId,
                        'amount'          => round($unitPrice * $units, 2),
                        'currency'        => (string) $subscription['currency'],
                        'plan_id'         => (int) $subscription['plan_id'],
                        'billing_interval' => $interval,
                        'status'          => 'paid',
                        'provider'        => (string) $subscription['provider'],
                        'method'          => 'manual',
                        'recorded_by'     => Auth::id(),
                        'notes'           => $note ?? ('Süre uzatma: ' . $days . ' gün'),
                        'paid_at'         => date('Y-m-d H:i:s'),
                        'period_start'    => date('Y-m-d H:i:s'),
                        'period_end'      => $newEnd,
                        'created_at'      => date('Y-m-d H:i:s'),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Logger::error('Abonelik uzatilamadi: ' . $e->getMessage(), ['subscription' => $subscriptionId]);
            return ['ok' => false, 'error' => 'Abonelik uzatılamadı.'];
        }

        Audit::log('subscription.extend', 'subscription', $subscriptionId, ['days' => $days, 'interval' => $interval, 'period_end' => $newEnd]);
        AccountNotifier::subscriptionChanged(
            (int) $subscription['organization_id'],
            (string) ($subscription['plan_name'] ?? ''),
            $newEnd,
            'extended'
        );
        return ['ok' => true, 'period_end' => $newEnd];
    }

    /** İptal: dönem sonunda mı, hemen mi? */
    public static function cancel(int $subscriptionId, bool $immediate = false, ?string $note = null): array
    {
        $subscription = Database::first('SELECT * FROM subscriptions WHERE id = ? LIMIT 1', [$subscriptionId]);
        if ($subscription === null) {
            return ['ok' => false, 'error' => 'Abonelik bulunamadı.'];
        }

        $data = [
            'cancel_at_period_end' => $immediate ? 0 : 1,
            'notes'                => $note === null ? $subscription['notes'] : mb_substr($note, 0, 250),
            'updated_at'           => date('Y-m-d H:i:s'),
        ];
        if ($immediate) {
            $data['status'] = 'cancelled';
            $data['cancelled_at'] = date('Y-m-d H:i:s');
        }
        Database::update('subscriptions', $data, 'id = :id', ['id' => $subscriptionId]);

        Audit::log('subscription.cancel', 'subscription', $subscriptionId, ['immediate' => $immediate]);
        return ['ok' => true];
    }

    /** Pasif planı yeniden aktif eder (iptal geri alma). */
    public static function resume(int $subscriptionId): array
    {
        $subscription = Database::first('SELECT * FROM subscriptions WHERE id = ? LIMIT 1', [$subscriptionId]);
        if ($subscription === null) {
            return ['ok' => false, 'error' => 'Abonelik bulunamadı.'];
        }
        $expired = strtotime((string) $subscription['current_period_end']) < time();
        Database::update('subscriptions', [
            'status'               => $expired ? 'expired' : 'active',
            'cancel_at_period_end' => 0,
            'cancelled_at'         => null,
            'updated_at'           => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $subscriptionId]);

        Audit::log('subscription.resume', 'subscription', $subscriptionId);
        return ['ok' => true, 'expired' => $expired];
    }

    /**
     * Kredi bakiyesinden mevcut aboneliğin dönemini öder/uzatır.
     * @return array{ok:bool,error?:string,shortfall?:float,period_end?:string,balance?:float}
     */
    public static function payWithCredit(int $subscriptionId, ?float $amount = null): array
    {
        $subscription = Database::first(
            'SELECT s.*, p.price, p.price_month, p.price_year, p.currency
             FROM subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.id = ? LIMIT 1',
            [$subscriptionId]
        );
        if ($subscription === null) {
            return ['ok' => false, 'error' => 'Abonelik bulunamadı.'];
        }

        $interval = in_array((string) ($subscription['billing_interval'] ?? ''), self::INTERVALS, true)
            ? (string) $subscription['billing_interval']
            : self::intervalFor($subscription);
        $price = (float) ($subscription['price_at_signup'] ?? 0);
        if ($price <= 0) {
            $price = self::priceFor($subscription, $interval);
        }

        $amount = round($amount ?? $price, 2);
        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'Bu plan ücretsiz; kredi kullanımına gerek yok.'];
        }

        $organizationId = (int) $subscription['organization_id'];
        $balance = CreditService::balance($organizationId);
        if ($balance < $amount) {
            return [
                'ok'        => false,
                'shortfall' => round($amount - $balance, 2),
                'error'     => sprintf('Yetersiz kredi. Bakiye: %.2f, gereken: %.2f', $balance, $amount),
            ];
        }

        $deduct = CreditService::deduct(
            $organizationId,
            $amount,
            'subscription',
            'Abonelik ödemesi (kredi ile)',
            'subscription:' . $subscriptionId
        );
        if (!$deduct['ok']) {
            return ['ok' => false, 'error' => (string) $deduct['error']];
        }

        $extend = self::extend($subscriptionId, self::daysForInterval($interval), false, 'Kredi ile ödendi');
        if (!$extend['ok']) {
            // Krediyi geri ver (işlem bütünlüğü)
            CreditService::add($organizationId, $amount, 'refund', 'Abonelik uzatma başarısız — iade', 'subscription:' . $subscriptionId);
            return ['ok' => false, 'error' => (string) $extend['error']];
        }

        self::recordPayment(
            $organizationId,
            $subscriptionId,
            $amount,
            (string) ($subscription['currency'] ?? ''),
            'credit',
            (int) $subscription['plan_id'],
            $interval,
            (string) $extend['period_end'],
            'Kredi bakiyesinden tahsil edildi.'
        );

        Audit::log('subscription.paid_with_credit', 'subscription', $subscriptionId, ['amount' => $amount, 'interval' => $interval]);
        return ['ok' => true, 'period_end' => (string) $extend['period_end'], 'balance' => (float) $deduct['balance'], 'charged' => $amount];
    }

    /**
     * Planı seçilen dönem (aylık/yıllık) için satın alır veya aynı planda yeniler.
     * Ödeme yöntemi: `credit` (kredi bakiyesinden) veya `bank_transfer` (havale talebi).
     * Kredi yetersizse hiçbir şey değiştirilmez ve `shortfall` döner.
     *
     * @return array{ok:bool,error?:string,shortfall?:float,needed?:float,balance?:float,subscription_id?:int,period_end?:string,charged?:float,interval?:string}
     */
    public static function purchase(int $organizationId, int $planId, string $interval = 'month', string $method = 'credit', ?string $note = null): array
    {
        $plan = self::plan($planId);
        if ($plan === null || (int) $plan['is_active'] !== 1) {
            return ['ok' => false, 'error' => 'Seçilen plan bulunamadı.'];
        }

        $interval = self::intervalFor($plan, $interval);
        $price = self::priceFor($plan, $interval);
        if ($price <= 0) {
            return ['ok' => false, 'error' => 'Bu plan ücretsiz; ödeme gerekmez.'];
        }
        if (!in_array($method, ['credit', 'bank_transfer'], true)) {
            $method = 'credit';
        }

        $current = self::current($organizationId);
        $renew = $current !== null
            && (int) $current['plan_id'] === $planId
            && in_array((string) $current['status'], ['active', 'trialing', 'past_due'], true)
            && (string) ($current['billing_interval'] ?? '') === $interval;

        $deducted = false;
        if ($method === 'credit') {
            $balance = CreditService::balance($organizationId);
            if ($balance < $price) {
                return [
                    'ok'        => false,
                    'shortfall' => round($price - $balance, 2),
                    'needed'    => $price,
                    'balance'   => $balance,
                    'error'     => sprintf('Yetersiz kredi. Bakiye: %.2f, gereken: %.2f', $balance, $price),
                ];
            }
            $deduct = CreditService::deduct(
                $organizationId,
                $price,
                'subscription',
                'Paket ödemesi (kredi ile) — ' . (string) $plan['name'],
                'plan:' . $planId . ':' . $interval
            );
            if (!$deduct['ok']) {
                return ['ok' => false, 'error' => (string) $deduct['error']];
            }
            $deducted = true;
        }

        $noteText = $note ?? ($method === 'credit' ? 'Kredi bakiyesinden ödendi.' : 'Havale ile ödendi.');

        if ($renew) {
            $applied = self::extend((int) $current['id'], self::daysForInterval($interval), false, $noteText);
            $subscriptionId = (int) $current['id'];
        } else {
            $applied = self::assign($organizationId, $planId, [
                'interval'  => $interval,
                'mark_paid' => false,
                'provider'  => $method,
                'method'    => $method,
                'note'      => $noteText,
            ]);
            $subscriptionId = (int) ($applied['subscription_id'] ?? 0);
        }

        if (!$applied['ok']) {
            if ($deducted) {
                CreditService::add($organizationId, $price, 'refund', 'Paket satın alma başarısız — iade', 'plan:' . $planId);
            }
            return ['ok' => false, 'error' => (string) $applied['error']];
        }

        $periodEnd = (string) $applied['period_end'];
        self::recordPayment(
            $organizationId,
            $subscriptionId,
            $price,
            (string) ($plan['currency'] ?? ''),
            $method,
            $planId,
            $interval,
            $periodEnd,
            $noteText
        );

        Audit::log('subscription.purchase', 'organization', $organizationId, [
            'plan' => $plan['key'], 'interval' => $interval, 'amount' => $price, 'method' => $method,
        ]);

        return [
            'ok'              => true,
            'subscription_id' => $subscriptionId,
            'period_end'      => $periodEnd,
            'balance'         => CreditService::balance($organizationId),
            'charged'         => $price,
            'interval'        => $interval,
        ];
    }

    /** Ödenmiş abonelik hareketini payments defterine yazar. */
    private static function recordPayment(
        int $organizationId,
        int $subscriptionId,
        float $amount,
        string $currency,
        string $method,
        int $planId,
        string $interval,
        string $periodEnd,
        ?string $note = null
    ): void {
        $now = date('Y-m-d H:i:s');
        $currency = strtoupper(trim($currency));
        Database::insert('payments', [
            'organization_id'  => $organizationId,
            'subscription_id'  => $subscriptionId > 0 ? $subscriptionId : null,
            'amount'           => $amount,
            'currency'         => $currency !== '' ? $currency : (string) Settings::get('payment.currency', 'TRY'),
            'status'           => 'paid',
            'provider'         => $method,
            'method'           => $method,
            'kind'             => 'subscription',
            'plan_id'          => $planId > 0 ? $planId : null,
            'billing_interval' => in_array($interval, self::INTERVALS, true) ? $interval : null,
            'recorded_by'      => Auth::id(),
            'notes'            => $note === null ? null : mb_substr($note, 0, 250),
            'paid_at'          => $now,
            'period_start'     => $now,
            'period_end'       => $periodEnd,
            'created_at'       => $now,
        ]);
    }

    /** Dönemi geçmiş abonelikleri işaretler (cron çağırır). */
    public static function expireOverdue(): int
    {
        $now = date('Y-m-d H:i:s');
        return Database::run(
            "UPDATE subscriptions
             SET status = CASE WHEN cancel_at_period_end = 1 THEN 'cancelled' ELSE 'expired' END,
                 cancelled_at = CASE WHEN cancel_at_period_end = 1 THEN ? ELSE cancelled_at END,
                 updated_at = ?
             WHERE status IN ('active','trialing','past_due') AND current_period_end IS NOT NULL AND current_period_end < ?",
            [$now, $now, $now]
        )->rowCount();
    }

    /** Kurumun plan limitleri (plan yoksa ücretsiz varsayılan). */
    public static function limits(int $organizationId): array
    {
        $subscription = self::current($organizationId);
        if ($subscription === null) {
            return ['max_vehicles' => 1, 'max_users' => 1, 'max_documents_per_vehicle' => 2, 'channels' => 'email', 'plan' => 'Yok', 'status' => 'none', 'period_end' => null];
        }
        return [
            'max_vehicles'               => (int) $subscription['max_vehicles'],
            'max_users'                  => (int) $subscription['max_users'],
            'max_documents_per_vehicle'  => (int) $subscription['max_documents_per_vehicle'],
            'channels'                   => (string) $subscription['channels'],
            'plan'                       => (string) $subscription['plan_name'],
            'status'                     => (string) $subscription['status'],
            'period_end'                 => $subscription['current_period_end'],
        ];
    }

    /**
     * Standart pakette sıradaki aracın ücreti.
     * Ücretsiz deneme paketinde veya aktif abonelik yoksa null döner.
     * Ücret kredi bakiyesinden düşülür; evraklar için ek ücret alınmaz.
     *
     * @return array{no:int,discount:int,price:float,currency:string,balance:float,enough:bool}|null
     */
    public static function nextVehicleFee(int $organizationId): ?array
    {
        $subscription = self::current($organizationId);
        if ($subscription === null
            || (string) $subscription['plan_key'] !== 'standard'
            || !in_array((string) $subscription['status'], ['active', 'trialing'], true)) {
            return null;
        }

        $used = (int) Database::scalar(
            'SELECT COUNT(*) FROM vehicles WHERE organization_id = ? AND deleted_at IS NULL',
            [$organizationId]
        );
        // İlk araç paket fiyatına dahildir; yalnızca ek araçlar ücretlendirilir.
        if ($used < 1) {
            return null;
        }
        $no = $used + 1;
        $price = VehiclePricing::priceFor($no, (float) $subscription['plan_price']);
        $balance = CreditService::balance($organizationId);

        return [
            'no'       => $no,
            'discount' => VehiclePricing::discountFor($no),
            'price'    => $price,
            'currency' => (string) $subscription['plan_currency'],
            'balance'  => $balance,
            'enough'   => $balance + 0.001 >= $price,
        ];
    }

    /** Araç eklenebilir mi? (plan limiti denetimi) */
    public static function canAddVehicle(int $organizationId): array
    {
        $limits = self::limits($organizationId);
        if (in_array($limits['status'], ['cancelled', 'expired'], true)) {
            return ['ok' => false, 'error' => 'Aboneliğiniz sona ermiş. Lütfen planınızı yenileyin.'];
        }
        $used = (int) Database::scalar('SELECT COUNT(*) FROM vehicles WHERE organization_id = ? AND deleted_at IS NULL', [$organizationId]);
        if ($used >= $limits['max_vehicles']) {
            return [
                'ok'       => false,
                'limit'    => true,
                'error'    => sprintf('%s planında en fazla %d araç ekleyebilirsiniz. Planı yükseltmek için bizimle iletişime geçin.', $limits['plan'], $limits['max_vehicles']),
                'whatsapp' => whatsapp_url('Merhaba, evrako planımı yükseltmek istiyorum.'),
            ];
        }
        return ['ok' => true];
    }

    /** @return array<int,array> */
    public static function payments(int $organizationId, int $limit = 100): array
    {
        $limit = max(1, min(300, $limit));
        return Database::select(
            "SELECT p.*, u.full_name AS recorded_by_name, pl.name AS plan_name
             FROM payments p
             LEFT JOIN users u ON u.id = p.recorded_by
             LEFT JOIN subscriptions s ON s.id = p.subscription_id
             LEFT JOIN plans pl ON pl.id = s.plan_id
             WHERE p.organization_id = ?
             ORDER BY p.id DESC LIMIT {$limit}",
            [$organizationId]
        );
    }
}
