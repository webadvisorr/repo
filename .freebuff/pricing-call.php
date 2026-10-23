<?php
declare(strict_types=1);

/**
 * Fiyat/araç ücreti test yardımcısı.
 * Kullanım:
 *   php .freebuff/pricing-call.php quote <araç sayısı>
 *   php .freebuff/pricing-call.php fees            # ek araç kademeleri + kredi kontrolü
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\SubscriptionService;
use App\Services\VehiclePricing;

$cmd = (string) ($argv[1] ?? 'quote');

if ($cmd === 'quote') {
    $count = max(1, (int) ($argv[2] ?? 1));
    $q = VehiclePricing::quote($count);
    echo implode('|', array_map(static fn (array $i): string => (string) $i['price'], $q['items']))
        . '|' . $q['total'] . '|' . $q['saved'] . PHP_EOL;
    exit(0);
}

if ($cmd === 'fees') {
    $plan = Database::first("SELECT * FROM plans WHERE `key` = 'standard'");
    if ($plan === null) {
        fwrite(STDERR, "standart plan yok\n");
        exit(1);
    }
    $type = (int) Database::scalar('SELECT id FROM vehicle_types ORDER BY id LIMIT 1');
    $now = time();

    $makeOrg = static function (float $credit, string $name) use ($plan, $now, $type): int {
        $orgId = (int) Database::insert('organizations', [
            'owner_user_id'  => 1,
            'name'           => $name,
            'type'           => 'individual',
            'credit_balance' => $credit,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
        Database::insert('subscriptions', [
            'organization_id'      => $orgId,
            'plan_id'              => (int) $plan['id'],
            'billing_interval'     => 'lifetime',
            'status'               => 'active',
            'provider'             => 'manual',
            'price_at_signup'      => (float) $plan['price'],
            'current_period_start' => date('Y-m-d H:i:s', $now),
            'current_period_end'   => date('Y-m-d H:i:s', $now + 36500 * 86400),
            'created_at'           => date('Y-m-d H:i:s'),
        ]);
        return $orgId;
    };

    $orgId = $makeOrg(1000, 'E2E Pricing Org ' . $now);
    $out = [];
    for ($i = 1; $i <= 5; $i++) {
        $fee = SubscriptionService::nextVehicleFee($orgId);
        $out[] = $fee === null
            ? 'null'
            : $fee['no'] . ':' . $fee['discount'] . ':' . $fee['price'] . ':' . ($fee['enough'] ? '1' : '0');
        Database::insert('vehicles', [
            'organization_id' => $orgId,
            'vehicle_type_id' => $type,
            'plate'           => 'E2EP' . $i,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
    }
    Database::delete('organizations', 'id = ?', [$orgId]);

    $poorOrg = $makeOrg(0, 'E2E Pricing Poor ' . $now);
    Database::insert('vehicles', [
        'organization_id' => $poorOrg,
        'vehicle_type_id' => $type,
        'plate'           => 'E2EP-POOR',
        'created_at'      => date('Y-m-d H:i:s'),
    ]);
    $poorFee = SubscriptionService::nextVehicleFee($poorOrg);
    Database::delete('organizations', 'id = ?', [$poorOrg]);

    echo implode(',', $out) . '|' . (($poorFee !== null && !$poorFee['enough']) ? 'blocked' : 'allowed') . PHP_EOL;
    exit(0);
}

fwrite(STDERR, "komutlar: quote <n> | fees\n");
exit(1);
