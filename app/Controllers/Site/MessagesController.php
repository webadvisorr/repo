<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Services\ContactMessageService;

/**
 * Müşteri paneli — iletişim formu mesajlarının takibi ve yanıtlanması.
 */
final class MessagesController extends Controller
{
    public function index(): string
    {
        $user = Auth::user();
        $rows = ContactMessageService::forUser((int) ($user['id'] ?? 0), (string) ($user['email'] ?? ''));

        return $this->view('site/messages', [
            'title' => 'Mesajlarım',
            'rows'  => $rows,
        ]);
    }

    public function show(string $id): string
    {
        $user = Auth::user();
        $message = ContactMessageService::findForUser(
            (int) $id,
            (int) ($user['id'] ?? 0),
            (string) ($user['email'] ?? '')
        );
        if ($message === null) {
            $this->flashError('Mesaj bulunamadı.');
            $this->redirect('/panel/mesajlar');
        }

        Database::run('UPDATE contact_messages SET is_read = 1 WHERE id = ?', [(int) $message['id']]);

        return $this->view('site/message_show', [
            'title'   => (string) ($message['subject'] ?: 'Mesaj'),
            'message' => $message,
            'replies' => ContactMessageService::replies((int) $message['id']),
        ]);
    }

    public function reply(): void
    {
        $user = Auth::user();
        $id = (int) $this->request->input('id', 0);
        $message = ContactMessageService::findForUser(
            $id,
            (int) ($user['id'] ?? 0),
            (string) ($user['email'] ?? '')
        );
        if ($message === null) {
            $this->flashError('Mesaj bulunamadı.');
            $this->redirect('/panel/mesajlar');
        }

        $result = ContactMessageService::reply($id, 'customer', (int) ($user['id'] ?? 0), (string) $this->request->input('body', ''));
        if ($result['ok']) {
            $this->flashSuccess('Yanıtınız gönderildi.');
        } else {
            $this->flashError((string) ($result['error'] ?? 'Yanıt gönderilemedi.'));
        }
        $this->redirect('/panel/mesajlar/' . $id);
    }
}
