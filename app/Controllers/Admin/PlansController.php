<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Database;
use App\Services\SubscriptionService;

/** Plan yönetimi: fiyat, limit, kanal ve aktiflik. */
final class PlansController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function index(): string
    {
        $plans = SubscriptionService::plans(false);
        foreach ($plans as &$plan) {
            $plan['subscriber_count'] = (int) Database::scalar(
                "SELECT COUNT(*) FROM subscriptions WHERE plan_id = ? AND status IN ('active','trialing')",
                [(int) $plan['id']]
            );
            $plan['revenue'] = round((float) Database::scalar(
                "SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'paid'
                 AND subscription_id IN (SELECT id FROM subscriptions WHERE plan_id = ?)",
                [(int) $plan['id']]
            ), 2);
        }
        unset($plan);

        return $this->view('admin/plans', [
            'title' => t('admin.plans'),
            'plans' => $plans,
            'totals' => [
                'active_subs' => (int) Database::scalar("SELECT COUNT(*) FROM subscriptions WHERE status IN ('active','trialing')"),
                'mrr'         => round((float) Database::scalar(
                    "SELECT COALESCE(SUM(p.price),0) FROM subscriptions s JOIN plans p ON p.id = s.plan_id
                     WHERE s.status = 'active' AND p.billing_interval = 'month'"
                ), 2),
                'revenue'     => round((float) Database::scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'paid'"), 2),
            ],
        ]);
    }

    public function update(): void
    {
        $planId = $this->request->int('plan_id');
        $plan = SubscriptionService::plan($planId);
        if ($plan === null) {
            $this->flashError('Plan bulunamadı.');
            $this->redirect('/yonetim/planlar');
        }

        $data = $this->validate(
            [
                'name'          => $this->request->string('name'),
                'price'         => $this->request->string('price'),
                'currency'      => $this->request->string('currency'),
                'interval'      => $this->request->string('billing_interval'),
                'max_vehicles'  => $this->request->string('max_vehicles'),
                'max_users'     => $this->request->string('max_users'),
                'max_documents' => $this->request->string('max_documents_per_vehicle'),
                'trial_days'    => $this->request->string('trial_days'),
            ],
            [
                'name'          => 'required|min:2|max:80',
                'price'         => 'required|numeric',
                'currency'      => 'required|in:TRY,EUR,USD,GBP',
                'interval'      => 'required|in:month,year,lifetime',
                'max_vehicles'  => 'required|integer',
                'max_users'     => 'required|integer',
                'max_documents' => 'required|integer',
                'trial_days'    => 'required|integer',
            ],
            ['name' => 'Plan adı', 'price' => 'Fiyat', 'currency' => 'Para birimi', 'interval' => 'Periyot'],
            '/yonetim/planlar'
        );

        $channels = array_values(array_intersect(
            $this->request->array('channels'),
            ['email', 'sms', 'whatsapp', 'telegram', 'push']
        ));

        Database::update('plans', [
            'name'                      => (string) $data['name'],
            'price'                     => max(0, round((float) str_replace(',', '.', (string) $data['price']), 2)),
            'currency'                  => (string) $data['currency'],
            'billing_interval'          => (string) $data['interval'],
            'max_vehicles'              => max(1, min(100000, (int) $data['max_vehicles'])),
            'max_users'                 => max(1, min(1000, (int) $data['max_users'])),
            'max_documents_per_vehicle' => max(1, min(1000, (int) $data['max_documents'])),
            'trial_days'                => max(0, min(365, (int) $data['trial_days'])),
            'channels'                  => $channels === [] ? 'email' : implode(',', $channels),
            'is_active'                 => $this->request->bool('is_active') ? 1 : 0,
            'description'               => mb_substr($this->request->string('description'), 0, 250) ?: null,
        ], 'id = :id', ['id' => $planId]);

        Audit::log('plan.update', 'plan', $planId);
        $this->flashSuccess(t('common.saved'));
        $this->redirect('/yonetim/planlar');
    }

    /** Yeni plan ekle. */
    public function store(): void
    {
        $key = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', (string) $this->request->input('key', '')) ?? '');
        if ($key === '' || mb_strlen($key) < 2) {
            $this->flashError('Plan anahtarı en az 2 karakter olmalı (harf, rakam, alt çizgi).');
            $this->redirect('/yonetim/planlar');
        }
        if (Database::scalar('SELECT id FROM plans WHERE `key` = ?', [$key])) {
            $this->flashError('Bu plan anahtarı zaten var.');
            $this->redirect('/yonetim/planlar');
        }

        $planId = Database::insert('plans', [
            'key'                     => $key,
            'name'                    => mb_substr($this->request->string('name') ?: $key, 0, 80),
            'description'             => null,
            'price'                   => max(0, round((float) str_replace(',', '.', $this->request->string('price', '0')), 2)),
            'currency'                => in_array($this->request->string('currency', 'TRY'), ['TRY', 'EUR', 'USD', 'GBP'], true) ? $this->request->string('currency', 'TRY') : 'TRY',
            'billing_interval'        => in_array($this->request->string('billing_interval', 'month'), ['month', 'year', 'lifetime'], true) ? $this->request->string('billing_interval', 'month') : 'month',
            'trial_days'              => max(0, min(365, $this->request->int('trial_days', 14))),
            'max_vehicles'            => max(1, $this->request->int('max_vehicles', 1)),
            'max_users'               => max(1, $this->request->int('max_users', 1)),
            'max_documents_per_vehicle' => max(1, $this->request->int('max_documents_per_vehicle', 20)),
            'channels'                => 'email,sms,telegram',
            'is_active'               => 1,
            'sort_order'              => $this->request->int('sort_order', 90),
            'created_at'              => date('Y-m-d H:i:s'),
        ]);

        Audit::log('plan.create', 'plan', $planId, ['key' => $key]);
        $this->flashSuccess('Plan eklendi.');
        $this->redirect('/yonetim/planlar');
    }

    /** Planı sil (aktif abone varsa engellenir). */
    public function destroy(): void
    {
        $planId = $this->request->int('plan_id');
        $active = (int) Database::scalar("SELECT COUNT(*) FROM subscriptions WHERE plan_id = ? AND status IN ('active','trialing')", [$planId]);
        if ($active > 0) {
            $this->flashError("Bu planda {$active} aktif abone var. Önce onları başka plana taşıyın.");
            $this->redirect('/yonetim/planlar');
        }
        Database::delete('plans', 'id = ?', [$planId]);
        Audit::log('plan.delete', 'plan', $planId);
        $this->flashSuccess(t('common.deleted'));
        $this->redirect('/yonetim/planlar');
    }
}
