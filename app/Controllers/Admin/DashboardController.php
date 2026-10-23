<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Response;
use App\Core\Settings;
use App\Services\PresenceService;
use App\Services\SupportService;

/**
 * Yönetim paneli ana sayfası.
 *
 * Kutucuklar (widget) tek bir katalogdan üretilir; yönetici sağ üstteki
 * “Kutucuk ayarları” ile hangilerinin görüneceğini seçer. Seçim
 * `dashboard.widgets` ayarında (JSON dizi) saklanır.
 */
final class DashboardController extends Controller
{
    protected string $layout = 'layouts/admin';

    /** Varsayılan olarak görünen kutucuklar. */
    private const DEFAULT_WIDGETS = [
        'customers', 'new_today', 'vehicles', 'documents',
        'expiring30', 'expired', 'reminder_mail', 'reminder_whatsapp',
        'reminder_telegram', 'mails_today', 'online', 'messages',
    ];

    public function index(): string
    {
        $catalogue = $this->widgetCatalogue();
        $selected = $this->selectedWidgets($catalogue);

        $widgets = [];
        foreach ($selected as $key) {
            if (isset($catalogue[$key])) {
                $widgets[$key] = $catalogue[$key];
            }
        }

        $upcoming = Database::select(
            "SELECT d.end_date, d.id AS document_id, v.id AS vehicle_id, v.plate, dt.labels_json, u.email AS owner_email
             FROM vehicle_documents d
             JOIN vehicles v ON v.id = d.vehicle_id
             JOIN document_types dt ON dt.id = d.document_type_id
             LEFT JOIN organizations o ON o.id = v.organization_id
             LEFT JOIN users u ON u.id = o.owner_user_id
             WHERE d.status = 'active' AND d.deleted_at IS NULL AND d.end_date >= CURDATE()
             ORDER BY d.end_date ASC LIMIT 12"
        );

        $audit = Database::select(
            'SELECT a.*, u.full_name, u.email FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 10'
        );

        return $this->view('admin/dashboard', [
            'title'          => t('admin.dashboard'),
            'widgets'        => $widgets,
            'catalogue'      => $catalogue,
            'upcoming'       => $upcoming,
            'audit'          => $audit,
            'visitors'       => PresenceService::onlineVisitors(PresenceService::WINDOW_MINUTES, 12),
            'support'        => SupportService::adminStats(),
            'stats'          => [
                'online'     => PresenceService::onlineCount(),
                'onlineToday' => PresenceService::todayCount(),
                'messages'   => (int) Database::scalar('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0'),
                'expiring30' => (int) Database::scalar("SELECT COUNT(*) FROM vehicle_documents WHERE status = 'active' AND deleted_at IS NULL AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"),
            ],
            'smtpConfigured' => trim((string) Settings::get('smtp.host', '')) !== '',
        ]);
    }

    /** Kutucuk seçimini kaydeder. */
    public function saveWidgets(): void
    {
        $catalogue = $this->widgetCatalogue();
        $posted = (array) $this->request->input('widgets', []);
        $selected = [];
        foreach ($posted as $key) {
            $key = (string) $key;
            if (isset($catalogue[$key])) {
                $selected[] = $key;
            }
        }

        Settings::set('dashboard.widgets', array_values(array_unique($selected)), 'dashboard');
        Audit::log('dashboard.widgets', 'setting', null, ['widgets' => $selected]);

        $this->flashSuccess(count($selected) . ' kutucuk seçildi. Panel görünümünüz güncellendi.');
        $this->redirect('/yonetim/');
    }

    /** @return array<string,array{label:string,value:string,icon:string,href:string,tone:int}> */
    private function widgetCatalogue(): array
    {
        $currency = (string) Settings::get('payment.currency', 'TRY');
        $money = static fn (float $value): string => number_format($value, 2, ',', '.') . ' ' . $currency;

        $channels = ['email' => 0, 'whatsapp' => 0, 'telegram' => 0, 'sms' => 0];
        try {
            foreach (Database::select(
                "SELECT channel, COUNT(*) AS total FROM notifications
                 WHERE template_key = 'reminder' AND status = 'sent' AND created_at >= CURDATE()
                 GROUP BY channel"
            ) as $row) {
                $channels[(string) $row['channel']] = (int) $row['total'];
            }
        } catch (\Throwable) {
        }

        $revenue = 0.0;
        try {
            $revenue = (float) Database::scalar(
                "SELECT COALESCE(SUM(amount), 0) FROM payments
                 WHERE status = 'paid' AND currency = ? AND paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')",
                [$currency]
            );
        } catch (\Throwable) {
        }

        return [
            'customers' => [
                'label' => 'Müşteri', 'icon' => '👥', 'href' => '/yonetim/uyeler', 'tone' => 1,
                'value' => (string) (int) Database::scalar("SELECT COUNT(*) FROM users WHERE role = 'customer' AND deleted_at IS NULL"),
            ],
            'new_today' => [
                'label' => 'Bugün yeni üye', 'icon' => '🆕', 'href' => '/yonetim/uyeler?siralama=yeni', 'tone' => 2,
                'value' => (string) (int) Database::scalar('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND DATE(created_at) = CURDATE()'),
            ],
            'new_week' => [
                'label' => 'Son 7 gün yeni üye', 'icon' => '📈', 'href' => '/yonetim/uyeler?siralama=yeni', 'tone' => 3,
                'value' => (string) (int) Database::scalar('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'),
            ],
            'new_month' => [
                'label' => 'Bu ay yeni üye', 'icon' => '🗓️', 'href' => '/yonetim/uyeler?siralama=yeni', 'tone' => 4,
                'value' => (string) (int) Database::scalar('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND created_at >= DATE_FORMAT(CURDATE(), \'%Y-%m-01\')'),
            ],
            'vehicles' => [
                'label' => 'Araç', 'icon' => '🚗', 'href' => '/yonetim/araclar', 'tone' => 2,
                'value' => (string) (int) Database::scalar('SELECT COUNT(*) FROM vehicles WHERE deleted_at IS NULL'),
            ],
            'documents' => [
                'label' => 'Aktif evrak', 'icon' => '📄', 'href' => '/yonetim/evraklar?durum=active', 'tone' => 3,
                'value' => (string) (int) Database::scalar("SELECT COUNT(*) FROM vehicle_documents WHERE status = 'active' AND deleted_at IS NULL"),
            ],
            'expiring30' => [
                'label' => '30 günde bitecek', 'icon' => '⏰', 'href' => '/yonetim/evraklar?vade=30', 'tone' => 4,
                'value' => (string) (int) Database::scalar("SELECT COUNT(*) FROM vehicle_documents WHERE status = 'active' AND deleted_at IS NULL AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"),
            ],
            'expiring7' => [
                'label' => '7 günde bitecek', 'icon' => '🔔', 'href' => '/yonetim/evraklar?vade=7', 'tone' => 5,
                'value' => (string) (int) Database::scalar("SELECT COUNT(*) FROM vehicle_documents WHERE status = 'active' AND deleted_at IS NULL AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"),
            ],
            'expired' => [
                'label' => 'Süresi geçmiş', 'icon' => '🚨', 'href' => '/yonetim/evraklar?vade=gecmis', 'tone' => 5,
                'value' => (string) (int) Database::scalar("SELECT COUNT(*) FROM vehicle_documents WHERE status = 'active' AND deleted_at IS NULL AND end_date < CURDATE()"),
            ],
            'reminder_mail' => [
                'label' => 'Bugün e-posta hatırlatma', 'icon' => '✉️', 'href' => '/yonetim/bildirimler?kanal=email', 'tone' => 6,
                'value' => (string) $channels['email'],
            ],
            'reminder_whatsapp' => [
                'label' => 'Bugün WhatsApp hatırlatma', 'icon' => '💬', 'href' => '/yonetim/bildirimler?kanal=whatsapp', 'tone' => 7,
                'value' => (string) $channels['whatsapp'],
            ],
            'reminder_telegram' => [
                'label' => 'Bugün Telegram hatırlatma', 'icon' => '📨', 'href' => '/yonetim/bildirimler?kanal=telegram', 'tone' => 8,
                'value' => (string) $channels['telegram'],
            ],
            'reminder_sms' => [
                'label' => 'Bugün SMS hatırlatma', 'icon' => '📱', 'href' => '/yonetim/bildirimler?kanal=sms', 'tone' => 1,
                'value' => (string) $channels['sms'],
            ],
            'mails_today' => [
                'label' => 'Bugün gönderilen e-posta', 'icon' => '📬', 'href' => '/yonetim/bildirimler', 'tone' => 6,
                'value' => (string) (int) Database::scalar("SELECT COUNT(*) FROM notifications WHERE channel = 'email' AND status = 'sent' AND created_at >= CURDATE()"),
            ],
            'push_today' => [
                'label' => 'Bugün panel bildirimi', 'icon' => '🔔', 'href' => '/yonetim/duyurular', 'tone' => 2,
                'value' => (string) (int) Database::scalar('SELECT COUNT(*) FROM user_notifications WHERE created_at >= CURDATE()'),
            ],
            'push_devices' => [
                'label' => 'Bağlı cihaz', 'icon' => '📲', 'href' => '/yonetim/duyurular', 'tone' => 3,
                'value' => (string) (int) Database::scalar('SELECT COUNT(*) FROM push_subscriptions'),
            ],
            'online' => [
                'label' => 'Şu an sitede', 'icon' => '🟢', 'href' => '/yonetim/#canli-ziyaretciler', 'tone' => 7,
                'value' => (string) PresenceService::onlineCount(),
            ],
            'online_today' => [
                'label' => 'Bugün tekil ziyaretçi', 'icon' => '👀', 'href' => '/yonetim/#canli-ziyaretciler', 'tone' => 8,
                'value' => (string) PresenceService::todayCount(),
            ],
            'messages' => [
                'label' => 'Okunmamış mesaj', 'icon' => '📥', 'href' => '/yonetim/mesajlar?durum=okunmamis', 'tone' => 8,
                'value' => (string) (int) Database::scalar('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0'),
            ],
            'support_open' => [
                'label' => 'Açık destek talebi', 'icon' => '🎧', 'href' => '/yonetim/destek?durum=open', 'tone' => 4,
                'value' => (string) (int) Database::scalar("SELECT COUNT(*) FROM support_tickets WHERE status = 'open'"),
            ],
            'payments_pending' => [
                'label' => 'Onay bekleyen ödeme', 'icon' => '💰', 'href' => '/yonetim/odemeler', 'tone' => 5,
                'value' => (string) (int) Database::scalar("SELECT COUNT(*) FROM payments WHERE status = 'pending'"),
            ],
            'revenue_month' => [
                'label' => 'Bu ay onaylanan ödeme', 'icon' => '🧾', 'href' => '/yonetim/odemeler', 'tone' => 6,
                'value' => $money($revenue),
            ],
            'active_subs' => [
                'label' => 'Aktif abonelik', 'icon' => '💳', 'href' => '/yonetim/uyeler', 'tone' => 1,
                'value' => (string) (int) Database::scalar("SELECT COUNT(*) FROM subscriptions WHERE status IN ('active','trialing')"),
            ],
        ];
    }

    /**
     * @param array<string,array> $catalogue
     * @return array<int,string>
     */
    private function selectedWidgets(array $catalogue): array
    {
        $saved = Settings::get('dashboard.widgets', null);
        if (!is_array($saved)) {
            return self::DEFAULT_WIDGETS;
        }
        $selected = [];
        foreach ($saved as $key) {
            if (isset($catalogue[(string) $key])) {
                $selected[] = (string) $key;
            }
        }
        return $selected;
    }

    /** Canlı sayaç için JSON (panel sayfası 25 sn'de bir tazeler). */
    public function presence(): never
    {
        $visitors = array_map(static fn (array $row): array => [
            'name' => $row['full_name'] !== null ? (string) $row['full_name'] : 'Ziyaretçi',
            'user_id' => (int) ($row['user_id'] ?? 0),
            'path' => (string) ($row['path'] ?: '/'),
            'time' => time_ago((string) $row['last_seen']),
        ], PresenceService::onlineVisitors(PresenceService::WINDOW_MINUTES, 12));

        Response::json([
            'ok'      => true,
            'online'  => PresenceService::onlineCount(),
            'today'   => PresenceService::todayCount(),
            'visitors' => $visitors,
        ]);
    }
}
