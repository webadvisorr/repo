<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Flash;
use App\Services\ContactMessageService;

/**
 * İletişim formundan gelen mesajlar: okunmamış filtresi, arama,
 * detay (otomatik okundu), okundu/okunmadı işaretleme ve silme.
 */
final class MessagesController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function index(): string
    {
        $q      = trim((string) $this->request->input('q', ''));
        $status = (string) $this->request->input('durum', '');
        $from   = (string) (db_date($this->request->input('baslangic', '')) ?? '');
        $to     = (string) (db_date($this->request->input('bitis', '')) ?? '');
        $page   = max(1, $this->request->int('page', 1));
        $perPage = 25;

        $where  = ['1 = 1'];
        $params = [];

        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(m.name LIKE ? OR m.email LIKE ? OR m.phone LIKE ? OR m.subject LIKE ? OR m.message LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if ($status === 'okunmamis') {
            $where[] = 'm.is_read = 0';
        } elseif ($status === 'okunmus') {
            $where[] = 'm.is_read = 1';
        }
        if ($from !== '') {
            $where[] = 'm.created_at >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== '') {
            $where[] = 'm.created_at <= ?';
            $params[] = $to . ' 23:59:59';
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Database::scalar("SELECT COUNT(*) FROM contact_messages m WHERE {$whereSql}", $params);
        $rows = Database::select(
            "SELECT m.* FROM contact_messages m WHERE {$whereSql}
             ORDER BY m.is_read ASC, m.id DESC
             LIMIT " . ($perPage + 1) . ' OFFSET ' . (($page - 1) * $perPage),
            $params
        );
        $hasNext = count($rows) > $perPage;
        if ($hasNext) {
            array_pop($rows);
        }

        $totals = [
            'all'     => (int) Database::scalar('SELECT COUNT(*) FROM contact_messages'),
            'unread'  => (int) Database::scalar('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0'),
            'today'   => (int) Database::scalar('SELECT COUNT(*) FROM contact_messages WHERE created_at >= CURDATE()'),
            'week'    => (int) Database::scalar('SELECT COUNT(*) FROM contact_messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'),
        ];

        return $this->view('admin/messages/index', [
            'title'   => 'Mesajlar',
            'rows'    => $rows,
            'totals'  => $totals,
            'filters' => ['q' => $q, 'status' => $status, 'from' => $from, 'to' => $to],
            'page'    => $page,
            'hasNext' => $hasNext,
            'total'   => $total,
        ]);
    }

    public function show(string $id): string
    {
        $id = (int) $id;
        $message = Database::first('SELECT * FROM contact_messages WHERE id = ? LIMIT 1', [$id]);
        if ($message === null) {
            Flash::error('Mesaj bulunamadı.');
            $this->redirect('/yonetim/mesajlar');
        }

        if ((int) $message['is_read'] === 0) {
            Database::run('UPDATE contact_messages SET is_read = 1 WHERE id = ?', [$id]);
            $message['is_read'] = 1;
            Audit::log('contact.read', 'contact_message', $id);
        }

        return $this->view('admin/messages/show', [
            'title'   => 'Mesaj: ' . (string) ($message['subject'] ?: $message['name']),
            'message' => $message,
            'replies' => ContactMessageService::replies($id),
        ]);
    }

    /** Müşteriye panel üzerinden yanıt yazar. */
    public function reply(): void
    {
        $id = $this->request->int('id');
        $result = ContactMessageService::reply($id, 'admin', \App\Core\Auth::id(), (string) $this->request->input('body', ''));
        if ($result['ok']) {
            $this->flashSuccess('Yanıt gönderildi. Müşteri panelindeki Mesajlar bölümünde görünecek.');
            $this->redirect('/yonetim/mesajlar/' . $id);
        }
        $this->flashError((string) ($result['error'] ?? 'Yanıt gönderilemedi.'));
        $this->redirect('/yonetim/mesajlar/' . $id);
    }

    /** Okundu / okunmadı işaretler. */
    public function setRead(): void
    {
        $id   = $this->request->int('id');
        $read = $this->request->bool('read') ? 1 : 0;

        $message = Database::first('SELECT id, name, email FROM contact_messages WHERE id = ? LIMIT 1', [$id]);
        if ($message === null) {
            $this->flashError('Mesaj bulunamadı.');
            $this->redirect('/yonetim/mesajlar');
        }

        Database::run('UPDATE contact_messages SET is_read = ? WHERE id = ?', [$read, $id]);
        Audit::log('contact.mark', 'contact_message', $id, ['read' => $read]);
        $this->flashSuccess($read === 1 ? 'Mesaj okundu olarak işaretlendi.' : 'Mesaj okunmadı olarak işaretlendi.');
        $this->redirect('/yonetim/mesajlar');
    }

    /** Mevcut filtreye uyan okunmamış mesajları tek işlemde okundu işaretler. */
    public function markAllRead(): void
    {
        $q      = trim((string) $this->request->input('q', ''));
        $status = (string) $this->request->input('durum', '');

        $where  = ['is_read = 0'];
        $params = [];
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ? OR subject LIKE ? OR message LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if ($status === 'okunmus') {
            // Okunmuşları işaretlemek anlamsız; filtre okunmamışa daraltılır.
            $this->flashSuccess('Bu filtrede okunmamış mesaj yok.');
            $this->redirect('/yonetim/mesajlar' . ($q !== '' ? '?q=' . urlencode($q) : ''));
        }

        $count = Database::run(
            'UPDATE contact_messages SET is_read = 1 WHERE ' . implode(' AND ', $where),
            $params
        )->rowCount();

        if ($count > 0) {
            Audit::log('contact.mark_all', 'contact_message', null, ['count' => $count, 'query' => $q]);
            $this->flashSuccess($count . ' mesaj okundu olarak işaretlendi.');
        } else {
            $this->flashSuccess('Okunmamış mesaj yok.');
        }
        $this->redirect('/yonetim/mesajlar' . ($q !== '' ? '?q=' . urlencode($q) : ''));
    }

    public function destroy(): void
    {
        $id = $this->request->int('id');
        $message = Database::first('SELECT id, name, email, subject FROM contact_messages WHERE id = ? LIMIT 1', [$id]);
        if ($message === null) {
            $this->flashError('Mesaj bulunamadı.');
            $this->redirect('/yonetim/mesajlar');
        }

        Database::run('DELETE FROM contact_messages WHERE id = ?', [$id]);
        Audit::log('contact.delete', 'contact_message', $id, [
            'name'  => (string) $message['name'],
            'email' => (string) $message['email'],
            'subject' => (string) ($message['subject'] ?? ''),
        ]);
        $this->flashSuccess('Mesaj silindi.');
        $this->redirect('/yonetim/mesajlar');
    }
}
