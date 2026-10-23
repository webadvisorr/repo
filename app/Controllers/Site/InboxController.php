<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Response;
use App\Services\InboxService;
use App\Services\PushService;

/**
 * Müşteri paneli bildirim merkezi.
 * Zil (özet) + /panel/bildirimler sayfası; okundu işaretleme JSON ile çalışır.
 */
final class InboxController extends Controller
{
    public function index(): string
    {
        $user = Auth::user();
        $page = max(1, (int) $this->request->input('sayfa', 1));
        $userId = (int) ($user['id'] ?? 0);
        $kind = (string) $this->request->input('tur', '');
        if (!in_array($kind, InboxService::KINDS, true)) {
            $kind = '';
        }
        $data = InboxService::paginate($userId, $page, 20, $kind !== '' ? $kind : null);

        return $this->view('site/notifications', [
            'title'       => t('inbox.title'),
            'rows'        => $data['rows'],
            'total'       => $data['total'],
            'page'        => $data['page'],
            'pages'       => $data['pages'],
            'unread'      => $data['unread'],
            'counts'      => InboxService::categoryCounts($userId),
            'kind'        => $kind,
            'pushEnabled' => PushService::enabled(),
            'pushKey'     => PushService::publicKey(),
            'pushDevices' => PushService::deviceCount((int) ($user['id'] ?? 0)),
        ]);
    }

    /** Zil için özet: son bildirimler + okunmamış sayısı. */
    public function summary(): never
    {
        $userId = (int) (Auth::id() ?? 0);
        $rows = array_map(static fn (array $row): array => [
            'id'         => (int) $row['id'],
            'kind'       => (string) $row['kind'],
            'kind_label' => InboxService::kindLabel((string) $row['kind']),
            'icon'       => InboxService::kindIcon((string) $row['kind']),
            'title'      => (string) $row['title'],
            'body'       => (string) ($row['body'] ?? ''),
            'image'      => InboxService::imageUrl($row['image_path'] ?? null),
            'link'       => (string) ($row['link_url'] ?? ''),
            'link_label' => (string) ($row['link_label'] ?? ''),
            'unread'     => $row['read_at'] === null,
            'time'       => time_ago((string) $row['created_at']),
            'created_at' => (string) $row['created_at'],
        ], InboxService::latest($userId, 8));

        Response::json([
            'ok'     => true,
            'unread' => InboxService::unreadCount($userId),
            'counts' => InboxService::categoryCounts($userId),
            'items'  => $rows,
            'all'    => url('/panel/bildirimler'),
        ]);
    }

    public function markRead(): void
    {
        $userId = (int) (Auth::id() ?? 0);
        $id = (int) $this->request->input('id', 0);
        $ok = $id > 0 && InboxService::markRead($userId, $id);

        if ($this->request->wantsJson()) {
            Response::json(['ok' => $ok, 'unread' => InboxService::unreadCount($userId)]);
        }

        $this->redirect('/panel/bildirimler');
    }

    /** Bir sekmeyi (bildirim türünü) okundu sayar; sayaç sıfırlanır. */
    public function markKind(): void
    {
        $userId = (int) (Auth::id() ?? 0);
        $kind = (string) $this->request->input('kind', '');
        $marked = InboxService::markKindRead($userId, $kind);

        if ($this->request->wantsJson()) {
            Response::json([
                'ok'     => in_array($kind, InboxService::KINDS, true),
                'marked' => $marked,
                'unread' => InboxService::unreadCount($userId),
                'counts' => InboxService::categoryCounts($userId),
            ]);
        }

        $this->redirect('/panel/bildirimler' . ($kind !== '' ? '?tur=' . rawurlencode($kind) : ''));
    }

    public function markAll(): void
    {
        $userId = (int) (Auth::id() ?? 0);
        $count = InboxService::markAllRead($userId);

        if ($this->request->wantsJson()) {
            Response::json(['ok' => true, 'marked' => $count, 'unread' => 0]);
        }

        $this->flashSuccess($count > 0 ? 'Tüm bildirimler okundu olarak işaretlendi.' : 'Okunmamış bildirim yok.');
        $this->redirect('/panel/bildirimler');
    }
}
