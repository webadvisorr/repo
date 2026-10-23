<?php
declare(strict_types=1);

/**
 * e2e yardımcısı: plan dönem fiyatları + kredi ile ödeme testleri için
 * doğrudan servis katmanını çağırır (HTTP arayüzü ayrıca curl ile test edilir).
 *
 * Kullanım:
 *   plan-call.php create-org <email> <password> <name>
 *   plan-call.php credit <orgId> <amount> [reason]
 *   plan-call.php state <orgId>
 *   plan-call.php approve <paymentId>
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;
use App\Services\CreditService;
use App\Services\PaymentService;
use App\Services\SubscriptionService;

$json = static fn ($value): string => (string) json_encode($value, JSON_UNESCAPED_UNICODE);
$cmd = (string) ($argv[1] ?? '');

switch ($cmd) {
    case 'create-org':
        $email = (string) ($argv[2] ?? '');
        $password = (string) ($argv[3] ?? '');
        $name = (string) ($argv[4] ?? 'Plan Test');
        if ($email === '' || $password === '') {
            echo 'email/password gerekli';
            exit(1);
        }
        $now = date('Y-m-d H:i:s');
        $userId = Database::insert('users', [
            'full_name'     => $name,
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role'              => 'customer',
            'status'            => 'active',
            'email_verified_at' => $now,
            'locale'            => 'tr',
            'created_at'        => $now,
        ]);
        $orgId = Database::insert('organizations', [
            'owner_user_id' => $userId,
            'name'          => $name,
            'type'          => 'individual',
            'credit_balance' => 0,
            'created_at'    => $now,
        ]);
        Database::insert('organization_members', [
            'organization_id'      => $orgId,
            'user_id'              => $userId,
            'role'                 => 'owner',
            'can_manage_vehicles'  => 1,
            'can_manage_documents' => 1,
            'can_manage_billing'   => 1,
            'status'               => 'active',
            'created_at'           => $now,
        ]);
        echo $json(['user_id' => $userId, 'org_id' => $orgId, 'email' => $email]);
        break;

    case 'credit':
        $result = CreditService::add((int) ($argv[2] ?? 0), (float) ($argv[3] ?? 0), (string) ($argv[4] ?? 'manual'), 'e2e kredi');
        echo $json($result);
        break;

    case 'state':
        $orgId = (int) ($argv[2] ?? 0);
        $sub = Database::first(
            "SELECT s.id, s.plan_id, p.`key` AS plan_key, s.status, s.billing_interval, s.price_at_signup,
                    s.current_period_start, s.current_period_end,
                    DATEDIFF(DATE(s.current_period_end), CURDATE()) AS days_left
             FROM subscriptions s JOIN plans p ON p.id = s.plan_id
             WHERE s.organization_id = ?
             ORDER BY FIELD(s.status,'active','trialing','past_due','cancelled','expired'), s.id DESC LIMIT 1",
            [$orgId]
        );
        $counts = Database::first(
            "SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN status='paid' THEN 1 END),0) AS paid,
                    COALESCE(SUM(CASE WHEN status='paid' THEN amount END),0) AS paid_total
             FROM payments WHERE organization_id = ?",
            [$orgId]
        );
        $last = Database::first(
            "SELECT id, amount, currency, status, provider, method, kind, plan_id, billing_interval,
                    period_start, period_end, applied_at
             FROM payments WHERE organization_id = ? ORDER BY id DESC LIMIT 1",
            [$orgId]
        );
        echo $json([
            'balance'       => CreditService::balance($orgId),
            'subscription'  => $sub,
            'payments'      => $counts,
            'last_payment'  => $last,
        ]);
        break;

    case 'approve':
        echo $json(PaymentService::approve((int) ($argv[2] ?? 0)));
        break;

    case 'plan-id':
        echo (string) Database::scalar('SELECT id FROM plans WHERE `key` = ? LIMIT 1', [(string) ($argv[2] ?? '')]);
        break;

    default:
        echo "komutlar: create-org|credit|state|approve|plan-id\n";
}
