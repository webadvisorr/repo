<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Settings;

final class DashboardController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function index(): string
    {
        $stats = [
            'users'      => (int) Database::scalar('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL'),
            'customers'  => (int) Database::scalar("SELECT COUNT(*) FROM users WHERE role = 'customer' AND deleted_at IS NULL"),
            'vehicles'   => (int) Database::scalar('SELECT COUNT(*) FROM vehicles WHERE deleted_at IS NULL'),
            'documents'  => (int) Database::scalar("SELECT COUNT(*) FROM vehicle_documents WHERE status = 'active' AND deleted_at IS NULL"),
            'expiring30' => (int) Database::scalar("SELECT COUNT(*) FROM vehicle_documents WHERE status = 'active' AND deleted_at IS NULL AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"),
            'expired'    => (int) Database::scalar("SELECT COUNT(*) FROM vehicle_documents WHERE status = 'active' AND deleted_at IS NULL AND end_date < CURDATE()"),
            'mailsToday' => (int) Database::scalar("SELECT COUNT(*) FROM notifications WHERE channel = 'email' AND DATE(created_at) = CURDATE()"),
            'mailFailed' => (int) Database::scalar("SELECT COUNT(*) FROM notifications WHERE status = 'failed'"),
            'messages'   => (int) Database::scalar('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0'),
        ];

        $upcoming = Database::select(
            "SELECT d.end_date, v.plate, dt.labels_json, u.email AS owner_email
             FROM vehicle_documents d
             JOIN vehicles v ON v.id = d.vehicle_id
             JOIN document_types dt ON dt.id = d.document_type_id
             LEFT JOIN organizations o ON o.id = v.organization_id
             LEFT JOIN users u ON u.id = o.owner_user_id
             WHERE d.status = 'active' AND d.deleted_at IS NULL
             ORDER BY d.end_date ASC LIMIT 12"
        );

        $audit = Database::select(
            'SELECT a.*, u.full_name, u.email FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 10'
        );

        return $this->view('admin/dashboard', [
            'title'          => t('admin.dashboard'),
            'stats'          => $stats,
            'upcoming'       => $upcoming,
            'audit'          => $audit,
            'smtpConfigured' => trim((string) Settings::get('smtp.host', '')) !== '',
        ]);
    }
}
