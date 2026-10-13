<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Settings;
use App\Services\CreditService;
use App\Services\PaymentService;
use App\Services\SubscriptionService;

/**
 * Müşteri paneli ödeme merkezi.
 * Şimdilik tek yöntem: HAVALE / EFT. Ödeme sayfası (iyzico/PayTR) sonradan
 * aynı akışa (niyet -> ödeme -> uygula) eklenecek.
 */
final class BillingController extends Controller
{
    private function organizationId(): ?int
    {
        $userId = Auth::id();
        return $userId === null ? null : CreditService::organizationForUser($userId);
    }

    /** Ödeme merkezi: kredi yükleme, abonelik satın alma, bekleyen talepler. */
    public function index(): string
    {
        $userId = (int) Auth::id();
        $orgId = $this->organizationId();

        $pending = $orgId ? Database::select(
            "SELECT p.*, pl.name AS plan_name FROM payments p LEFT JOIN plans pl ON pl.id = p.plan_id
             WHERE p.organization_id = ? AND p.status = 'pending' ORDER BY p.id DESC LIMIT 20",
            [$orgId]
        ) : [];

        return $this->view('site/billing_checkout', [
            'title'        => t('billing.title'),
            'orgId'        => $orgId,
            'credit'       => $orgId ? CreditService::balance($orgId) : 0.0,
            'currency'     => PaymentService::currency(),
            'presets'      => PaymentService::presets(),
            'minTopup'     => PaymentService::minTopup(),
            'plans'        => SubscriptionService::plans(true),
            'current'      => $orgId ? SubscriptionService::current($orgId) : null,
            'accounts'     => PaymentService::bankAccounts(),
            'bankNote'     => (string) Settings::get('payment.bank_note', ''),
            'bankEnabled'  => PaymentService::bankEnabled(),
            'pending'      => $pending,
            'summary'      => $orgId ? PaymentService::summary($orgId) : ['paid' => 0.0, 'pending' => 0.0, 'count' => 0],
        ]);
    }

    /** Kredi yükleme talebi oluşturur. */
    public function startCredit(): void
    {
        $orgId = $this->organizationId();
        if ($orgId === null) {
            $this->flashError(t('billing.error_org'));
            $this->redirect('/panel/odeme');
        }
        $amount = round((float) str_replace(',', '.', (string) $this->request->input('amount', '0')), 2);
        $result = PaymentService::createIntent($orgId, (int) Auth::id(), 'credit', $amount);
        if (!$result['ok']) {
            $this->flashError((string) $result['error']);
            $this->redirect('/panel/odeme');
        }
        $this->flashSuccess(t('billing.intent_created'));
        $this->redirect('/panel/odeme/talep/' . (int) $result['payment_id']);
    }

    /** Abonelik satın alma / yenileme talebi oluşturur. */
    public function startSubscription(): void
    {
        $orgId = $this->organizationId();
        if ($orgId === null) {
            $this->flashError(t('billing.error_org'));
            $this->redirect('/panel/odeme');
        }
        $planId = $this->request->int('plan_id');
        $result = PaymentService::createIntent($orgId, (int) Auth::id(), 'subscription', 0.0, $planId ?: null);
        if (!$result['ok']) {
            $this->flashError((string) $result['error']);
            $this->redirect('/panel/odeme');
        }
        $this->flashSuccess(t('billing.intent_created'));
        $this->redirect('/panel/odeme/talep/' . (int) $result['payment_id']);
    }

    /** Havale talimat sayfası: ödeme kodu + IBAN listesi + bildirim formu. */
    public function show(string $id): string
    {
        $id = (int) $id;
        $orgId = $this->organizationId();
        $payment = $orgId ? Database::first(
            "SELECT p.*, pl.name AS plan_name FROM payments p LEFT JOIN plans pl ON pl.id = p.plan_id
             WHERE p.id = ? AND p.organization_id = ? LIMIT 1",
            [$id, $orgId]
        ) : null;
        if ($payment === null) {
            http_response_code(404);
            return $this->view('errors/404', ['title' => t('http.404')], 'layouts/site');
        }

        $events = Database::select('SELECT * FROM payment_events WHERE payment_id = ? ORDER BY id DESC LIMIT 20', [$id]);

        return $this->view('site/billing_intent', [
            'title'    => t('billing.intent_title'),
            'payment'  => $payment,
            'accounts' => PaymentService::bankAccounts(),
            'bankNote' => (string) Settings::get('payment.bank_note', ''),
            'currency' => (string) $payment['currency'],
            'events'   => $events,
        ]);
    }

    /** "Havaleyi yaptım" bildirimi. */
    public function declare(string $id): void
    {
        $id = (int) $id;
        $result = PaymentService::declareTransfer($id, (int) Auth::id(), [
            'sender' => $this->request->string('sender'),
            'bank'   => $this->request->string('bank'),
            'date'   => $this->request->string('date'),
            'note'   => $this->request->string('note'),
        ]);
        if (!$result['ok']) {
            $this->flashError((string) $result['error']);
        } else {
            $this->flashSuccess(t('billing.declared'));
        }
        $this->redirect('/panel/odeme/talep/' . $id);
    }

    /** Bekleyen talebi iptal eder. */
    public function cancel(string $id): void
    {
        $result = PaymentService::cancel((int) $id, (int) Auth::id());
        if (!$result['ok']) {
            $this->flashError((string) $result['error']);
        } else {
            $this->flashSuccess(t('billing.cancelled'));
        }
        $this->redirect('/panel/odeme');
    }

    /** Ödeme geçmişim. */
    public function history(): string
    {
        $orgId = $this->organizationId();
        return $this->view('site/billing_payments', [
            'title'    => t('billing.history'),
            'payments' => $orgId ? PaymentService::forOrganization($orgId, 100) : [],
            'credit'   => $orgId ? CreditService::balance($orgId) : 0.0,
            'currency' => PaymentService::currency(),
        ]);
    }
}
