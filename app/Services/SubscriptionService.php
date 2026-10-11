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

    public static function plan(int $planId): ?array
    {
        return Database::first('SELECT * FROM plans WHERE id = ? LIMIT 1', [$planId]);
    }

    /** @return array<int,array> */
    public static function plans(bool $onlyActive = true): array
    {
        $sql = 'SELECT * FROM plans' . ($onlyActive ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order ASC, price ASC';
        return Database::select($sql);
    }

    /** Kurumun yürürlükteki aboneliği (plan bilgisiyle). */
    public static function current(int $organizationId): ?array
    {
        return Database::first(
            "SELECT s.*, p.`key` AS plan_key, p.name AS plan_name, p.price AS plan_price, p.currency AS plan_currency,
                    p.max_vehicles, p.max_users, p.max_documents_per_vehicle, p.channels, p.features_json, p.billing_interval
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

    private static function periodDays(array $plan, ?int $overrideDays = null): int
    {
        if ($overrideDays !== null && $overrideDays > 0) {
            return $overrideDays;
        }
        return match ((string) ($plan['billing_interval'] ?? 'month')) {
            'year'     => 365,
            'lifetime' => 36500,
            default    => 30,
        };
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
        $days = self::periodDays($plan, isset($options['days']) ? (int) $options['days'] : null);
        $now = time();
        $status = $trialDays > 0 ? 'trialing' : (string) ($options['status'] ?? 'active');
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'active';
        }

        $start = date('Y-m-d H:i:s', $now);
        $periodEnd = date('Y-m-d H:i:s', $now + ($status === 'trialing' ? $trialDays : $days) * 86400);

        try {
            $subscriptionId = Database::transaction(function () use ($organizationId, $plan, $status, $start, $periodEnd, $trialDays, $days, $options): int {
                // Aynı kurumda aktif/trial abonelik varsa onu kapat.
                Database::run(
                    "UPDATE subscriptions SET status = 'cancelled', cancelled_at = ?, cancel_at_period_end = 0
                     WHERE organization_id = ? AND status IN ('active','trialing','past_due')",
                    [date('Y-m-d H:i:s'), $organizationId]
                );

                $id = Database::insert('subscriptions', [
                    'organization_id'      => $organizationId,
                    'plan_id'              => (int) $plan['id'],
                    'status'               => $status,
                    'provider'             => (string) ($options['provider'] ?? 'manual'),
                    'assigned_by'          => Auth::id(),
                    'current_period_start' => $start,
                    'current_period_end'   => $periodEnd,
                    'trial_ends_at'        => $trialDays > 0 ? $periodEnd : null,
                    'notes'                => isset($options['note']) ? mb_substr((string) $options['note'], 0, 250) : null,
                    'price_at_signup'      => (float) $plan['price'],
                    'created_at'           => $start,
                ]);

                // Ödemeyi kaydet (plan ücretliyse)
                if (!empty($options['mark_paid']) && (float) $plan['price'] > 0) {
                    Database::insert('payments', [
                        'organization_id' => $organizationId,
                        'subscription_id' => $id,
                        'amount'          => (float) $plan['price'],
                        'currency'        => (string) ($plan['currency'] ?? Settings::get('payment.currency', 'TRY')),
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
            'plan' => $plan['key'], 'status' => $status, 'period_end' => $periodEnd,
        ]);

        return ['ok' => true, 'subscription_id' => $subscriptionId, 'period_end' => $periodEnd];
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
            'SELECT s.*, p.price, p.currency, p.name AS plan_name FROM subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.id = ? LIMIT 1',
            [$subscriptionId]
        );
        if ($subscription === null) {
            return ['ok' => false, 'error' => 'Abonelik bulunamadı.'];
        }

        $base = max(strtotime((string) ($subscription['current_period_end'] ?? 'now')) ?: time(), time());
        $newEnd = date('Y-m-d H:i:s', $base + $days * 86400);

        try {
            Database::transaction(function () use ($subscription, $subscriptionId, $newEnd, $days, $markPaid, $note): void {
                $status = in_array((string) $subscription['status'], ['cancelled', 'expired'], true) ? 'active' : (string) $subscription['status'];
                Database::run(
                    'UPDATE subscriptions SET current_period_end = ?, status = ?, cancel_at_period_end = 0, cancelled_at = NULL, updated_at = ? WHERE id = ?',
                    [$newEnd, $status, date('Y-m-d H:i:s'), $subscriptionId]
                );

                if ($markPaid && (float) $subscription['price'] > 0) {
                    $months = max(1, (int) round($days / 30));
                    Database::insert('payments', [
                        'organization_id' => (int) $subscription['organization_id'],
                        'subscription_id' => $subscriptionId,
                        'amount'          => round((float) $subscription['price'] * $months, 2),
                        'currency'        => (string) $subscription['currency'],
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

        Audit::log('subscription.extend', 'subscription', $subscriptionId, ['days' => $days, 'period_end' => $newEnd]);
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
     * Kredi bakiyesinden abonelik ödemesi yapar ve dönemi uzatır.
     * @return array{ok:bool,error?:string,period_end?:string,balance?:float}
     */
    public static function payWithCredit(int $subscriptionId, ?float $amount = null): array
    {
        $subscription = Database::first(
            'SELECT s.*, p.price, p.currency, p.billing_interval FROM subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.id = ? LIMIT 1',
            [$subscriptionId]
        );
        if ($subscription === null) {
            return ['ok' => false, 'error' => 'Abonelik bulunamadı.'];
        }

        $amount = round($amount ?? (float) $subscription['price'], 2);
        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'Bu plan ücretsiz; kredi kullanımına gerek yok.'];
        }

        $balance = CreditService::balance((int) $subscription['organization_id']);
        if ($balance < $amount) {
            return ['ok' => false, 'error' => sprintf('Yetersiz kredi. Bakiye: %.2f, gereken: %.2f', $balance, $amount)];
        }

        $deduct = CreditService::deduct(
            (int) $subscription['organization_id'],
            $amount,
            'subscription',
            'Abonelik ödemesi (kredi ile)',
            'subscription:' . $subscriptionId
        );
        if (!$deduct['ok']) {
            return ['ok' => false, 'error' => (string) $deduct['error']];
        }

        $days = (int) round($amount / max((float) $subscription['price'], 0.01)) * match ((string) $subscription['billing_interval']) {
            'year' => 365, 'lifetime' => 36500, default => 30,
        };
        $days = max(1, min(3650, $days));

        $extend = self::extend($subscriptionId, $days, false, 'Kredi ile ödendi');
        if (!$extend['ok']) {
            // Krediyi geri ver (işlem bütünlüğü)
            CreditService::add((int) $subscription['organization_id'], $amount, 'refund', 'Abonelik uzatma başarısız — iade', 'subscription:' . $subscriptionId);
            return ['ok' => false, 'error' => (string) $extend['error']];
        }

        Database::insert('payments', [
            'organization_id' => (int) $subscription['organization_id'],
            'subscription_id' => $subscriptionId,
            'amount'          => $amount,
            'currency'        => (string) $subscription['currency'],
            'status'          => 'paid',
            'provider'        => 'credit',
            'method'          => 'credit',
            'recorded_by'     => Auth::id(),
            'notes'           => 'Kredi bakiyesinden tahsil edildi.',
            'paid_at'         => date('Y-m-d H:i:s'),
            'period_start'    => date('Y-m-d H:i:s'),
            'period_end'      => (string) $extend['period_end'],
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        Audit::log('subscription.paid_with_credit', 'subscription', $subscriptionId, ['amount' => $amount]);
        return ['ok' => true, 'period_end' => (string) $extend['period_end'], 'balance' => (float) $deduct['balance']];
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
                'ok'    => false,
                'error' => sprintf('%s planında en fazla %d araç ekleyebilirsiniz. Planı yükseltmek için bizimle iletişime geçin.', $limits['plan'], $limits['max_vehicles']),
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
