<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Uploader;
use App\Services\InboxService;
use App\Services\PushService;

/**
 * Site içi duyurular (özel bildirim).
 *
 * Panelden resim, metin ve buton bağlantısı içeren bir duyuru hazırlanır;
 * seçilen hedef kitlenin bildirim merkezine (zil) düşer. Hatırlatmalardan
 * farklı olarak e-posta göndermez.
 */
final class AnnouncementsController extends Controller
{
    protected string $layout = 'layouts/admin';

    private const MAX_IMAGE_BYTES = 3 * 1024 * 1024;

    public function index(): string
    {
        $rows = [];
        $totals = ['broadcasts' => 0, 'recipients' => 0, 'read' => 0];
        foreach (InboxService::broadcasts(100) as $broadcast) {
            $stats = InboxService::broadcastStats((int) $broadcast['id']);
            $rows[] = ['broadcast' => $broadcast, 'stats' => $stats];
            $totals['broadcasts']++;
            $totals['recipients'] += $stats['recipients'];
            $totals['read'] += $stats['read'];
        }

        return $this->view('admin/announcements', [
            'title' => 'Bildirim',
            'rows'  => $rows,
            'totals' => $totals,
            'audienceTotal' => InboxService::audienceCount([]),
            'available' => InboxService::available(),
            'pushEnabled' => PushService::enabled(),
            'pushDevices' => PushService::totalDevices(),
            'pushPending' => PushService::pendingCount(),
        ]);
    }

    /** Bekleyen cihaz bildirimlerini şimdi gönderir (cron beklemeden). */
    public function sendPushQueue(): void
    {
        if (!PushService::enabled()) {
            $this->flashError('Cihaz bildirimleri kullanılamıyor (tablolar veya VAPID anahtarı hazır değil).');
            $this->redirect('/yonetim/duyurular');
        }

        $result = PushService::process(60);
        if ($result['attempted'] === 0) {
            $this->flashSuccess('Bekleyen cihaz bildirimi yok.');
        } else {
            $this->flashSuccess('Cihaz bildirimi: ' . $result['sent'] . ' gönderildi, '
                . $result['failed'] . ' başarısız, ' . $result['skipped'] . ' cihazsız atlandı.');
        }
        $this->redirect('/yonetim/duyurular');
    }

    public function create(): string
    {
        return $this->view('admin/announcement_form', [
            'title'    => 'Yeni bildirim',
            'broadcast' => null,
            'options'  => $this->formOptions(),
            'values'   => ['title' => '', 'body' => '', 'link_url' => '', 'link_label' => ''],
        ]);
    }

    /** Duyuruyu oluşturur ve hedef kitleye gönderir. */
    public function store(): void
    {
        $title = trim((string) $this->request->string('title', ''));
        $body = trim((string) $this->request->input('body', ''));
        $backTo = '/yonetim/duyurular/yeni';

        $data = $this->validate(
            ['title' => $title, 'body' => $body],
            ['title' => 'required|max:160', 'body' => 'required|max:2000'],
            ['title' => 'Başlık', 'body' => 'Metin'],
            $backTo
        );

        // Tek kullanıcı seçildiyse diğer filtreler yok sayılır.
        $userId = $this->resolveUserId((string) $this->request->input('audience_user_email', ''), $backTo);
        $audience = InboxService::normalizeAudience($userId > 0
            ? ['user_id' => $userId]
            : [
                'plan_key' => (string) $this->request->input('audience_plan_key', ''),
                'org_id'   => (int) $this->request->input('audience_org_id', 0),
            ]);

        $image = ['media_id' => null, 'path' => null];
        $file = $this->request->file('image');
        if ($file !== null) {
            $stored = Uploader::store($file, 'inbox', [
                'type'      => 'image',
                'max_bytes' => self::MAX_IMAGE_BYTES,
            ]);
            if (!$stored['ok']) {
                $this->flashError((string) ($stored['error'] ?? 'Görsel yüklenemedi.'));
                $this->redirect($backTo);
            }
            $image['media_id'] = (int) $stored['media']['id'];
            $image['path'] = (string) $stored['media']['path'];
        }

        $result = InboxService::send([
            'title'          => $data['title'],
            'body'           => $data['body'],
            'image_media_id' => $image['media_id'],
            'image_path'     => $image['path'],
            'link_url'       => (string) $this->request->input('link_url', ''),
            'link_label'     => (string) $this->request->input('link_label', ''),
            'audience'       => $audience,
        ]);

        if (!$result['ok']) {
            $this->flashError((string) ($result['error'] ?? 'Duyuru gönderilemedi.'));
            $this->redirect($backTo);
        }

        $this->flashSuccess('Bildirim gönderildi — ulaşan kullanıcı: ' . (int) ($result['recipients'] ?? 0));
        $this->redirect('/yonetim/duyurular/' . (int) ($result['id'] ?? 0));
    }

    public function show(string $id): string
    {
        $broadcast = InboxService::broadcast((int) $id);
        if ($broadcast === null) {
            $this->flashError('Bildirim bulunamadı.');
            $this->redirect('/yonetim/duyurular');
        }

        return $this->view('admin/announcement_show', [
            'title'      => (string) $broadcast['title'],
            'broadcast'  => $broadcast,
            'stats'      => InboxService::broadcastStats((int) $broadcast['id']),
            'audience'   => InboxService::audienceOf($broadcast),
            'recipients' => InboxService::broadcastRecipients((int) $broadcast['id'], 100),
        ]);
    }

    public function destroy(): void
    {
        $id = (int) $this->request->input('id', 0);
        if ($id > 0 && InboxService::deleteBroadcast($id)) {
            $this->flashSuccess('Bildirim ve kullanıcı bildirimleri silindi.');
        } else {
            $this->flashError('Bildirim silinemedi.');
        }
        $this->redirect('/yonetim/duyurular');
    }

    /** @return array<string,mixed> */
    private function formOptions(): array
    {
        return [
            'plans'         => Database::select('SELECT id, `key`, name FROM plans ORDER BY sort_order ASC, id ASC'),
            'organizations' => Database::select('SELECT id, name FROM organizations ORDER BY name ASC LIMIT 500'),
            'all'           => InboxService::audienceCount([]),
        ];
    }

    /** E-posta ile girilen tek kullanıcıyı çözer; bulunamazsa forma geri döner. */
    private function resolveUserId(string $email, string $backTo): int
    {
        $email = trim($email);
        if ($email === '') {
            return 0;
        }
        $row = Database::first('SELECT id FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1', [mb_strtolower($email)]);
        if ($row === null) {
            $this->flashError('Belirtilen e-posta ile kayıtlı kullanıcı bulunamadı: ' . $email);
            $this->redirect($backTo);
        }
        return (int) $row['id'];
    }
}
