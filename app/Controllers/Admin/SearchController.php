<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Database;

/**
 * Yönetim paneli hızlı arama: müşteriyi ad, e-posta veya telefonla bulur.
 * Araç plakası ve kurum adı da aranır; sonuçlar ilgili kayda bağlantı verir.
 */
final class SearchController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function index(): string
    {
        $q = trim((string) $this->request->input('q', ''));
        $users = [];
        $organizations = [];
        $vehicles = [];

        if ($q !== '') {
            $like = '%' . $q . '%';
            $users = Database::select(
                "SELECT u.id, u.full_name, u.email, u.phone, u.status, u.role, u.created_at, u.last_login_at,
                        o.id AS organization_id, o.name AS organization_name,
                        (SELECT COUNT(*) FROM vehicles v WHERE v.organization_id = o.id AND v.deleted_at IS NULL) AS vehicle_count
                 FROM users u
                 LEFT JOIN organizations o ON o.owner_user_id = u.id
                 WHERE u.deleted_at IS NULL
                   AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.whatsapp LIKE ?)
                 ORDER BY u.id DESC LIMIT 25",
                [$like, $like, $like, $like]
            );

            $organizations = Database::select(
                'SELECT o.id, o.name, o.phone, o.city, u.full_name AS owner_name, u.email AS owner_email
                 FROM organizations o LEFT JOIN users u ON u.id = o.owner_user_id
                 WHERE o.name LIKE ? ORDER BY o.name ASC LIMIT 15',
                [$like]
            );

            $vehicles = Database::select(
                'SELECT v.id, v.plate, v.brand, v.model, v.organization_id, o.name AS organization_name
                 FROM vehicles v LEFT JOIN organizations o ON o.id = v.organization_id
                 WHERE v.deleted_at IS NULL AND v.plate LIKE ? ORDER BY v.id DESC LIMIT 15',
                [$like]
            );
        }

        return $this->view('admin/search', [
            'title'         => 'Arama',
            'q'             => $q,
            'users'         => $users,
            'organizations' => $organizations,
            'vehicles'      => $vehicles,
        ]);
    }
}
