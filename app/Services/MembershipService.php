<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Kurum/filo üyeliği ve yetki matrisi.
 * Her müşteri bir kuruma (organizations) bağlıdır; organization_members satırında
 * rol ve izin bayrakları tutulur. Panel işlemleri bu yetkilere göre korunur.
 */
final class MembershipService
{
    /** Kullanıcının üyelik bilgisi: kurum + rol + izinler. Yoksa null. */
    public static function forUser(int $userId): ?array
    {
        $row = Database::first(
            "SELECT m.organization_id, m.role, m.status,
                    m.can_manage_vehicles, m.can_manage_documents, m.can_manage_billing,
                    o.name AS org_name, o.type AS org_type, o.owner_user_id
             FROM organization_members m
             JOIN organizations o ON o.id = m.organization_id
             WHERE m.user_id = ? AND m.status = 'active'
             ORDER BY FIELD(m.role, 'owner', 'manager', 'viewer'), m.id ASC
             LIMIT 1",
            [$userId]
        );
        if ($row !== null) {
            return self::normalize($row);
        }

        // Üyelik kaydı olmayan eski kullanıcılar için sahiplikten türet.
        $owned = Database::first('SELECT * FROM organizations WHERE owner_user_id = ? LIMIT 1', [$userId]);
        if ($owned === null) {
            return null;
        }
        return self::normalize([
            'organization_id'      => $owned['id'],
            'role'                 => 'owner',
            'status'               => 'active',
            'can_manage_vehicles'  => 1,
            'can_manage_documents' => 1,
            'can_manage_billing'   => 1,
            'org_name'             => $owned['name'],
            'org_type'             => $owned['type'],
            'owner_user_id'        => $owned['owner_user_id'],
        ]);
    }

    public static function organizationId(int $userId): ?int
    {
        $membership = self::forUser($userId);
        return $membership === null ? null : (int) $membership['organization_id'];
    }

    /** Araç ekleme/düzenleme yetkisi. */
    public static function canManageVehicles(?array $membership): bool
    {
        if ($membership === null) {
            return false;
        }
        return (string) $membership['role'] === 'owner' || (int) $membership['can_manage_vehicles'] === 1;
    }

    /** Evrak ekleme/düzenleme yetkisi. */
    public static function canManageDocuments(?array $membership): bool
    {
        if ($membership === null) {
            return false;
        }
        return (string) $membership['role'] === 'owner' || (int) $membership['can_manage_documents'] === 1;
    }

    /** @return array<int,array> Kurumun tüm üyeleri (sahip + davetliler). */
    public static function members(int $organizationId): array
    {
        return Database::select(
            "SELECT m.*, u.full_name, u.email AS user_email
             FROM organization_members m
             LEFT JOIN users u ON u.id = m.user_id
             WHERE m.organization_id = ?
             ORDER BY FIELD(m.role, 'owner', 'manager', 'viewer'), m.id ASC",
            [$organizationId]
        );
    }

    private static function normalize(array $row): array
    {
        return [
            'organization_id'      => (int) $row['organization_id'],
            'role'                 => (string) $row['role'],
            'status'               => (string) $row['status'],
            'can_manage_vehicles'  => (int) $row['can_manage_vehicles'],
            'can_manage_documents' => (int) $row['can_manage_documents'],
            'can_manage_billing'   => (int) $row['can_manage_billing'],
            'org_name'             => (string) ($row['org_name'] ?? ''),
            'org_type'             => (string) ($row['org_type'] ?? 'individual'),
            'owner_user_id'        => (int) ($row['owner_user_id'] ?? 0),
        ];
    }
}
