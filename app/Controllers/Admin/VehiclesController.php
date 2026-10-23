<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Flash;
use App\Services\DocumentService;
use App\Services\VehicleService;

/**
 * Yönetim paneli araç gözetimi: tüm kurumların araçları, evrak durumu,
 * sahip/kurum bilgisi, araç detayı ve durum (aktif/satıldı/arşiv) yönetimi.
 */
final class VehiclesController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function index(): string
    {
        $q      = trim((string) $this->request->input('q', ''));
        $typeId = $this->request->int('tur', 0);
        $status = (string) $this->request->input('durum', '');
        $vade   = (string) $this->request->input('vade', '');
        $page   = max(1, $this->request->int('page', 1));
        $perPage = 25;

        $where  = ['v.deleted_at IS NULL'];
        $params = [];

        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(v.plate LIKE ? OR v.brand LIKE ? OR v.model LIKE ? OR v.driver_name LIKE ?
                         OR o.name LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like, $like, $like);
        }
        if ($typeId > 0) {
            $where[] = 'v.vehicle_type_id = ?';
            $params[] = $typeId;
        }
        if (in_array($status, ['active', 'sold', 'archived'], true)) {
            $where[] = 'v.status = ?';
            $params[] = $status;
        }
        $expiryWhere = [
            'gecmis'    => "EXISTS (SELECT 1 FROM vehicle_documents d WHERE d.vehicle_id = v.id AND d.status = 'active' AND d.deleted_at IS NULL AND d.end_date < CURDATE())",
            '7'         => "EXISTS (SELECT 1 FROM vehicle_documents d WHERE d.vehicle_id = v.id AND d.status = 'active' AND d.deleted_at IS NULL AND d.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY))",
            '30'        => "EXISTS (SELECT 1 FROM vehicle_documents d WHERE d.vehicle_id = v.id AND d.status = 'active' AND d.deleted_at IS NULL AND d.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY))",
            'evrak-yok' => "NOT EXISTS (SELECT 1 FROM vehicle_documents d WHERE d.vehicle_id = v.id AND d.status = 'active' AND d.deleted_at IS NULL)",
        ];
        if (isset($expiryWhere[$vade])) {
            $where[] = $expiryWhere[$vade];
        }

        $whereSql = implode(' AND ', $where);
        $baseFrom = "FROM vehicles v
                     JOIN vehicle_types vt ON vt.id = v.vehicle_type_id
                     JOIN organizations o ON o.id = v.organization_id
                     LEFT JOIN users u ON u.id = o.owner_user_id
                     WHERE {$whereSql}";

        $total = (int) Database::scalar("SELECT COUNT(*) {$baseFrom}", $params);
        $rows = Database::select(
            "SELECT v.*, vt.labels_json AS type_labels, vt.icon AS type_icon,
                    o.name AS organization_name, o.id AS organization_id,
                    u.id AS owner_id, u.full_name AS owner_name, u.email AS owner_email,
                    (SELECT MIN(d.end_date) FROM vehicle_documents d
                      WHERE d.vehicle_id = v.id AND d.status = 'active' AND d.deleted_at IS NULL) AS next_end,
                    (SELECT COUNT(*) FROM vehicle_documents d
                      WHERE d.vehicle_id = v.id AND d.status = 'active' AND d.deleted_at IS NULL) AS active_docs
             {$baseFrom}
             ORDER BY v.created_at DESC, v.id DESC
             LIMIT " . ($perPage + 1) . ' OFFSET ' . (($page - 1) * $perPage),
            $params
        );
        $hasNext = count($rows) > $perPage;
        if ($hasNext) {
            array_pop($rows);
        }

        foreach ($rows as &$row) {
            $row['days_left']  = days_left((string) ($row['next_end'] ?? ''));
            $row['light']      = VehicleService::light($row['days_left']);
            $row['type_label'] = label((string) $row['type_labels']);
        }
        unset($row);

        $totals = [
            'vehicles'    => (int) Database::scalar('SELECT COUNT(*) FROM vehicles WHERE deleted_at IS NULL'),
            'active'      => (int) Database::scalar("SELECT COUNT(*) FROM vehicles WHERE deleted_at IS NULL AND status = 'active'"),
            'active_docs' => (int) Database::scalar("SELECT COUNT(*) FROM vehicle_documents WHERE status = 'active' AND deleted_at IS NULL"),
            'expiring30'  => (int) Database::scalar("SELECT COUNT(*) FROM vehicle_documents WHERE status = 'active' AND deleted_at IS NULL AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"),
            'expired'     => (int) Database::scalar("SELECT COUNT(*) FROM vehicle_documents WHERE status = 'active' AND deleted_at IS NULL AND end_date < CURDATE()"),
        ];

        return $this->view('admin/vehicles/index', [
            'title'   => t('admin.vehicles'),
            'rows'    => $rows,
            'totals'  => $totals,
            'types'   => VehicleService::types(false),
            'filters' => ['q' => $q, 'type_id' => $typeId, 'status' => $status, 'vade' => $vade],
            'page'    => $page,
            'hasNext' => $hasNext,
            'total'   => $total,
        ]);
    }

    public function show(string $id): string
    {
        $id = (int) $id;
        $vehicle = Database::first(
            "SELECT v.*, vt.labels_json AS type_labels, vt.icon AS type_icon,
                    o.id AS organization_id, o.name AS organization_name, o.credit_balance,
                    u.id AS owner_id, u.full_name AS owner_name, u.email AS owner_email, u.phone AS owner_phone,
                    (SELECT pl.name FROM subscriptions s JOIN plans pl ON pl.id = s.plan_id
                      WHERE s.organization_id = o.id AND s.status IN ('active','trialing','past_due')
                      ORDER BY s.id DESC LIMIT 1) AS plan_name
             FROM vehicles v
             JOIN vehicle_types vt ON vt.id = v.vehicle_type_id
             JOIN organizations o ON o.id = v.organization_id
             LEFT JOIN users u ON u.id = o.owner_user_id
             WHERE v.id = ? AND v.deleted_at IS NULL LIMIT 1",
            [$id]
        );

        if ($vehicle === null) {
            Flash::error('Araç bulunamadı.');
            $this->redirect('/yonetim/araclar');
        }

        $documents = DocumentService::forVehicle($id);
        $notifications = Database::select(
            "SELECT id, channel, template_key, recipient, subject, status, created_at
             FROM notifications WHERE vehicle_id = ? ORDER BY id DESC LIMIT 12",
            [$id]
        );

        return $this->view('admin/vehicles/show', [
            'title'         => 'Araç: ' . (string) $vehicle['plate'],
            'vehicle'       => $vehicle,
            'typeLabel'     => label((string) $vehicle['type_labels']),
            'documents'     => $documents,
            'notifications' => $notifications,
            'nextEnd'       => $documents[0]['end_date'] ?? null,
        ]);
    }

    /** Araç durumunu günceller (satıldı/arşiv/aktif). */
    public function updateStatus(): void
    {
        $id     = $this->request->int('id');
        $status = (string) $this->request->input('status', '');

        if (!in_array($status, ['active', 'sold', 'archived'], true)) {
            $this->flashError('Geçersiz araç durumu.');
            $this->redirect('/yonetim/araclar/' . $id);
        }

        $vehicle = Database::first('SELECT id, plate, status FROM vehicles WHERE id = ? AND deleted_at IS NULL LIMIT 1', [$id]);
        if ($vehicle === null) {
            $this->flashError('Araç bulunamadı.');
            $this->redirect('/yonetim/araclar');
        }

        Database::run('UPDATE vehicles SET status = ?, updated_at = ? WHERE id = ?', [$status, date('Y-m-d H:i:s'), $id]);
        Audit::log('vehicle.status', 'vehicle', $id, ['plate' => (string) $vehicle['plate'], 'from' => (string) $vehicle['status'], 'to' => $status]);
        $this->flashSuccess('Araç durumu güncellendi: ' . $status);
        $this->redirect('/yonetim/araclar/' . $id);
    }
}
