<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;

/**
 * Kredi (ön ödemeli bakiye) yönetimi.
 * Kredi kurum (tenant) bazında tutulur; her hareket `credit_ledger` defterine yazılır.
 * Tüm bakiye değişimleri tek transaction + satır kilidi ile yapılır (yarış koşulu koruması).
 */
final class CreditService
{
    public const MAX_SINGLE_AMOUNT = 100000.0;

    public const REASONS = [
        'manual'        => 'Panelden manuel ekleme',
        'manual_debit'  => 'Panelden manuel düşme',
        'bank_transfer' => 'Havale / EFT',
        'cash'          => 'Nakit',
        'card'          => 'Kredi kartı',
        'subscription'  => 'Abonelik ödemesi',
        'sms_package'   => 'SMS paketi',
        'refund'        => 'İade',
        'bonus'         => 'Bonus / promosyon',
        'correction'    => 'Düzeltme',
        'usage'         => 'Kullanım',
    ];

    /** Kullanıcının kurumunu bulur; yoksa oluşturur (eski kayıtlar için güvence). */
    public static function organizationForUser(int $userId): ?int
    {
        $orgId = Database::scalar(
            "SELECT organization_id FROM organization_members WHERE user_id = ? AND status = 'active' ORDER BY id ASC LIMIT 1",
            [$userId]
        );
        if ($orgId !== null) {
            return (int) $orgId;
        }

        $owned = Database::scalar('SELECT id FROM organizations WHERE owner_user_id = ? LIMIT 1', [$userId]);
        if ($owned !== null) {
            return (int) $owned;
        }

        $user = Database::first('SELECT id, full_name FROM users WHERE id = ? LIMIT 1', [$userId]);
        if ($user === null) {
            return null;
        }

        $now = date('Y-m-d H:i:s');
        $newId = Database::insert('organizations', [
            'owner_user_id' => $userId,
            'name'          => (string) $user['full_name'],
            'type'          => 'individual',
            'created_at'    => $now,
        ]);
        Database::insert('organization_members', [
            'organization_id' => $newId,
            'user_id'         => $userId,
            'role'            => 'owner',
            'can_manage_vehicles'  => 1,
            'can_manage_documents' => 1,
            'can_manage_billing'   => 1,
            'status'          => 'active',
            'created_at'      => $now,
        ]);
        Logger::info('Eksik kurum otomatik olusturuldu', ['user_id' => $userId, 'organization_id' => $newId]);
        return $newId;
    }

    public static function balance(int $organizationId): float
    {
        return round((float) Database::scalar('SELECT credit_balance FROM organizations WHERE id = ?', [$organizationId]), 2);
    }

    /** @return array{ok:bool,error?:string,balance?:float,ledger_id?:int} */
    public static function add(int $organizationId, float $amount, string $reason = 'manual', ?string $note = null, ?string $reference = null, ?int $userId = null): array
    {
        return self::change($organizationId, $amount, 'credit', $reason, $note, $reference, $userId);
    }

    /** @return array{ok:bool,error?:string,balance?:float,ledger_id?:int} */
    public static function deduct(int $organizationId, float $amount, string $reason = 'usage', ?string $note = null, ?string $reference = null, ?int $userId = null): array
    {
        return self::change($organizationId, $amount, 'debit', $reason, $note, $reference, $userId);
    }

    /**
     * @return array{ok:bool,error?:string,balance?:float,ledger_id?:int}
     */
    private static function change(int $organizationId, float $amount, string $direction, string $reason, ?string $note, ?string $reference, ?int $userId): array
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'Tutar sıfırdan büyük olmalıdır.'];
        }
        if ($amount > self::MAX_SINGLE_AMOUNT) {
            return ['ok' => false, 'error' => 'Tek işlemde en fazla ' . number_format(self::MAX_SINGLE_AMOUNT, 0, ',', '.') . ' kredi girilebilir.'];
        }
        if (!array_key_exists($reason, self::REASONS)) {
            $reason = $direction === 'credit' ? 'manual' : 'usage';
        }
        if ((int) Database::scalar('SELECT COUNT(*) FROM organizations WHERE id = ?', [$organizationId]) === 0) {
            return ['ok' => false, 'error' => 'Kurum bulunamadı.'];
        }

        try {
            $result = Database::transaction(function () use ($organizationId, $amount, $direction, $reason, $note, $reference, $userId): array {
                // Satır kilidi: eşzamanlı iki işlem bakiyeyi bozamaz.
                $row = Database::first('SELECT id, credit_balance FROM organizations WHERE id = ? FOR UPDATE', [$organizationId]);
                if ($row === null) {
                    return ['ok' => false, 'error' => 'Kurum bulunamadı.'];
                }

                $current = round((float) $row['credit_balance'], 2);
                $newBalance = $direction === 'credit' ? $current + $amount : $current - $amount;

                if ($newBalance < 0) {
                    return ['ok' => false, 'error' => sprintf('Yetersiz kredi. Mevcut bakiye: %.2f', $current)];
                }

                Database::run('UPDATE organizations SET credit_balance = ? WHERE id = ?', [$newBalance, $organizationId]);
                $ledgerId = Database::insert('credit_ledger', [
                    'organization_id' => $organizationId,
                    'user_id'         => $userId,
                    'admin_user_id'   => Auth::id(),
                    'direction'       => $direction,
                    'amount'          => $amount,
                    'balance_after'   => $newBalance,
                    'currency'        => (string) \App\Core\Settings::get('payment.currency', 'TRY'),
                    'reason'          => $reason,
                    'note'            => $note === null ? null : mb_substr($note, 0, 250),
                    'reference'       => $reference === null ? null : mb_substr($reference, 0, 120),
                    'created_at'      => date('Y-m-d H:i:s'),
                ]);

                Audit::log(
                    $direction === 'credit' ? 'credit.add' : 'credit.deduct',
                    'organization',
                    $organizationId,
                    ['amount' => $amount, 'reason' => $reason, 'balance_after' => $newBalance]
                );

                return ['ok' => true, 'balance' => $newBalance, 'ledger_id' => $ledgerId];
            });

            // Yükleme ise sahibine panel bildirimi + e-posta gönder (düşümlerde sessiz kal).
            if (($result['ok'] ?? false) && $direction === 'credit') {
                AccountNotifier::creditAdded($organizationId, $amount, (float) ($result['balance'] ?? 0), $reason, $note);
            }

            return $result;
        } catch (\Throwable $e) {
            Logger::error('Kredi hareketi basarisiz: ' . $e->getMessage(), ['org' => $organizationId, 'amount' => $amount]);
            return ['ok' => false, 'error' => 'Kredi işlemi kaydedilemedi.'];
        }
    }

    /** @return array<int,array> */
    public static function history(int $organizationId, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        return Database::select(
            "SELECT l.*, u.full_name AS user_name, a.full_name AS admin_name
             FROM credit_ledger l
             LEFT JOIN users u ON u.id = l.user_id
             LEFT JOIN users a ON a.id = l.admin_user_id
             WHERE l.organization_id = ?
             ORDER BY l.id DESC LIMIT {$limit}",
            [$organizationId]
        );
    }

    /** @return array{credit:float,debit:float,count:int} */
    public static function summary(int $organizationId): array
    {
        $row = Database::first(
            "SELECT COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount END), 0) AS credit,
                    COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount END), 0) AS debit,
                    COUNT(*) AS cnt
             FROM credit_ledger WHERE organization_id = ?",
            [$organizationId]
        );
        return [
            'credit' => round((float) ($row['credit'] ?? 0), 2),
            'debit'  => round((float) ($row['debit'] ?? 0), 2),
            'count'  => (int) ($row['cnt'] ?? 0),
        ];
    }
}
