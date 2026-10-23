<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Services\SupportService;

/**
 * Müşteri paneli — destek talepleri.
 */
final class SupportController extends Controller
{
    public function index(): string
    {
        $userId = (int) (Auth::id() ?? 0);

        return $this->view('site/support', [
            'title'   => 'Destek taleplerim',
            'rows'    => SupportService::forUser($userId),
            'pending' => SupportService::pendingForUser($userId),
        ]);
    }

    /** Yeni talep formu — yanıt bekleyen talep varsa forma izin verilmez. */
    public function create(): string
    {
        $userId = (int) (Auth::id() ?? 0);
        $pending = SupportService::pendingForUser($userId);
        if ($pending !== null) {
            $this->flashError('Yanıt bekleyen bir destek talebiniz var. Destek ekibimiz yanıtladıktan sonra yeni talep açabilirsiniz.');
            $this->redirect('/panel/destek/' . (int) $pending['id']);
        }

        return $this->view('site/support_form', [
            'title' => 'Yeni destek talebi',
        ]);
    }

    public function store(): void
    {
        $data = $this->validate(
            [
                'subject' => trim((string) $this->request->string('subject', '')),
                'body'    => trim((string) $this->request->input('body', '')),
            ],
            ['subject' => 'required|min:3|max:200', 'body' => 'required|min:10|max:5000'],
            ['subject' => 'Konu', 'body' => 'Mesaj'],
            '/panel/destek/yeni'
        );

        $userId = (int) (Auth::id() ?? 0);
        $pending = SupportService::pendingForUser($userId);
        if ($pending !== null) {
            $this->flashError('Yanıt bekleyen bir destek talebiniz var. Destek ekibimiz yanıtladıktan sonra yeni talep açabilirsiniz.');
            $this->redirect('/panel/destek/' . (int) $pending['id']);
        }
        $organizationId = (int) Database::scalar(
            'SELECT organization_id FROM organization_members WHERE user_id = ? AND status = "active" LIMIT 1',
            [$userId]
        );

        $result = SupportService::create($userId, [
            'subject'         => $data['subject'],
            'body'            => $data['body'],
            'category'        => (string) $this->request->input('category', 'other'),
            'priority'        => (string) $this->request->input('priority', 'normal'),
            'organization_id' => $organizationId,
        ]);

        if (!$result['ok']) {
            $this->flashError((string) ($result['error'] ?? 'Talep oluşturulamadı.'));
            $this->redirect(isset($result['pending']) ? '/panel/destek/' . (int) $result['pending'] : '/panel/destek/yeni');
        }

        $this->flashSuccess('Destek talebiniz açıldı. Yanıtlar bu sayfada görünecek.');
        $this->redirect('/panel/destek/' . (int) $result['id']);
    }

    public function show(string $id): string
    {
        $ticket = $this->ownTicket((int) $id);
        SupportService::markReadByCustomer((int) $ticket['id']);

        return $this->view('site/support_show', [
            'title'    => 'Talep ' . (string) $ticket['ticket_no'],
            'ticket'   => $ticket,
            'messages' => SupportService::messages((int) $ticket['id']),
        ]);
    }

    public function reply(): void
    {
        $ticket = $this->ownTicket((int) $this->request->input('id', 0));
        $result = SupportService::reply((int) $ticket['id'], 'customer', (int) (Auth::id() ?? 0), (string) $this->request->input('body', ''));
        if ($result['ok']) {
            $this->flashSuccess('Yanıtınız gönderildi.');
        } else {
            $this->flashError((string) ($result['error'] ?? 'Yanıt gönderilemedi.'));
        }
        $this->redirect('/panel/destek/' . (int) $ticket['id']);
    }

    public function close(): void
    {
        $ticket = $this->ownTicket((int) $this->request->input('id', 0));
        SupportService::setStatus((int) $ticket['id'], 'closed');
        $this->flashSuccess('Talep kapatıldı.');
        $this->redirect('/panel/destek/' . (int) $ticket['id']);
    }

    private function ownTicket(int $id): array
    {
        $ticket = SupportService::find($id);
        if ($ticket === null || (int) $ticket['user_id'] !== (int) (Auth::id() ?? 0)) {
            $this->flashError('Destek talebi bulunamadı.');
            $this->redirect('/panel/destek');
        }
        return $ticket;
    }
}
