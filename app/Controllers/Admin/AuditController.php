<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Database;

/**
 * Denetim kaydı: yönetici ve sistem işlemlerini kullanıcı, işlem, varlık ve
 * tarih aralığına göre filtreler; meta JSON'u satır detayında gösterir.
 */
final class AuditController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function index(): string
    {
        $q      = trim((string) $this->request->input('q', ''));
        $action = trim((string) $this->request->input('islem', ''));
        $entity = trim((string) $this->request->input('varlik', ''));
        $userId = $this->request->int('kullanici', 0);
        $days   = $this->request->int('gun', 0);
        $from   = (string) (db_date($this->request->input('baslangic', '')) ?? '');
        $to     = (string) (db_date($this->request->input('bitis', '')) ?? '');
        $page   = max(1, $this->request->int('page', 1));
        $perPage = 50;

        $where  = ['1 = 1'];
        $params = [];

        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(a.action LIKE ? OR a.entity LIKE ? OR a.entity_id LIKE ? OR a.ip LIKE ? OR a.meta_json LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if ($action !== '') {
            $where[] = 'a.action = ?';
            $params[] = $action;
        }
        if ($entity !== '') {
            $where[] = 'a.entity = ?';
            $params[] = $entity;
        }
        if ($userId > 0) {
            $where[] = 'a.user_id = ?';
            $params[] = $userId;
        }
        if (in_array($days, [1, 7, 30, 90], true)) {
            $where[] = 'a.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
            $params[] = $days;
        }
        if ($from !== '') {
            $where[] = 'a.created_at >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== '') {
            $where[] = 'a.created_at <= ?';
            $params[] = $to . ' 23:59:59';
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Database::scalar("SELECT COUNT(*) FROM audit_logs a WHERE {$whereSql}", $params);

        $rows = Database::select(
            "SELECT a.*, u.full_name, u.email
             FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
             WHERE {$whereSql}
             ORDER BY a.id DESC
             LIMIT " . ($perPage + 1) . ' OFFSET ' . (($page - 1) * $perPage),
            $params
        );
        $hasNext = count($rows) > $perPage;
        if ($hasNext) {
            array_pop($rows);
        }

        $actions = array_column(
            Database::select('SELECT DISTINCT action FROM audit_logs ORDER BY action ASC LIMIT 120'),
            'action'
        );
        $entities = array_column(
            Database::select('SELECT DISTINCT entity FROM audit_logs WHERE entity IS NOT NULL ORDER BY entity ASC LIMIT 80'),
            'entity'
        );
        $users = Database::select(
            "SELECT DISTINCT u.id, u.full_name, u.email
             FROM audit_logs a JOIN users u ON u.id = a.user_id
             ORDER BY u.full_name ASC LIMIT 80"
        );

        $totals = [
            'all'    => (int) Database::scalar('SELECT COUNT(*) FROM audit_logs'),
            'today'  => (int) Database::scalar('SELECT COUNT(*) FROM audit_logs WHERE created_at >= CURDATE()'),
            'hour'   => (int) Database::scalar('SELECT COUNT(*) FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'),
            'actors' => (int) Database::scalar('SELECT COUNT(DISTINCT user_id) FROM audit_logs WHERE user_id IS NOT NULL'),
        ];

        return $this->view('admin/audit/index', [
            'title'    => t('admin.audit'),
            'rows'     => $rows,
            'totals'   => $totals,
            'actions'  => $actions,
            'entities' => $entities,
            'users'    => $users,
            'filters'  => [
                'q' => $q, 'action' => $action, 'entity' => $entity,
                'user_id' => $userId, 'days' => $days, 'from' => $from, 'to' => $to,
            ],
            'page'    => $page,
            'hasNext' => $hasNext,
            'total'   => $total,
        ]);
    }
}
