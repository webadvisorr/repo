<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Settings;
use App\Core\Uploader;

/**
 * Araç evrakları: tür, kurum/poliçe no, tarihler, ücret, hatırlatma günleri,
 * belge arşivi, "yenilendi" işaretleme ve yenileme geçmişi.
 */
final class DocumentService
{
    public const STATUSES = ['active', 'renewed', 'cancelled'];

    /** @return array<int,array> Evrak türleri (isteğe bağlı araç türüne göre). */
    public static function types(?int $vehicleTypeId = null): array
    {
        $rows = Database::select('SELECT * FROM document_types WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
        if ($vehicleTypeId === null) {
            return $rows;
        }
        return array_values(array_filter($rows, static function (array $row) use ($vehicleTypeId): bool {
            $applies = (string) ($row['applies_vehicle_types'] ?? '');
            if ($applies === '') {
                return true; // NULL/boş = hepsi
            }
            $ids = array_map('intval', array_filter(array_map('trim', explode(',', $applies))));
            return in_array($vehicleTypeId, $ids, true);
        }));
    }

    public static function type(int $id): ?array
    {
        return Database::first('SELECT * FROM document_types WHERE id = ? LIMIT 1', [$id]);
    }

    /** @return array<int,array> Aracın evrakları (belgeleriyle birlikte). */
    public static function forVehicle(int $vehicleId, bool $includeInactive = true): array
    {
        $sql = "SELECT d.*, dt.labels_json AS type_labels, dt.color AS type_color, dt.requires_file,
                       u.full_name AS created_by_name
                FROM vehicle_documents d
                JOIN document_types dt ON dt.id = d.document_type_id
                LEFT JOIN users u ON u.id = d.created_by
                WHERE d.vehicle_id = ? AND d.deleted_at IS NULL";
        if (!$includeInactive) {
            $sql .= " AND d.status = 'active'";
        }
        $sql .= ' ORDER BY FIELD(d.status, \'active\', \'renewed\', \'cancelled\'), d.end_date ASC, d.id DESC';

        $rows = Database::select($sql, [$vehicleId]);
        foreach ($rows as &$row) {
            $row['days_left']  = days_left((string) $row['end_date']);
            $row['light']      = (string) $row['status'] === 'active' ? VehicleService::light($row['days_left']) : 'none';
            $row['type_label'] = label((string) $row['type_labels']);
            $row['files']      = self::files((int) $row['id']);
        }
        unset($row);
        return $rows;
    }

    /** @return array<int,array> Evrakın yüklenen belgeleri. */
    public static function files(int $documentId): array
    {
        return Database::select(
            'SELECT df.id, df.label, m.id AS media_id, m.disk_path, m.original_name, m.mime, m.size_bytes
             FROM document_files df JOIN media m ON m.id = df.media_id
             WHERE df.document_id = ? ORDER BY df.id ASC',
            [$documentId]
        );
    }

    /** Bir aracın en yakın bitişli aktif evrağı. */
    public static function nextDue(int $vehicleId): ?array
    {
        return Database::first(
            "SELECT d.*, dt.labels_json AS type_labels FROM vehicle_documents d
             JOIN document_types dt ON dt.id = d.document_type_id
             WHERE d.vehicle_id = ? AND d.status = 'active' AND d.deleted_at IS NULL
             ORDER BY d.end_date ASC LIMIT 1",
            [$vehicleId]
        );
    }

    /** Aktif evrak sayısı (limit kontrolü için). */
    public static function countForVehicle(int $vehicleId): int
    {
        return (int) Database::scalar(
            "SELECT COUNT(*) FROM vehicle_documents WHERE vehicle_id = ? AND status IN ('active','renewed') AND deleted_at IS NULL",
            [$vehicleId]
        );
    }

    /** Yeni evrak limiti kontrolü. */
    public static function canAdd(int $vehicleId): array
    {
        $vehicle = Database::first('SELECT organization_id FROM vehicles WHERE id = ? LIMIT 1', [$vehicleId]);
        if ($vehicle === null) {
            return ['ok' => false, 'error' => 'Araç bulunamadı.'];
        }
        $limits = SubscriptionService::limits((int) $vehicle['organization_id']);
        if (self::countForVehicle($vehicleId) >= (int) $limits['max_documents_per_vehicle']) {
            return ['ok' => false, 'error' => t('documents.limit', ['max' => (int) $limits['max_documents_per_vehicle']])];
        }
        return ['ok' => true];
    }

    /**
     * Evrak oluştur (isteğe bağlı belge yüklemeleriyle).
     * @param array<string,mixed> $data
     * @param array<int,array> $files
     * @return array{ok:bool,error?:string,id?:int}
     */
    public static function create(int $vehicleId, array $data, array $files = []): array
    {
        $limit = self::canAdd($vehicleId);
        if (!$limit['ok']) {
            return $limit;
        }
        $prepared = self::prepare($data);
        if (!$prepared['ok']) {
            return $prepared;
        }
        unset($prepared['ok']);
        $prepared['vehicle_id'] = $vehicleId;
        $prepared['created_by'] = Auth::id();
        $prepared['created_at'] = date('Y-m-d H:i:s');

        try {
            $id = Database::insert('vehicle_documents', $prepared);
        } catch (\Throwable $e) {
            Logger::error('Evrak kaydedilemedi: ' . $e->getMessage(), ['vehicle' => $vehicleId]);
            return ['ok' => false, 'error' => 'Evrak kaydedilemedi.'];
        }
        self::attachFiles($id, $files);
        Audit::log('document.create', 'vehicle_document', $id, ['vehicle' => $vehicleId, 'type' => $prepared['document_type_id']]);
        return ['ok' => true, 'id' => $id];
    }

    /**
     * Evrakı güncelle.
     * @return array{ok:bool,error?:string}
     */
    public static function update(int $documentId, array $data, array $files = []): array
    {
        $document = self::find($documentId);
        if ($document === null) {
            return ['ok' => false, 'error' => 'Evrak bulunamadı.'];
        }
        $prepared = self::prepare($data);
        if (!$prepared['ok']) {
            return $prepared;
        }
        unset($prepared['ok'], $prepared['vehicle_id'], $prepared['created_by'], $prepared['created_at']);
        $prepared['updated_at'] = date('Y-m-d H:i:s');
        Database::update('vehicle_documents', $prepared, 'id = :id', ['id' => $documentId]);
        self::attachFiles($documentId, $files);
        Audit::log('document.update', 'vehicle_document', $documentId, ['vehicle' => (int) $document['vehicle_id']]);
        return ['ok' => true];
    }

    /**
     * "Yenilendi": eski kayıt geçmişe düşer, yeni dönem kaydı oluşur ve eskiye bağlanır.
     * @return array{ok:bool,error?:string,id?:int}
     */
    public static function renew(int $documentId, array $data, array $files = []): array
    {
        $document = self::find($documentId);
        if ($document === null) {
            return ['ok' => false, 'error' => 'Evrak bulunamadı.'];
        }
        $vehicleId = (int) $document['vehicle_id'];

        // Yenilemede tür değişmez; tür verisi taşınır.
        $data['document_type_id'] = (int) $document['document_type_id'];
        $prepared = self::prepare($data, $document);
        if (!$prepared['ok']) {
            return $prepared;
        }
        unset($prepared['ok']);
        $prepared['vehicle_id']       = $vehicleId;
        $prepared['renewed_from_id']  = $documentId;
        $prepared['created_by']       = Auth::id();
        $prepared['created_at']       = date('Y-m-d H:i:s');

        try {
            $newId = Database::transaction(function () use ($documentId, $prepared, $files): int {
                $newId = Database::insert('vehicle_documents', $prepared);
                Database::update('vehicle_documents', [
                    'status'     => 'renewed',
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = :id', ['id' => $documentId]);
                self::attachFiles($newId, $files);
                return $newId;
            });
        } catch (\Throwable $e) {
            Logger::error('Evrak yenilenemedi: ' . $e->getMessage(), ['document' => $documentId]);
            return ['ok' => false, 'error' => 'Evrak yenilenemedi.'];
        }
        Audit::log('document.renew', 'vehicle_document', $newId, ['from' => $documentId, 'vehicle' => $vehicleId]);
        return ['ok' => true, 'id' => $newId];
    }

    /** Evrakı iptal et / kaldır. */
    public static function cancel(int $documentId): array
    {
        $document = self::find($documentId);
        if ($document === null) {
            return ['ok' => false, 'error' => 'Evrak bulunamadı.'];
        }
        Database::update('vehicle_documents', [
            'status'     => 'cancelled',
            'deleted_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $documentId]);
        Audit::log('document.cancel', 'vehicle_document', $documentId);
        return ['ok' => true];
    }

    public static function find(int $documentId): ?array
    {
        return Database::first('SELECT * FROM vehicle_documents WHERE id = ? AND deleted_at IS NULL LIMIT 1', [$documentId]);
    }

    /** Trafik ışığı seviyesi (kalan güne göre) - harici erişim için kısayol. */
    public static function light(int $documentId): string
    {
        $document = self::find($documentId);
        if ($document === null || (string) $document['status'] !== 'active') {
            return 'none';
        }
        return VehicleService::light(days_left((string) $document['end_date']));
    }

    /** Tek evrak için iCalendar (.ics) içeriği üretir (RFC 5545, tüm gün etkinlik). */
    public static function buildIcs(array $document, array $vehicle, ?array $type = null): string
    {
        $type = $type ?? self::type((int) $document['document_type_id']);
        $typeName = $type === null ? t('documents.title') : label((string) $type['labels_json']);
        $plate = (string) ($vehicle['plate'] ?? '');
        $end = (string) $document['end_date'];
        $endExclusive = date('Ymd', strtotime($end . ' +1 day') ?: time());
        $summary = trim($typeName . ($plate !== '' ? ' — ' . $plate : ''));
        $details = trim(sprintf(
            '%s%s%s',
            $vehicle['brand'] !== null || $vehicle['model'] !== null ? trim((string) $vehicle['brand'] . ' ' . (string) $vehicle['model']) . '\n' : '',
            !empty($document['issuer']) ? t('documents.issuer') . ': ' . (string) $document['issuer'] . '\n' : '',
            !empty($document['policy_no']) ? t('documents.policy_no') . ': ' . (string) $document['policy_no'] : ''
        ));

        $gmt = gmdate('Ymd\THis\Z');
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//evrako//Arac Evrak Takibi//TR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:evrak-' . (int) $document['id'] . '@evrako',
            'DTSTAMP:' . $gmt,
            'DTSTART;VALUE=DATE:' . ics_date((string) ($document['start_date'] ?? $end)),
            'DTEND;VALUE=DATE:' . $endExclusive,
            'SUMMARY:' . self::icsEscape($summary),
            'DESCRIPTION:' . self::icsEscape($details),
            'TRANSP:TRANSPARENT',
            'BEGIN:VALARM',
            'TRIGGER:-P7D',
            'ACTION:DISPLAY',
            'DESCRIPTION:' . self::icsEscape($summary),
            'END:VALARM',
            'END:VEVENT',
            'END:VCALENDAR',
        ];
        return implode("\r\n", $lines) . "\r\n";
    }

    private static function icsEscape(string $value): string
    {
        $value = str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $value);
        return str_replace(["\r\n", "\r", "\n"], '\\n', $value);
    }

    /** @return array<int,array> */
    public static function attachFiles(int $documentId, array $files): array
    {
        $stored = [];
        foreach ($files as $file) {
            if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $result = Uploader::store($file, 'document', [
                'type'      => 'document',
                'max_bytes' => (int) Settings::get('uploads.max_document_bytes', 12 * 1024 * 1024),
            ]);
            if (!$result['ok']) {
                Logger::warning('Evrak belgesi yüklenemedi: ' . (string) ($result['error'] ?? ''));
                continue;
            }
            $mediaId = (int) $result['media']['id'];
            $fileId = Database::insert('document_files', [
                'document_id' => $documentId,
                'media_id'    => $mediaId,
                'label'       => null,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
            $stored[] = ['id' => $fileId, 'media_id' => $mediaId];
        }
        return $stored;
    }

    public static function deleteFile(int $documentId, int $fileId): array
    {
        $file = Database::first('SELECT * FROM document_files WHERE id = ? AND document_id = ? LIMIT 1', [$fileId, $documentId]);
        if ($file === null) {
            return ['ok' => false, 'error' => 'Belge bulunamadı.'];
        }
        Uploader::delete((int) $file['media_id']);
        Database::delete('document_files', 'id = ?', [$fileId]);
        Audit::log('document.file_delete', 'vehicle_document', $documentId, ['file' => $fileId]);
        return ['ok' => true];
    }

    /**
     * Girdi doğrulama + alan hazırlama.
     * @param array<string,mixed> $data
     * @param array|null $fallback eski kayıt (yenilemede varsayılanlar için)
     * @return array{ok:bool,error?:string}
     */
    private static function prepare(array $data, ?array $fallback = null): array
    {
        $typeId = (int) ($data['document_type_id'] ?? ($fallback['document_type_id'] ?? 0));
        $type = self::type($typeId);
        if ($type === null) {
            return ['ok' => false, 'error' => t('documents.invalid_type')];
        }

        $start = self::dateOrNull($data['start_date'] ?? null);
        $end   = self::dateOrNull($data['end_date'] ?? null);
        if ($end === null) {
            return ['ok' => false, 'error' => t('documents.end_required')];
        }
        if ($start !== null && $start > $end) {
            return ['ok' => false, 'error' => t('documents.end_before_start')];
        }

        $offsets = trim((string) ($data['reminder_offsets'] ?? ''));
        if ($offsets !== '' && !preg_match('/^\d{1,3}(,\d{1,3})*$/', $offsets)) {
            return ['ok' => false, 'error' => t('documents.offsets_invalid')];
        }

        return [
            'ok'               => true,
            'document_type_id' => $typeId,
            'issuer'           => self::text($data['issuer'] ?? null, 160),
            'policy_no'        => self::text($data['policy_no'] ?? null, 80),
            'start_date'       => $start,
            'end_date'         => $end,
            'cost'             => self::money($data['cost'] ?? null),
            'currency'         => self::currency($data['currency'] ?? null),
            'reminder_offsets' => $offsets === '' ? null : mb_substr($offsets, 0, 60),
            'notes'            => self::text($data['notes'] ?? null, 2000),
            'status'           => 'active',
        ];
    }

    private static function dateOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $ts = strtotime((string) $value);
        return $ts === false ? null : date('Y-m-d', $ts);
    }

    private static function text(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private static function money(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $clean = str_replace([' ', ','], ['', '.'], (string) $value);
        return is_numeric($clean) ? round((float) $clean, 2) : null;
    }

    private static function currency(mixed $value): string
    {
        $value = strtoupper(trim((string) $value));
        return in_array($value, ['TRY', 'USD', 'EUR', 'GBP'], true) ? $value : (string) Settings::get('payment.currency', 'TRY');
    }
}
