<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;

/**
 * Araç kaydı ve trafik ışığı durumları.
 * Her araç bir kuruma aittir; plaka kurum içinde benzersizdir.
 */
final class VehicleService
{
    public const LIGHTS = ['green', 'yellow', 'orange', 'red', 'none'];

    /** @return array<int,array> Aktif araç türleri. */
    public static function types(bool $onlyActive = true): array
    {
        $sql = 'SELECT * FROM vehicle_types' . ($onlyActive ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order ASC, id ASC';
        return Database::select($sql);
    }

    public static function type(int $id): ?array
    {
        return Database::first('SELECT * FROM vehicle_types WHERE id = ? LIMIT 1', [$id]);
    }

    /** Kalan güne göre trafik ışığı seviyesi. PRD: yeşil 30+, sarı <30, turuncu <7, kırmızı süresi geçti. */
    public static function light(?int $daysLeft): string
    {
        if ($daysLeft === null) {
            return 'none';
        }
        if ($daysLeft < 0) {
            return 'red';
        }
        if ($daysLeft < 7) {
            return 'orange';
        }
        if ($daysLeft < 30) {
            return 'yellow';
        }
        return 'green';
    }

    /** Seviyeler arasında "en kötü" olanı seçer (kırmızı > turuncu > sarı > yeşil > yok). */
    public static function worstLight(array $levels): string
    {
        $order = ['red' => 4, 'orange' => 3, 'yellow' => 2, 'green' => 1, 'none' => 0];
        $worst = 'none';
        foreach ($levels as $level) {
            if (($order[$level] ?? 0) > ($order[$worst] ?? 0)) {
                $worst = (string) $level;
            }
        }
        return $worst;
    }

    /**
     * Kurumun araçları: tür + en yakın evrak bitişi + durum ışığı + evrak sayısı.
     * @param array{type_id?:int,status?:string,q?:string} $filters
     * @return array<int,array>
     */
    public static function forOrganization(int $organizationId, array $filters = []): array
    {
        $sql = "SELECT v.*, vt.labels_json AS type_labels, vt.icon AS type_icon,
                       MIN(CASE WHEN d.status = 'active' AND d.deleted_at IS NULL THEN d.end_date END) AS next_end,
                       COUNT(CASE WHEN d.status = 'active' AND d.deleted_at IS NULL THEN 1 END) AS active_docs
                FROM vehicles v
                JOIN vehicle_types vt ON vt.id = v.vehicle_type_id
                LEFT JOIN vehicle_documents d ON d.vehicle_id = v.id
                WHERE v.organization_id = ? AND v.deleted_at IS NULL";
        $params = [$organizationId];

        if (!empty($filters['type_id'])) {
            $sql .= ' AND v.vehicle_type_id = ?';
            $params[] = (int) $filters['type_id'];
        }
        if (!empty($filters['status']) && in_array($filters['status'], ['active', 'sold', 'archived'], true)) {
            $sql .= ' AND v.status = ?';
            $params[] = (string) $filters['status'];
        }
        if (!empty($filters['q'])) {
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], (string) $filters['q']) . '%';
            $sql .= ' AND (v.plate LIKE ? OR v.brand LIKE ? OR v.model LIKE ? OR v.driver_name LIKE ?)';
            array_push($params, $term, $term, $term, $term);
        }
        $sql .= ' GROUP BY v.id ORDER BY v.created_at DESC, v.id DESC';

        $rows = Database::select($sql, $params);
        foreach ($rows as &$row) {
            $row['days_left']  = days_left((string) ($row['next_end'] ?? ''));
            $row['light']      = self::light($row['days_left']);
            $row['type_label'] = label((string) $row['type_labels']);
        }
        unset($row);
        return $rows;
    }

    public static function find(int $organizationId, int $id): ?array
    {
        return Database::first(
            'SELECT v.*, vt.labels_json AS type_labels, vt.icon AS type_icon
             FROM vehicles v JOIN vehicle_types vt ON vt.id = v.vehicle_type_id
             WHERE v.id = ? AND v.organization_id = ? AND v.deleted_at IS NULL LIMIT 1',
            [$id, $organizationId]
        );
    }

    public static function count(int $organizationId): int
    {
        return (int) Database::scalar('SELECT COUNT(*) FROM vehicles WHERE organization_id = ? AND deleted_at IS NULL', [$organizationId]);
    }

    /**
     * Araç oluştur / güncelle. Plaka kurum içinde benzersizdir; arşivlenmiş aynı plaka
     * varsa kayıt geri getirilip güncellenir.
     * @param array<string,mixed> $data
     * @return array{ok:bool,error?:string,id?:int,revived?:bool}
     */
    public static function save(int $organizationId, ?int $id, array $data): array
    {
        $plate = mb_strtoupper(trim((string) ($data['plate'] ?? '')));
        if ($plate === '') {
            return ['ok' => false, 'error' => 'Plaka zorunludur.'];
        }

        $fields = [
            'vehicle_type_id' => (int) ($data['vehicle_type_id'] ?? 0),
            'plate'           => $plate,
            'brand'           => self::nullable($data['brand'] ?? null, 80),
            'model'           => self::nullable($data['model'] ?? null, 80),
            'model_year'      => self::nullable($data['model_year'] ?? null, null, true),
            'vin'             => self::nullable($data['vin'] ?? null, 40),
            'engine_no'       => self::nullable($data['engine_no'] ?? null, 40),
            'color'           => self::nullable($data['color'] ?? null, 40),
            'odometer'        => self::nullable($data['odometer'] ?? null, null, true),
            'driver_name'     => self::nullable($data['driver_name'] ?? null, 120),
            'notes'           => self::nullable($data['notes'] ?? null, 2000),
        ];
        if ($fields['vehicle_type_id'] === 0 || self::type($fields['vehicle_type_id']) === null) {
            return ['ok' => false, 'error' => 'Geçersiz araç türü.'];
        }
        $status = (string) ($data['status'] ?? 'active');
        if (!in_array($status, ['active', 'sold', 'archived'], true)) {
            $status = 'active';
        }

        $conflict = Database::first(
            'SELECT id, deleted_at FROM vehicles WHERE organization_id = ? AND plate = ? LIMIT 1',
            [$organizationId, $plate]
        );
        if ($conflict !== null && ($id === null || (int) $conflict['id'] !== $id)) {
            if ($conflict['deleted_at'] === null) {
                return ['ok' => false, 'error' => 'Bu plaka zaten kayıtlı.'];
            }
            // Arşivdeki aracı geri getir ve güncelle.
            $fields['deleted_at'] = null;
            $fields['status'] = $status;
            $fields['updated_at'] = date('Y-m-d H:i:s');
            Database::update('vehicles', $fields, 'id = :id', ['id' => (int) $conflict['id']]);
            Audit::log('vehicle.restore', 'vehicle', (int) $conflict['id'], ['plate' => $plate]);
            return ['ok' => true, 'id' => (int) $conflict['id'], 'revived' => true];
        }

        if ($id !== null) {
            if (self::find($organizationId, $id) === null) {
                return ['ok' => false, 'error' => 'Araç bulunamadı.'];
            }
            $fields['status'] = $status;
            $fields['updated_at'] = date('Y-m-d H:i:s');
            Database::update('vehicles', $fields, 'id = :id AND organization_id = :org', ['id' => $id, 'org' => $organizationId]);
            Audit::log('vehicle.update', 'vehicle', $id, ['plate' => $plate]);
            return ['ok' => true, 'id' => $id];
        }

        $fields['organization_id'] = $organizationId;
        $fields['status']          = $status;
        $fields['created_by']      = Auth::id();
        $fields['created_at']      = date('Y-m-d H:i:s');
        try {
            $newId = Database::insert('vehicles', $fields);
        } catch (\Throwable $e) {
            Logger::error('Araç kaydedilemedi: ' . $e->getMessage(), ['org' => $organizationId, 'plate' => $plate]);
            return ['ok' => false, 'error' => 'Araç kaydedilemedi.'];
        }
        Audit::log('vehicle.create', 'vehicle', $newId, ['plate' => $plate]);
        return ['ok' => true, 'id' => $newId];
    }

    /** Arşivle (soft delete). */
    public static function archive(int $organizationId, int $id): array
    {
        if (self::find($organizationId, $id) === null) {
            return ['ok' => false, 'error' => 'Araç bulunamadı.'];
        }
        Database::update('vehicles', [
            'status'     => 'archived',
            'deleted_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id AND organization_id = :org', ['id' => $id, 'org' => $organizationId]);
        Audit::log('vehicle.archive', 'vehicle', $id);
        return ['ok' => true];
    }

    private static function nullable(mixed $value, ?int $max, bool $numeric = false): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($numeric) {
            return is_numeric($value) ? (int) $value : null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        return $max === null ? $value : mb_substr($value, 0, $max);
    }
}
