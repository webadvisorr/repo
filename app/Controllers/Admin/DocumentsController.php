<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Flash;
use App\Services\DocumentService;
use App\Services\VehicleService;

/**
 * Yönetim paneli evrak gözetimi: tüm kurumların evrakları, süre durumu,
 * yüklenen dosyalar ve evrak için gönderilen bildirimler.
 */
final class DocumentsController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function index(): string
    {
        $q      = trim((string) $this->request->input('q', ''));
        $typeId = $this->request->int('tur', 0);
        $status = (string) $this->request->input('durum', '');
        $vade   = (string) $this->request->input('vade', '');
        $page   = max(1, $this->request->int('page', 1));
        $perPage = 30;

        $where  = ['d.deleted_at IS NULL'];
        $params = [];

        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(v.plate LIKE ? OR d.policy_no LIKE ? OR d.issuer LIKE ? OR dt.labels_json LIKE ?
                         OR o.name LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like, $like, $like);
        }
        if ($typeId > 0) {
            $where[] = 'd.document_type_id = ?';
            $params[] = $typeId;
        }
        if (in_array($status, DocumentService::STATUSES, true)) {
            $where[] = 'd.status = ?';
            $params[] = $status;
        }
        $vadeWhere = [
            'gecmis'    => "d.status = 'active' AND d.end_date < CURDATE()",
            '7'         => "d.status = 'active' AND d.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)",
            '30'        => "d.status = 'active' AND d.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)",
            'yaklasan'  => "d.status = 'active' AND d.end_date >= CURDATE()",
        ];
        if (isset($vadeWhere[$vade])) {
            $where[] = $vadeWhere[$vade];
        }

        $whereSql = implode(' AND ', $where);
        $baseFrom = "FROM vehicle_documents d
                     JOIN document_types dt ON dt.id = d.document_type_id
                     JOIN vehicles v ON v.id = d.vehicle_id
                     JOIN organizations o ON o.id = v.organization_id
                     LEFT JOIN users u ON u.id = o.owner_user_id
                     WHERE {$whereSql}";

        $total = (int) Database::scalar("SELECT COUNT(*) {$baseFrom}", $params);
        $rows = Database::select(
            "SELECT d.*, dt.labels_json AS type_labels, dt.color AS type_color, dt.requires_file,
                    v.plate, v.id AS vehicle_id, v.brand, v.model,
                    o.name AS organization_name, o.id AS organization_id,
                    u.id AS owner_id, u.full_name AS owner_name, u.email AS owner_email,
                    (SELECT COUNT(*) FROM document_files df WHERE df.document_id = d.id) AS files_count
             {$baseFrom}
             ORDER BY FIELD(d.status, 'active', 'renewed', 'cancelled'), d.end_date ASC, d.id DESC
             LIMIT " . ($perPage + 1) . ' OFFSET ' . (($page - 1) * $perPage),
            $params
        );
        $hasNext = count($rows) > $perPage;
        if ($hasNext) {
            array_pop($rows);
        }

        foreach ($rows as &$row) {
            $row['days_left']  = days_left((string) $row['end_date']);
            $row['light']      = (string) $row['status'] === 'active' ? VehicleService::light($row['days_left']) : 'none';
            $row['type_label'] = label((string) $row['type_labels']);
        }
        unset($row);

        $totals = [
            'active'     => (int) Database::scalar("SELECT COUNT(*) FROM vehicle_documents WHERE status = 'active' AND deleted_at IS NULL"),
            'expiring30' => (int) Database::scalar("SELECT COUNT(*) FROM vehicle_documents WHERE status = 'active' AND deleted_at IS NULL AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"),
            'expired'    => (int) Database::scalar("SELECT COUNT(*) FROM vehicle_documents WHERE status = 'active' AND deleted_at IS NULL AND end_date < CURDATE()"),
            'files'      => (int) Database::scalar('SELECT COUNT(*) FROM document_files'),
        ];

        return $this->view('admin/documents/index', [
            'title'   => t('admin.documents'),
            'rows'    => $rows,
            'totals'  => $totals,
            'types'   => DocumentService::types(),
            'filters' => ['q' => $q, 'type_id' => $typeId, 'status' => $status, 'vade' => $vade],
            'page'    => $page,
            'hasNext' => $hasNext,
            'total'   => $total,
        ]);
    }

    public function show(string $id): string
    {
        $id = (int) $id;
        $document = Database::first(
            "SELECT d.*, dt.labels_json AS type_labels, dt.color AS type_color, dt.requires_file,
                    v.id AS vehicle_id, v.plate, v.brand, v.model,
                    o.id AS organization_id, o.name AS organization_name,
                    u.id AS owner_id, u.full_name AS owner_name, u.email AS owner_email
             FROM vehicle_documents d
             JOIN document_types dt ON dt.id = d.document_type_id
             JOIN vehicles v ON v.id = d.vehicle_id
             JOIN organizations o ON o.id = v.organization_id
             LEFT JOIN users u ON u.id = o.owner_user_id
             WHERE d.id = ? AND d.deleted_at IS NULL LIMIT 1",
            [$id]
        );

        if ($document === null) {
            Flash::error('Evrak bulunamadı.');
            $this->redirect('/yonetim/evraklar');
        }

        $notifications = Database::select(
            "SELECT id, channel, template_key, recipient, subject, status, created_at
             FROM notifications WHERE document_id = ? ORDER BY id DESC LIMIT 20",
            [$id]
        );

        return $this->view('admin/documents/show', [
            'title'         => 'Evrak: ' . label((string) $document['type_labels']) . ' · ' . (string) $document['plate'],
            'document'      => $document,
            'typeLabel'     => label((string) $document['type_labels']),
            'daysLeft'      => days_left((string) $document['end_date']),
            'files'         => DocumentService::files($id),
            'notifications' => $notifications,
        ]);
    }
}
