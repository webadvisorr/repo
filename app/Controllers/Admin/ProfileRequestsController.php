<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Services\ProfileChangeService;

/**
 * Yönetim paneli — müşteri profil güncelleme talepleri.
 * Onaylanan talepler hesaba uygulanır; e-posta değişiminde doğrulama yenilenir.
 */
final class ProfileRequestsController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function index(): string
    {
        return $this->view('admin/profile_requests', [
            'title'   => 'Profil talepleri',
            'pending' => ProfileChangeService::pendingList(),
            'history' => ProfileChangeService::history(80),
        ]);
    }

    public function approve(): void
    {
        $id = $this->request->int('id');
        $note = trim((string) $this->request->input('note', ''));
        $result = ProfileChangeService::approve($id, $note !== '' ? $note : null);
        if (!$result['ok']) {
            $this->flashError((string) $result['error']);
            $this->redirect('/yonetim/profil-talepleri');
        }

        $message = !empty($result['email_changed'])
            ? 'Talep onaylandı. Yeni e-posta adresi için doğrulama kodu gönderildi.'
            : 'Talep onaylandı ve bilgiler güncellendi.';
        $this->flashSuccess($message);
        $this->redirect('/yonetim/profil-talepleri');
    }

    public function reject(): void
    {
        $id = $this->request->int('id');
        $note = trim((string) $this->request->input('note', ''));
        $result = ProfileChangeService::reject($id, $note !== '' ? $note : null);
        if (!$result['ok']) {
            $this->flashError((string) $result['error']);
            $this->redirect('/yonetim/profil-talepleri');
        }
        $this->flashSuccess('Talep reddedildi; müşteriye bilgi verildi.');
        $this->redirect('/yonetim/profil-talepleri');
    }
}
