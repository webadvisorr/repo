<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Services\SupportService;

/**
 * Yönetim paneli — destek talepleri.
 * Müşteri taleplerini listeler, konuşmayı gösterir, yanıtlar ve durum değiştirir.
 */
final class SupportController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function index(): string
    {
        $filters = [
            'q'      => trim((string) $this->request->input('q', '')),
            'status' => (string) $this->request->input('durum', ''),
            'from'   => (string) (db_date($this->request->input('baslangic', '')) ?? ''),
            'to'     => (string) (db_date($this->request->input('bitis', '')) ?? ''),
        ];
        $page = max(1, (int) $this->request->input('page', 1));
        $list = SupportService::adminList($filters, $page, 25);

        return $this->view('admin/support/index', [
            'title'   => 'Destek talepleri',
            'rows'    => $list['rows'],
            'total'   => $list['total'],
            'page'    => $list['page'],
            'pages'   => $list['pages'],
            'filters' => $filters,
            'stats'   => SupportService::adminStats(),
        ]);
    }

    public function show(string $id): string
    {
        $ticket = $this->findOrRedirect((int) $id);
        SupportService::markReadByAdmin((int) $ticket['id']);

        return $this->view('admin/support/show', [
            'title'    => 'Destek: ' . (string) $ticket['ticket_no'],
            'ticket'   => $ticket,
            'messages' => SupportService::messages((int) $ticket['id']),
        ]);
    }

    public function reply(): void
    {
        $ticket = $this->findOrRedirect((int) $this->request->input('id', 0));
        $result = SupportService::reply((int) $ticket['id'], 'admin', (int) (Auth::id() ?? 0), (string) $this->request->input('body', ''));
        if ($result['ok']) {
            $this->flashSuccess('Yanıt gönderildi. Müşteri panelinde görünecek.');
        } else {
            $this->flashError((string) ($result['error'] ?? 'Yanıt gönderilemedi.'));
        }
        $this->redirect('/yonetim/destek/' . (int) $ticket['id']);
    }

    public function status(): void
    {
        $ticket = $this->findOrRedirect((int) $this->request->input('id', 0));
        $status = (string) $this->request->input('status', '');
        if (SupportService::setStatus((int) $ticket['id'], $status)) {
            $this->flashSuccess('Talep durumu güncellendi: ' . SupportService::statusLabel($status));
        } else {
            $this->flashError('Geçersiz durum.');
        }
        $this->redirect('/yonetim/destek/' . (int) $ticket['id']);
    }

    private function findOrRedirect(int $id): array
    {
        $ticket = SupportService::find($id);
        if ($ticket === null) {
            $this->flashError('Destek talebi bulunamadı.');
            $this->redirect('/yonetim/destek');
        }
        return $ticket;
    }
}
