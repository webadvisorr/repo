<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Mailer;
use App\Core\Settings;
use App\Services\CreditService;
use App\Services\SubscriptionService;

/**
 * Üye yönetimi: liste, detay, kredi ekleme/düşme, abonelik atama/uzatma/iptal,
 * kredi ile ödeme, durum (aktif/pasif) yönetimi.
 */
final class MembersController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function index(): string
    {
        $search = trim((string) $this->request->input('q', ''));
        $status = (string) $this->request->input('status', '');
        $plan   = (string) $this->request->input('plan', '');
        $page   = max(1, $this->request->int('page', 1));
        $perPage = 20;

        $where = ['u.deleted_at IS NULL'];
        $params = [];

        if ($search !== '') {
            $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR o.name LIKE ?
                         OR EXISTS (SELECT 1 FROM vehicles v WHERE v.organization_id = o.id AND v.plate LIKE ?))';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if (in_array($status, ['active', 'passive', 'suspended'], true)) {
            $where[] = 'u.status = ?';
            $params[] = $status;
        }
        if ($plan !== '') {
            $where[] = 'p.`key` = ?';
            $params[] = $plan;
        }

        // ONLY_FULL_GROUP_BY uyumsuzluğunu önlemek için alt sorgular kullanılır.
        $sql = "SELECT u.id, u.full_name, u.email, u.phone, u.role, u.status, u.created_at, u.last_login_at,
                       o.id AS organization_id, o.name AS organization_name, o.credit_balance,
                       (SELECT pl.name FROM subscriptions s
                          JOIN plans pl ON pl.id = s.plan_id
                         WHERE s.organization_id = o.id AND s.status IN ('active','trialing','past_due')
                         ORDER BY s.id DESC LIMIT 1) AS plan_name,
                       (SELECT pl.`key` FROM subscriptions s
                          JOIN plans pl ON pl.id = s.plan_id
                         WHERE s.organization_id = o.id AND s.status IN ('active','trialing','past_due')
                         ORDER BY s.id DESC LIMIT 1) AS plan_key,
                       (SELECT s.status FROM subscriptions s
                         WHERE s.organization_id = o.id AND s.status IN ('active','trialing','past_due')
                         ORDER BY s.id DESC LIMIT 1) AS subscription_status,
                       (SELECT s.current_period_end FROM subscriptions s
                         WHERE s.organization_id = o.id AND s.status IN ('active','trialing','past_due')
                         ORDER BY s.id DESC LIMIT 1) AS current_period_end,
                       (SELECT COUNT(*) FROM vehicles v2 WHERE v2.organization_id = o.id AND v2.deleted_at IS NULL) AS vehicle_count
                FROM users u
                LEFT JOIN organizations o ON o.owner_user_id = u.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY u.id DESC
                LIMIT " . ($perPage + 1) . " OFFSET " . (($page - 1) * $perPage);

        $rows = Database::select($sql, $params);
        $hasNext = count($rows) > $perPage;
        if ($hasNext) {
            array_pop($rows);
        }

        $totals = [
            'users'     => (int) Database::scalar('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL'),
            'customers' => (int) Database::scalar("SELECT COUNT(*) FROM users WHERE role = 'customer' AND deleted_at IS NULL"),
            'credit'    => round((float) Database::scalar('SELECT COALESCE(SUM(credit_balance),0) FROM organizations'), 2),
            'active'    => (int) Database::scalar("SELECT COUNT(*) FROM subscriptions WHERE status IN ('active','trialing')"),
        ];

        return $this->view('admin/members/index', [
            'title'    => t('admin.members'),
            'rows'     => $rows,
            'totals'   => $totals,
            'search'   => $search,
            'status'   => $status,
            'plan'     => $plan,
            'page'     => $page,
            'hasNext'  => $hasNext,
            'plans'    => SubscriptionService::plans(false),
        ]);
    }

    public function show(string $id): string
    {
        $member = $this->member((int) $id);
        $organizationId = (int) ($member['organization_id'] ?? 0);

        $audit = Database::select(
            'SELECT * FROM audit_logs WHERE user_id = ? OR (entity = "organization" AND entity_id = ?) ORDER BY id DESC LIMIT 40',
            [(int) $member['id'], (string) $organizationId]
        );

        return $this->view('admin/members/show', [
            'title'          => (string) $member['full_name'],
            'member'         => $member,
            'organizationId' => $organizationId,
            'subscription'   => $organizationId > 0 ? SubscriptionService::current($organizationId) : null,
            'subscriptions'  => $organizationId > 0 ? SubscriptionService::history($organizationId, 20) : [],
            'ledger'         => $organizationId > 0 ? CreditService::history($organizationId, 60) : [],
            'creditSummary'  => $organizationId > 0 ? CreditService::summary($organizationId) : ['credit' => 0, 'debit' => 0, 'count' => 0],
            'payments'       => $organizationId > 0 ? SubscriptionService::payments($organizationId, 60) : [],
            'limits'         => $organizationId > 0 ? SubscriptionService::limits($organizationId) : [],
            'plans'          => SubscriptionService::plans(false),
            'vehicles'       => $organizationId > 0
                ? Database::select('SELECT id, plate, brand, model, status FROM vehicles WHERE organization_id = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT 20', [$organizationId])
                : [],
            'audit'          => $audit,
        ]);
    }

    /** Kredi ekle (panelden). */
    public function addCredit(): void
    {
        $member = $this->memberOrRedirect((int) $this->request->int('user_id'));
        $organizationId = (int) ($member['organization_id'] ?? 0);
        if ($organizationId <= 0) {
            $organizationId = (int) (CreditService::organizationForUser((int) $member['id']) ?? 0);
        }

        $amount = (float) str_replace(',', '.', (string) $this->request->input('amount', '0'));
        $reason = (string) $this->request->input('reason', 'manual');
        $note   = trim((string) $this->request->input('note', ''));

        if ($amount <= 0) {
            $this->flashError('Geçerli bir kredi tutarı girin.');
            $this->redirect('/yonetim/uyeler/' . (int) $member['id']);
        }

        $result = CreditService::add($organizationId, $amount, $reason, $note !== '' ? $note : null, null, (int) $member['id']);
        if (!$result['ok']) {
            $this->flashError((string) $result['error']);
        } else {
            $this->flashSuccess(sprintf('%s kredi eklendi. Yeni bakiye: %.2f', number_format($amount, 2, ',', '.'), (float) $result['balance']));
        }
        $this->redirect('/yonetim/uyeler/' . (int) $member['id']);
    }

    /** Krediyi düş (manuel düzeltme/iade). */
    public function deductCredit(): void
    {
        $member = $this->memberOrRedirect((int) $this->request->int('user_id'));
        $organizationId = (int) ($member['organization_id'] ?? 0);

        $amount = (float) str_replace(',', '.', (string) $this->request->input('amount', '0'));
        $note   = trim((string) $this->request->input('note', ''));

        $result = CreditService::deduct($organizationId, $amount, 'manual_debit', $note !== '' ? $note : 'Panelden düşüldü', null, (int) $member['id']);
        if (!$result['ok']) {
            $this->flashError((string) $result['error']);
        } else {
            $this->flashSuccess(sprintf('Kredi düşüldü. Yeni bakiye: %.2f', (float) $result['balance']));
        }
        $this->redirect('/yonetim/uyeler/' . (int) $member['id']);
    }

    /** Abonelik ata / plan değiştir. */
    public function assignPlan(): void
    {
        $member = $this->memberOrRedirect((int) $this->request->int('user_id'));
        $organizationId = (int) ($member['organization_id'] ?? 0);
        $planId = $this->request->int('plan_id');
        $days = $this->request->int('days', 0);
        $trialDays = $this->request->int('trial_days', 0);
        $markPaid = $this->request->bool('mark_paid');
        $method = (string) $this->request->input('method', 'manual');
        $note = trim((string) $this->request->input('note', ''));

        $result = SubscriptionService::assign($organizationId, $planId, [
            'days'       => $days > 0 ? $days : null,
            'trial_days' => $trialDays,
            'mark_paid'  => $markPaid,
            'method'     => $method,
            'provider'   => 'manual',
            'note'       => $note !== '' ? $note : null,
        ]);

        if (!$result['ok']) {
            $this->flashError((string) $result['error']);
        } else {
            $this->flashSuccess('Abonelik atandı. Bitiş: ' . format_date((string) $result['period_end']));
        }
        $this->redirect('/yonetim/uyeler/' . (int) $member['id']);
    }

    public function extendSubscription(): void
    {
        $member = $this->memberOrRedirect((int) $this->request->int('user_id'));
        $subscriptionId = $this->request->int('subscription_id');
        $days = $this->request->int('days', 30);
        $markPaid = $this->request->bool('mark_paid');

        $result = SubscriptionService::extend($subscriptionId, $days, $markPaid, 'Panelden uzatıldı');
        if (!$result['ok']) {
            $this->flashError((string) $result['error']);
        } else {
            $this->flashSuccess('Abonelik uzatıldı. Yeni bitiş: ' . format_date((string) $result['period_end']));
        }
        $this->redirect('/yonetim/uyeler/' . (int) $member['id']);
    }

    public function cancelSubscription(): void
    {
        $member = $this->memberOrRedirect((int) $this->request->int('user_id'));
        $subscriptionId = $this->request->int('subscription_id');
        $immediate = $this->request->bool('immediate');

        $result = SubscriptionService::cancel($subscriptionId, $immediate, 'Panelden iptal edildi');
        $result['ok'] ? $this->flashSuccess($immediate ? 'Abonelik hemen iptal edildi.' : 'Abonelik dönem sonunda iptal edilecek.')
                      : $this->flashError((string) $result['error']);
        $this->redirect('/yonetim/uyeler/' . (int) $member['id']);
    }

    public function resumeSubscription(): void
    {
        $member = $this->memberOrRedirect((int) $this->request->int('user_id'));
        $result = SubscriptionService::resume($this->request->int('subscription_id'));
        $result['ok'] ? $this->flashSuccess('Abonelik yeniden etkinleştirildi.') : $this->flashError((string) $result['error']);
        $this->redirect('/yonetim/uyeler/' . (int) $member['id']);
    }

    /** Aboneliği kredi bakiyesinden öder. */
    public function payWithCredit(): void
    {
        $member = $this->memberOrRedirect((int) $this->request->int('user_id'));
        $result = SubscriptionService::payWithCredit($this->request->int('subscription_id'));
        if (!$result['ok']) {
            $this->flashError((string) $result['error']);
        } else {
            $this->flashSuccess(sprintf('Kredi ile ödendi. Yeni dönem bitişi: %s · Kalan bakiye: %.2f', format_date((string) $result['period_end']), (float) $result['balance']));
        }
        $this->redirect('/yonetim/uyeler/' . (int) $member['id']);
    }

    /** Hesap durumu: aktif / pasif / askıya alındı. */
    public function updateStatus(): void
    {
        $member = $this->memberOrRedirect((int) $this->request->int('user_id'));
        $status = (string) $this->request->input('status', 'active');

        if (!in_array($status, ['active', 'passive', 'suspended'], true)) {
            $this->flashError('Geçersiz durum.');
            $this->redirect('/yonetim/uyeler/' . (int) $member['id']);
        }
        if ((int) $member['id'] === (int) Auth::id()) {
            $this->flashError('Kendi hesabınızın durumunu değiştiremezsiniz.');
            $this->redirect('/yonetim/uyeler/' . (int) $member['id']);
        }

        Database::update('users', [
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => (int) $member['id']]);

        Audit::log('member.status', 'user', (int) $member['id'], ['status' => $status]);
        $this->flashSuccess('Hesap durumu güncellendi.');
        $this->redirect('/yonetim/uyeler/' . (int) $member['id']);
    }

    /** Üye profilini güncelle (ad, telefon, dil, bildirim tercihleri). */
    public function updateProfile(): void
    {
        $member = $this->memberOrRedirect((int) $this->request->int('user_id'));

        $data = $this->validate(
            [
                'full_name' => $this->request->string('full_name'),
                'phone'     => $this->request->string('phone'),
                'whatsapp'  => $this->request->string('whatsapp'),
                'locale'    => (string) $this->request->input('locale', 'tr'),
            ],
            [
                'full_name' => 'required|min:3|max:160',
                'phone'     => 'nullable|phone|max:32',
                'whatsapp'  => 'nullable|phone|max:32',
                'locale'    => 'required|in:tr,en,ru,ar,fa,el',
            ],
            ['full_name' => t('auth.name'), 'phone' => t('auth.phone'), 'locale' => t('common.language')],
            '/yonetim/uyeler/' . (int) $member['id']
        );

        Database::update('users', [
            'full_name' => (string) $data['full_name'],
            'phone'     => $data['phone'] !== '' ? (string) $data['phone'] : null,
            'whatsapp'  => $data['whatsapp'] !== '' ? (string) $data['whatsapp'] : null,
            'locale'    => (string) $data['locale'],
            'reminder_offsets' => preg_replace('/[^0-9,]/', '', $this->request->string('reminder_offsets')) ?: (string) Settings::get('site.default_reminder_offsets', '7,3,1,0'),
            'notify_email'    => $this->request->bool('notify_email') ? 1 : 0,
            'notify_sms'      => $this->request->bool('notify_sms') ? 1 : 0,
            'notify_whatsapp' => $this->request->bool('notify_whatsapp') ? 1 : 0,
            'notify_telegram' => $this->request->bool('notify_telegram') ? 1 : 0,
            'marketing_opt_in' => $this->request->bool('marketing_opt_in') ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => (int) $member['id']]);

        Audit::log('member.profile_update', 'user', (int) $member['id']);
        $this->flashSuccess(t('common.saved'));
        $this->redirect('/yonetim/uyeler/' . (int) $member['id']);
    }

    /** Şifre sıfırlama bağlantısı gönderir (şifre gösterme yok — güvenli yol). */
    public function sendResetLink(): void
    {
        $member = $this->memberOrRedirect((int) $this->request->int('user_id'));
        $token = Auth::createToken((int) $member['id'], 'password_reset', 86400);
        $link = url('/sifre-sifirla?token=' . $token);

        $mailer = new Mailer();
        if (!$mailer->isConfigured()) {
            $this->flashError('SMTP ayarları yapılmadığı için e-posta gönderilemedi. Bağlantıyı elle iletebilirsiniz.');
            $this->redirect('/yonetim/uyeler/' . (int) $member['id']);
        }

        $brand = (string) Settings::get('brand.title', 'evrako');
        $result = $mailer->send(
            (string) $member['email'],
            t('auth.reset_title') . ' — ' . $brand,
            '<p>' . e((string) $member['full_name']) . ',</p>'
            . '<p>Şifrenizi yenilemek için 24 saat geçerli bağlantı:</p>'
            . '<p><a href="' . e($link) . '">' . e($link) . '</a></p>'
        );

        if ($result['ok']) {
            Audit::log('member.reset_link_sent', 'user', (int) $member['id']);
            $this->flashSuccess('Şifre sıfırlama bağlantısı e-posta ile gönderildi.');
        } else {
            $this->flashError('E-posta gönderilemedi: ' . (string) $result['error']);
        }
        $this->redirect('/yonetim/uyeler/' . (int) $member['id']);
    }

    private function member(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return Database::first(
            'SELECT u.*, o.id AS organization_id, o.name AS organization_name, o.type AS organization_type,
                    o.credit_balance, o.phone AS organization_phone, o.address, o.city, o.country, o.tax_number
             FROM users u
             LEFT JOIN organizations o ON o.owner_user_id = u.id
             WHERE u.id = ? AND u.deleted_at IS NULL LIMIT 1',
            [$id]
        );
    }

    private function memberOrRedirect(int $id): array
    {
        $member = $this->member($id);
        if ($member === null) {
            Flash::error('Üye bulunamadı.');
            $this->redirect('/yonetim/uyeler');
        }
        return $member;
    }
}
