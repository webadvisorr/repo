<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Database;
use App\Services\CronService;
use App\Services\ReminderService;

/**
 * Bildirim günlüğü: hatırlatma e-postalarının durumu (kuyrukta/gönderildi/açıldı/hata).
 * Filtreler, tekil/tekil olmayan yeniden deneme ve manuel kuyruk işleme içerir.
 */
final class NotificationsController extends Controller
{
    protected string $layout = 'layouts/admin';

    private const STATUSES = ['queued', 'sent', 'failed', 'skipped', 'cancelled'];
    private const CHANNELS = ['email', 'sms', 'whatsapp', 'telegram', 'push'];
    private const PER_PAGE = 50;

    public function index(): string
    {
        $filters = [
            'status'  => (string) $this->request->input('durum', ''),
            'channel' => (string) $this->request->input('kanal', ''),
            'q'       => trim((string) $this->request->string('q', '')),
            'days'    => (int) $this->request->input('gun', 30),
            'from'    => (string) (db_date($this->request->input('baslangic', '')) ?? ''),
            'to'      => (string) (db_date($this->request->input('bitis', '')) ?? ''),
        ];
        $page = max(1, (int) $this->request->input('sayfa', 1));

        [$where, $params] = $this->buildWhere($filters);
        $sql = ' FROM notifications n
                 LEFT JOIN users u ON u.id = n.user_id
                 LEFT JOIN vehicles v ON v.id = n.vehicle_id
                 LEFT JOIN organizations o ON o.id = n.organization_id
                 WHERE ' . implode(' AND ', $where);

        $total = (int) Database::scalar('SELECT COUNT(*)' . $sql, $params);
        $offset = ($page - 1) * self::PER_PAGE;

        $rows = Database::select(
            'SELECT n.*, u.full_name, v.plate, o.name AS org_name' . $sql
            . ' ORDER BY n.id DESC LIMIT ' . self::PER_PAGE . ' OFFSET ' . $offset,
            $params
        );

        return $this->view('admin/notifications', [
            'title'    => 'E-posta Günlüğü',
            'filters'  => $filters,
            'statuses' => self::STATUSES,
            'channels' => self::CHANNELS,
            'rows'     => $rows,
            'stats'    => ReminderService::stats(),
            'total'    => $total,
            'page'     => $page,
            'pages'    => max(1, (int) ceil($total / self::PER_PAGE)),
            'smtpReady' => (new \App\Core\Mailer())->isConfigured(),
            'dailyLimit' => ReminderService::dailyLimit(),
            'sentToday'  => ReminderService::sentToday(),
        ]);
    }

    public function show(string $id): string
    {
        $row = $this->find((int) $id);
        if ($row === null) {
            $this->flashError(t('notifications.not_found'));
            $this->redirect('/yonetim/bildirimler');
        }

        return $this->view('admin/notification_show', [
            'title' => t('notifications.detail') . ' #' . (int) $id,
            'row'   => $row,
        ]);
    }

    public function retry(): void
    {
        $result = ReminderService::requeue((int) $this->request->input('id', 0));
        if ($result['ok']) {
            Audit::log('notification.requeue', 'notification', (int) $this->request->input('id', 0));
            $this->flashSuccess(t('notifications.requeued'));
        } else {
            $this->flashError((string) ($result['error'] ?? t('common.error')));
        }
        $this->redirect($this->backToList());
    }

    public function retryFailed(): void
    {
        $count = ReminderService::requeueFailed(200);
        Audit::log('notification.requeue_failed', 'notification', null, ['count' => $count]);
        $this->flashSuccess(t('notifications.requeued_count', ['count' => $count]));
        $this->redirect($this->backToList());
    }

    public function cancel(): void
    {
        $result = ReminderService::cancel((int) $this->request->input('id', 0));
        if ($result['ok']) {
            Audit::log('notification.cancel', 'notification', (int) $this->request->input('id', 0));
            $this->flashSuccess(t('notifications.cancelled'));
        } else {
            $this->flashError((string) ($result['error'] ?? t('common.error')));
        }
        $this->redirect($this->backToList());
    }

    /** Kuyruğu hemen işle (günlük limit kadar). */
    public function runNow(): void
    {
        $dispatch = ReminderService::dispatch();
        Audit::log('notification.dispatch', 'notification', null, [
            'sent' => $dispatch['sent'], 'failed' => $dispatch['failed'], 'remaining' => $dispatch['remaining'],
        ]);
        if (isset($dispatch['error']) && $dispatch['error'] !== null) {
            $this->flashError((string) $dispatch['error']);
        } else {
            $this->flashSuccess(t('notifications.dispatched', [
                'sent'    => $dispatch['sent'],
                'failed'  => $dispatch['failed'],
                'pending' => $dispatch['remaining'],
            ]));
        }
        $this->redirect($this->backToList());
    }

    /** Taramayı da çalıştır (kuyruğa ekle + gönder). */
    public function runFull(): void
    {
        $result = CronService::runDaily();
        Audit::log('notification.run_full', 'notification', null, ['created' => (int) $result['reminders']['created']]);
        $this->flashSuccess(t('notifications.ran_full', ['created' => (int) $result['reminders']['created'], 'sent' => (int) $result['reminders']['sent']]));
        $this->redirect($this->backToList());
    }

    /** @return array{0:array<int,string>,1:array<int,mixed>} */
    private function buildWhere(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (in_array($filters['status'], self::STATUSES, true)) {
            $where[] = 'n.status = ?';
            $params[] = $filters['status'];
        }
        if (in_array($filters['channel'], self::CHANNELS, true)) {
            $where[] = 'n.channel = ?';
            $params[] = $filters['channel'];
        }
        if ($filters['q'] !== '') {
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], (string) $filters['q']) . '%';
            $where[] = '(n.recipient LIKE ? OR n.subject LIKE ? OR n.subject_rendered LIKE ? OR v.plate LIKE ?)';
            array_push($params, $term, $term, $term, $term);
        }
        if ((int) $filters['days'] > 0) {
            $where[] = 'n.created_at >= ?';
            $params[] = date('Y-m-d H:i:s', time() - (int) $filters['days'] * 86400);
        }
        if ($filters['from'] !== '') {
            $where[] = 'n.created_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if ($filters['to'] !== '') {
            $where[] = 'n.created_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }
        return [$where, $params];
    }

    private function find(int $id): ?array
    {
        return Database::first(
            'SELECT n.*, u.full_name, u.email AS user_email, v.plate, v.brand, v.model, o.name AS org_name
             FROM notifications n
             LEFT JOIN users u ON u.id = n.user_id
             LEFT JOIN vehicles v ON v.id = n.vehicle_id
             LEFT JOIN organizations o ON o.id = n.organization_id
             WHERE n.id = ? LIMIT 1',
            [$id]
        );
    }

    private function backToList(): string
    {
        $query = http_build_query(array_filter([
            'durum' => (string) $this->request->input('durum', ''),
            'kanal' => (string) $this->request->input('kanal', ''),
            'q'     => (string) $this->request->input('q', ''),
            'gun'   => (string) $this->request->input('gun', ''),
            'baslangic' => (string) $this->request->input('baslangic', ''),
            'bitis'     => (string) $this->request->input('bitis', ''),
        ], static fn ($v) => $v !== ''));
        return '/yonetim/bildirimler' . ($query === '' ? '' : '?' . $query);
    }
}
