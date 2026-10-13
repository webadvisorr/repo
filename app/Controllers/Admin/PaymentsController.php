<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Services\CreditService;
use App\Services\PaymentService;
use App\Services\SubscriptionService;

/** Ödeme kayıtları: liste, manuel ödeme kaydı, kredi yüklemesi ve iptal/iade. */
final class PaymentsController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function index(): string
    {
        $status = (string) $this->request->input('status', '');
        $search = trim((string) $this->request->input('q', ''));
        $where = ['1=1'];
        $params = [];

        if (in_array($status, ['pending', 'paid', 'failed', 'refunded'], true)) {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where[] = '(u.email LIKE ? OR o.name LIKE ? OR p.notes LIKE ? OR p.provider_txn_id LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }

        $rows = Database::select(
            "SELECT p.*, o.name AS organization_name, u.email AS owner_email, u.id AS owner_id,
                    pl.name AS plan_name, r.full_name AS recorded_by_name
             FROM payments p
             LEFT JOIN organizations o ON o.id = p.organization_id
             LEFT JOIN users u ON u.id = o.owner_user_id
             LEFT JOIN users r ON r.id = p.recorded_by
             LEFT JOIN subscriptions s ON s.id = p.subscription_id
             LEFT JOIN plans pl ON pl.id = s.plan_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY p.id DESC LIMIT 100",
            $params
        );

        $totals = [
            'paid'    => round((float) Database::scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'paid'"), 2),
            'pending' => round((float) Database::scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'pending'"), 2),
            'count'   => (int) Database::scalar('SELECT COUNT(*) FROM payments'),
            'credit'  => round((float) Database::scalar('SELECT COALESCE(SUM(credit_balance),0) FROM organizations'), 2),
        ];

        return $this->view('admin/payments', [
            'title'   => 'Ödemeler',
            'rows'    => $rows,
            'totals'  => $totals,
            'search'  => $search,
            'status'  => $status,
            'members' => Database::select("SELECT u.id, u.full_name, u.email FROM users u WHERE u.deleted_at IS NULL AND u.role = 'customer' ORDER BY u.full_name ASC LIMIT 500"),
            'plans'   => SubscriptionService::plans(true),
            'declarations' => PaymentService::pendingDeclarations(100),
            'currency' => (string) \App\Core\Settings::get('payment.currency', 'TRY'),
        ]);
    }

    /**
     * Manuel ödeme kaydı. İki mod:
     *  - kredi: üyeye kredi yükler (credit_ledger'a işler)
     *  - abonelik: abonelik ata/uzat ve ödemeyi kaydet
     */
    public function store(): void
    {
        $mode = (string) $this->request->input('mode', 'credit');
        $userId = $this->request->int('user_id');
        $amount = round((float) str_replace(',', '.', $this->request->string('amount', '0')), 2);
        $method = (string) $this->request->input('method', 'bank_transfer');
        $note = mb_substr($this->request->string('note'), 0, 250);

        $member = Database::first('SELECT id, full_name, email FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1', [$userId]);
        if ($member === null) {
            $this->flashError('Üye bulunamadı.');
            $this->redirect('/yonetim/odemeler');
        }

        $organizationId = CreditService::organizationForUser($userId);
        if ($organizationId === null) {
            $this->flashError('Üyenin kurumu bulunamadı.');
            $this->redirect('/yonetim/odemeler');
        }
        if (!in_array($method, ['bank_transfer', 'cash', 'card', 'manual'], true)) {
            $method = 'manual';
        }

        if ($mode === 'credit') {
            if ($amount <= 0) {
                $this->flashError('Geçerli bir tutar girin.');
                $this->redirect('/yonetim/odemeler');
            }

            $credit = CreditService::add($organizationId, $amount, $method, $note !== '' ? $note : 'Manuel ödeme', null, $userId);
            if (!$credit['ok']) {
                $this->flashError((string) $credit['error']);
                $this->redirect('/yonetim/odemeler');
            }

            Database::insert('payments', [
                'organization_id' => $organizationId,
                'subscription_id' => null,
                'amount'          => $amount,
                'currency'        => (string) \App\Core\Settings::get('payment.currency', 'TRY'),
                'status'          => 'paid',
                'provider'        => 'manual',
                'method'          => $method,
                'recorded_by'     => Auth::id(),
                'notes'           => $note !== '' ? $note : 'Kredi yüklemesi',
                'paid_at'         => date('Y-m-d H:i:s'),
                'created_at'      => date('Y-m-d H:i:s'),
            ]);

            $this->flashSuccess(sprintf('%s için %.2f kredi yüklendi. Yeni bakiye: %.2f', (string) $member['full_name'], $amount, (float) $credit['balance']));
            $this->redirect('/yonetim/odemeler');
        }

        // mode = subscription
        $planId = $this->request->int('plan_id');
        $days = $this->request->int('days', 0);
        $result = SubscriptionService::assign($organizationId, $planId, [
            'days'      => $days > 0 ? $days : null,
            'mark_paid' => true,
            'method'    => $method,
            'provider'  => 'manual',
            'note'      => $note !== '' ? $note : 'Panelden manuel satış',
        ]);

        if (!$result['ok']) {
            $this->flashError((string) $result['error']);
        } else {
            $this->flashSuccess('Abonelik atandı ve ödeme kaydedildi. Bitiş: ' . format_date((string) $result['period_end']));
        }
        $this->redirect('/yonetim/odemeler');
    }

    /** Bekleyen havale bildirimini onaylar: kredi yükler / aboneliği başlatır. */
    public function approveDeclared(): void
    {
        $paymentId = $this->request->int('payment_id');
        $amountRaw = trim((string) $this->request->input('amount', ''));
        $amount = $amountRaw === '' ? null : round((float) str_replace(',', '.', $amountRaw), 2);
        $note = mb_substr($this->request->string('note'), 0, 250);

        $result = PaymentService::approve($paymentId, $amount, $note !== '' ? $note : null);
        if (!$result['ok']) {
            $this->flashError((string) $result['error']);
        } elseif (!empty($result['already'])) {
            $this->flashError('Bu ödeme zaten işlenmiş (çift kayıt engellendi).');
        } else {
            $this->flashSuccess('Havale onaylandı ve kredi/abonelik uygulandı.');
        }
        $this->redirect('/yonetim/odemeler');
    }

    /** Bekleyen havale bildirimini reddeder. */
    public function rejectDeclared(): void
    {
        $paymentId = $this->request->int('payment_id');
        $reason = mb_substr($this->request->string('reason'), 0, 200);
        $result = PaymentService::reject($paymentId, $reason);
        if (!$result['ok']) {
            $this->flashError((string) $result['error']);
        } else {
            $this->flashSuccess('Ödeme reddedildi.');
        }
        $this->redirect('/yonetim/odemeler');
    }

    /** Ödeme durumunu değiştir (onayla / iade / başarısız). */
    public function updateStatus(): void
    {
        $paymentId = $this->request->int('payment_id');
        $status = (string) $this->request->input('status', 'paid');
        if (!in_array($status, ['pending', 'paid', 'failed', 'refunded'], true)) {
            $this->flashError('Geçersiz durum.');
            $this->redirect('/yonetim/odemeler');
        }

        $data = ['status' => $status];
        if ($status === 'paid') {
            $data['paid_at'] = date('Y-m-d H:i:s');
        }
        Database::update('payments', $data, 'id = :id', ['id' => $paymentId]);

        Audit::log('payment.status', 'payment', $paymentId, ['status' => $status]);
        $this->flashSuccess('Ödeme durumu güncellendi.');
        $this->redirect('/yonetim/odemeler');
    }
}
