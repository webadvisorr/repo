<?php
use App\Core\Settings;
use App\Core\Translator;
use App\Services\CreditService;

$statusLabels = ['trialing' => 'Deneme', 'active' => 'Aktif', 'past_due' => 'Ödeme bekliyor', 'cancelled' => 'İptal edildi', 'expired' => 'Süresi bitti'];
$subStatus = (string) ($subscription['status'] ?? '');
$balance = (float) ($member['credit_balance'] ?? 0);
$currency = (string) Settings::get('payment.currency', 'TRY');
$totalPaid = 0.0;
foreach ($payments as $p) {
    if ((string) $p['status'] === 'paid') {
        $totalPaid += (float) $p['amount'];
    }
}
?>
<div class="row row--between mb-3">
    <div>
        <a class="muted" href="<?= e(url('/yonetim/uyeler')) ?>">← <?= e(t('admin.members')) ?></a>
        <h1 style="font-size:1.6rem;margin:6px 0 4px"><?= e((string) $member['full_name']) ?></h1>
        <p class="muted mb-0">
            <?= e((string) $member['email']) ?>
            <?php if (!empty($member['phone'])): ?> · <?= e((string) $member['phone']) ?><?php endif; ?>
            · <span class="badge <?= (string) $member['status'] === 'active' ? 'badge--ok' : 'badge--warn' ?>"><?= e((string) $member['status']) ?></span>
            <?php if (!empty($member['organization_name'])): ?> · <?= e((string) $member['organization_name']) ?><?php endif; ?>
        </p>
    </div>
    <div class="row">
        <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/odemeler?q=' . urlencode((string) $member['email']))) ?>"><?= e(__('Ödemeler')) ?></a>
        <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/uyeler?q=' . urlencode((string) $member['email']))) ?>"><?= e(__('Listede bul')) ?></a>
    </div>
</div>

<div class="grid grid-4 mb-3">
    <div class="card stat">
        <div class="stat__value" style="color:var(--c-accent)"><?= e(number_format($balance, 2, ',', '.')) ?></div>
        <div class="stat__label"><?= e('Kredi bakiyesi (' . $currency . ')') ?></div>
    </div>
    <div class="card stat">
        <div class="stat__value" style="font-size:1.15rem"><?= e((string) ($subscription['plan_name'] ?? 'Abonelik yok')) ?></div>
        <div class="stat__label">
            <?= e($subStatus !== '' ? ($statusLabels[$subStatus] ?? $subStatus) : '—') ?>
            <?= !empty($subscription['current_period_end']) ? ' · ' . e(format_date((string) $subscription['current_period_end'])) : '' ?>
        </div>
    </div>
    <div class="card stat"><div class="stat__value"><?= count($vehicles) ?></div><div class="stat__label"><?= e(__('Araç')) ?></div></div>
    <div class="card stat"><div class="stat__value"><?= e(number_format($totalPaid, 2, ',', '.')) ?></div><div class="stat__label"><?= e('Tahsil edilen (' . $currency . ')') ?></div></div>
</div>

<div class="grid grid-2 mb-3">
    <!-- ================= KREDİ ================= -->
    <div class="card">
        <h2 style="font-size:1.1rem">💰 <?= e(__('Kredi yönetimi')) ?></h2>
        <p class="muted" style="font-size:13.5px">
            <?= e(__('Krediyi SMS paketi, ek araç veya abonelik ödemesi için kullanabilirsiniz. Her hareket kayıt altına alınır.')) ?>
        </p>

        <form method="post" action="<?= e(url('/yonetim/uyeler/kredi-ekle')) ?>" class="mb-2">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= (int) $member['id'] ?>">
            <div class="field-grid">
                <div class="field">
                    <label for="credit-amount"><?= e('Eklenecek kredi (' . $currency . ')') ?></label>
                    <input id="credit-amount" type="number" name="amount" step="0.01" min="0.01" max="100000" required value="100">
                </div>
                <div class="field">
                    <label for="credit-reason"><?= e(__('Kaynak / gerekçe')) ?></label>
                    <select id="credit-reason" name="reason">
                        <?php foreach (['manual' => 'Manuel ekleme', 'bank_transfer' => 'Havale / EFT', 'cash' => 'Nakit', 'card' => 'Kredi kartı', 'bonus' => 'Bonus / promosyon', 'refund' => 'İade', 'correction' => 'Düzeltme'] as $key => $label): ?>
                            <option value="<?= e($key) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="field">
                <label for="credit-note"><?= e(__('Not')) ?> <span class="muted">(<?= e(t('common.optional')) ?>)</span></label>
                <input id="credit-note" type="text" name="note" maxlength="250" placeholder="<?= e(__('Örn. 3 aylık SMS paketi bedeli')) ?>">
            </div>
            <button class="btn btn-accent" type="submit"><?= e(__('Kredi ekle')) ?></button>
        </form>

        <details>
            <summary class="muted" style="cursor:pointer;font-size:13.5px"><?= e(__('Kredi düş / düzeltme')) ?></summary>
            <form method="post" action="<?= e(url('/yonetim/uyeler/kredi-dus')) ?>" class="mt-2" data-confirm="<?= e(__('Kredi düşülecek. Onaylıyor musunuz?')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= (int) $member['id'] ?>">
                <div class="field-grid">
                    <div class="field">
                        <label for="debit-amount"><?= e(__('Düşülecek tutar')) ?></label>
                        <input id="debit-amount" type="number" name="amount" step="0.01" min="0.01" max="100000" required>
                    </div>
                    <div class="field">
                        <label for="debit-note"><?= e(__('Gerekçe / not')) ?></label>
                        <input id="debit-note" type="text" name="note" maxlength="250">
                    </div>
                </div>
                <button class="btn btn-outline btn-sm" type="submit" style="color:var(--c-danger);border-color:var(--c-danger)"><?= e(__('Kredi düş')) ?></button>
            </form>
        </details>

        <h3 class="mt-3" style="font-size:1rem"><?= e(__('Kredi hareketleri')) ?></h3>
        <?php if (empty($ledger)): ?>
            <p class="muted mb-0" style="font-size:14px"><?= e(__('Henüz kredi hareketi yok.')) ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th><?= e(__('Tarih')) ?></th><th><?= e(__('İşlem')) ?></th><th><?= e(__('Tutar')) ?></th><th><?= e(__('Bakiye')) ?></th><th><?= e(__('Yetkili')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($ledger as $row): ?>
                        <tr>
                            <td class="muted"><?= e(format_date((string) $row['created_at'], 'd.m.Y H:i')) ?></td>
                            <td>
                                <?= e((string) (CreditService::REASONS[(string) $row['reason']] ?? $row['reason'])) ?>
                                <?php if (!empty($row['note'])): ?><div class="muted" style="font-size:12px"><?= e((string) $row['note']) ?></div><?php endif; ?>
                            </td>
                            <td class="mono" style="color:<?= (string) $row['direction'] === 'credit' ? 'var(--c-accent)' : 'var(--c-danger)' ?>">
                                <?= e(((string) $row['direction'] === 'credit' ? '+' : '−') . number_format((float) $row['amount'], 2, ',', '.')) ?>
                            </td>
                            <td class="mono"><?= e(number_format((float) $row['balance_after'], 2, ',', '.')) ?></td>
                            <td class="muted"><?= e((string) ($row['admin_name'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================= ABONELİK ================= -->
    <div class="card">
        <h2 style="font-size:1.1rem">💳 <?= e(__('Abonelik yönetimi')) ?></h2>

        <?php if ($subscription !== null): ?>
            <div class="media-preview mb-2" style="flex-direction:column;align-items:flex-start;gap:6px">
                <div class="row row--between w-100">
                    <strong style="font-size:1.05rem"><?= e((string) $subscription['plan_name']) ?></strong>
                    <?php $cls = match ($subStatus) { 'active' => 'badge--ok', 'trialing' => 'badge--info', 'past_due' => 'badge--warn', default => 'badge--danger' }; ?>
                    <span class="badge <?= $cls ?>"><?= e($statusLabels[$subStatus] ?? $subStatus) ?></span>
                </div>
                <div class="muted" style="font-size:13px">
                    <?= e('Dönem: ' . format_date((string) $subscription['current_period_start']) . ' → ' . format_date((string) $subscription['current_period_end'])) ?>
                    <?php $subInterval = (string) ($subscription['billing_interval'] ?? 'month'); ?>
                    <?php $subPrice = (float) ($subscription['price_at_signup'] ?? \App\Services\SubscriptionService::priceFor($subscription, $subInterval)); ?>
                    <br><?= e(t('billing.interval') . ': ' . t('billing.interval_' . $subInterval) . ' · ' . number_format($subPrice, 2, ',', '.') . ' ' . $currency) ?>
                    <?php if ((int) $subscription['cancel_at_period_end'] === 1): ?>
                        <br><strong style="color:var(--c-warning)"><?= e(__('Dönem sonunda iptal edilecek.')) ?></strong>
                    <?php endif; ?>
                    <br><?= e('Limitler: ' . (int) $subscription['max_vehicles'] . ' araç · ' . (int) $subscription['max_users'] . ' kullanıcı · ' . strtoupper((string) $subscription['channels'])) ?>
                </div>
            </div>

            <div class="row mb-2">
                <form method="post" action="<?= e(url('/yonetim/uyeler/abonelik-uzat')) ?>" class="row">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= (int) $member['id'] ?>">
                    <input type="hidden" name="subscription_id" value="<?= (int) $subscription['id'] ?>">
                    <input type="number" name="days" value="30" min="1" max="3650" style="width:92px" aria-label="<?= e(__('Gün')) ?>">
                    <label class="check" style="align-items:center">
                        <input type="checkbox" name="mark_paid" value="1" checked>
                        <span style="font-size:13px"><?= e(__('Ödemeyi kaydet')) ?></span>
                    </label>
                    <button class="btn btn-primary btn-sm" type="submit"><?= e(__('Süre uzat')) ?></button>
                </form>
            </div>
            <div class="row mb-2">
                <form method="post" action="<?= e(url('/yonetim/uyeler/kredi-ile-ode')) ?>" data-confirm="<?= e(__('Abonelik kredi bakiyesinden ödenecek. Onaylıyor musunuz?')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= (int) $member['id'] ?>">
                    <input type="hidden" name="subscription_id" value="<?= (int) $subscription['id'] ?>">
                    <button class="btn btn-accent btn-sm" type="submit" <?= $balance < (float) ($subscription['plan_price'] ?? 0) ? 'disabled' : '' ?>>
                        <?= e('Krediden ' . number_format((float) ($subscription['plan_price'] ?? 0), 2, ',', '.') . ' öde ve uzat') ?>
                    </button>
                </form>
                <?php if ((int) $subscription['cancel_at_period_end'] === 1 || $subStatus === 'cancelled'): ?>
                    <form method="post" action="<?= e(url('/yonetim/uyeler/abonelik-devam')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= (int) $member['id'] ?>">
                        <input type="hidden" name="subscription_id" value="<?= (int) $subscription['id'] ?>">
                        <button class="btn btn-outline btn-sm" type="submit"><?= e(__('İptali geri al')) ?></button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= e(url('/yonetim/uyeler/abonelik-iptal')) ?>" data-confirm="<?= e(__('Abonelik dönem sonunda iptal edilecek. Onaylıyor musunuz?')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= (int) $member['id'] ?>">
                        <input type="hidden" name="subscription_id" value="<?= (int) $subscription['id'] ?>">
                        <button class="btn btn-outline btn-sm" type="submit"><?= e(__('Dönem sonunda iptal')) ?></button>
                    </form>
                    <form method="post" action="<?= e(url('/yonetim/uyeler/abonelik-iptal')) ?>" data-confirm="<?= e(__('Abonelik HEMEN iptal edilecek. Onaylıyor musunuz?')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= (int) $member['id'] ?>">
                        <input type="hidden" name="subscription_id" value="<?= (int) $subscription['id'] ?>">
                        <input type="hidden" name="immediate" value="1">
                        <button class="btn btn-outline btn-sm" type="submit" style="color:var(--c-danger);border-color:var(--c-danger)"><?= e(__('Hemen iptal')) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="alert alert--info"><span>ℹ️</span><div><?= e(__('Bu üyenin aktif bir aboneliği yok. Aşağıdan plan atayabilirsiniz.')) ?></div></div>
        <?php endif; ?>

        <h3 class="mt-3" style="font-size:1rem"><?= e(__('Plan ata / plan değiştir')) ?></h3>
        <form method="post" action="<?= e(url('/yonetim/uyeler/plan-ata')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= (int) $member['id'] ?>">
            <div class="field-grid">
                <div class="field">
                    <label for="plan-id"><?= e(__('Plan')) ?></label>
                    <select id="plan-id" name="plan_id" required>
                        <?php foreach ($plans as $p): ?>
                            <?php
                            $pIntervals = \App\Services\SubscriptionService::intervals($p);
                            $pParts = [];
                            if (isset($pIntervals['month'])) {
                                $pParts[] = 'Aylık ' . number_format($pIntervals['month'], 2, ',', '.');
                            }
                            if (isset($pIntervals['year'])) {
                                $pParts[] = 'Yıllık ' . number_format($pIntervals['year'], 2, ',', '.');
                            }
                            if (isset($pIntervals['lifetime'])) {
                                $pParts[] = 'Ömür boyu ' . number_format($pIntervals['lifetime'], 2, ',', '.');
                            }
                            ?>
                            <option value="<?= (int) $p['id'] ?>" <?= (int) ($subscription['plan_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
                                <?= e((string) $p['name']) ?> — <?= e(implode(' · ', $pParts) ?: 'ücretsiz') ?> <?= e((string) $p['currency']) ?>
                                <?= (int) $p['is_active'] === 1 ? '' : '(pasif)' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="plan-interval"><?= e(__('Dönem')) ?></label>
                    <select id="plan-interval" name="interval">
                        <?php foreach (['month' => t('billing.interval_month'), 'year' => t('billing.interval_year'), 'lifetime' => t('billing.interval_lifetime')] as $k => $v): ?>
                            <option value="<?= e($k) ?>"><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="plan-days"><?= e(__('Süre (gün)')) ?> <span class="muted">(<?= e(t('common.optional')) ?>)</span></label>
                    <input id="plan-days" type="number" name="days" min="0" max="3650" placeholder="<?= e(__('Boş = plan dönemi')) ?>">
                </div>
                <div class="field">
                    <label for="plan-trial"><?= e(__('Deneme süresi (gün)')) ?></label>
                    <input id="plan-trial" type="number" name="trial_days" min="0" max="365" value="0">
                </div>
                <div class="field">
                    <label for="plan-method"><?= e(__('Ödeme yöntemi')) ?></label>
                    <select id="plan-method" name="method">
                        <?php foreach (['manual' => 'Manuel', 'bank_transfer' => 'Havale / EFT', 'cash' => 'Nakit', 'card' => 'Kredi kartı'] as $k => $v): ?>
                            <option value="<?= e($k) ?>"><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="field">
                <label for="plan-note"><?= e(__('Not')) ?> <span class="muted">(<?= e(t('common.optional')) ?>)</span></label>
                <input id="plan-note" type="text" name="note" maxlength="250">
            </div>
            <div class="field check">
                <input id="plan-paid" type="checkbox" name="mark_paid" value="1">
                <label for="plan-paid"><?= e(__('Ödemeyi alındı olarak kaydet')) ?></label>
            </div>
            <button class="btn btn-accent" type="submit"><?= e(__('Aboneliği ata')) ?></button>
        </form>

        <h3 class="mt-3" style="font-size:1rem"><?= e(__('Abonelik geçmişi')) ?></h3>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th><?= e(__('Plan')) ?></th><th><?= e(__('Durum')) ?></th><th><?= e(__('Dönem')) ?></th><th><?= e(__('Atayan')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($subscriptions as $row): ?>
                    <tr>
                        <td><?= e((string) $row['plan_name']) ?></td>
                        <td><span class="badge <?= (string) $row['status'] === 'active' ? 'badge--ok' : 'badge--info' ?>"><?= e((string) $row['status']) ?></span></td>
                        <td class="muted" style="font-size:12.5px"><?= e(format_date((string) $row['current_period_start']) . ' → ' . format_date((string) $row['current_period_end'])) ?></td>
                        <td class="muted"><?= e((string) ($row['assigned_by_name'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($subscriptions === []): ?><tr><td colspan="4" class="muted"><?= e(__('Kayıt yok.')) ?></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
