<?php
use App\Core\Auth;
use App\Services\SubscriptionService;
$user = Auth::user();
$orgId = $orgId ?? null;
?>

<section class="section section--tight">
    <div class="container">
        <div class="row row--between mb-3">
            <div>
                <h1 style="font-size:1.7rem;margin-bottom:4px"><?= e(t('billing.title')) ?></h1>
                <p class="muted mb-0"><?= e(t('billing.subtitle')) ?></p>
            </div>
            <div class="row" style="gap:8px">
                <a class="btn btn-outline btn-sm" href="<?= e(url('/panel')) ?>"><?= e(t('nav.dashboard')) ?></a>
                <a class="btn btn-outline btn-sm" href="<?= e(url('/panel/odemelerim')) ?>"><?= e(t('billing.history')) ?></a>
            </div>
        </div>

        <div class="grid grid-3 mb-3">
            <div class="card stat">
                <div class="stat__value" style="color:var(--c-accent)"><?= e(number_format((float) ($credit ?? 0), 2, ',', '.')) ?></div>
                <div class="stat__label"><?= e(t('billing.credit_balance')) ?> (<?= e((string) $currency) ?>)</div>
            </div>
            <div class="card stat">
                <div class="stat__value"><?= e(number_format((float) ($summary['pending'] ?? 0), 2, ',', '.')) ?></div>
                <div class="stat__label"><?= e(t('billing.pending_total')) ?></div>
            </div>
            <div class="card stat">
                <div class="stat__value"><?= e(number_format((float) ($summary['paid'] ?? 0), 2, ',', '.')) ?></div>
                <div class="stat__label"><?= e(t('billing.paid_total')) ?></div>
            </div>
        </div>

        <?php if (!empty($focusCredit)): ?>
            <div class="card mb-3" style="border-color:var(--c-danger)">
                <p class="mb-0"><?= e(t('billing.credit_shortfall_hint')) ?></p>
                <a class="btn btn-accent btn-sm mt-2" href="#kredi"><?= e(t('billing.buy_credit')) ?> →</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($pending)): ?>
            <div class="card mb-3">
                <h2 style="font-size:1.1rem">⏳ <?= e(t('billing.pending_intents')) ?></h2>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr>
                            <th><?= e(t('billing.reference')) ?></th><th><?= e(t('billing.type')) ?></th>
                            <th><?= e(t('common.status')) ?></th><th><?= e(t('billing.amount')) ?></th><th></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($pending as $row): ?>
                            <tr>
                                <td class="mono"><?= e((string) $row['reference']) ?></td>
                                <td><?= e((string) $row['kind'] === 'subscription' ? t('billing.kind_subscription') : t('billing.kind_credit')) ?>
                                    <?php if (!empty($row['plan_name'])): ?>· <?= e((string) $row['plan_name']) ?><?php endif; ?></td>
                                <td><span class="badge badge--warn"><?= e(t('billing.status_pending')) ?></span></td>
                                <td class="mono"><?= e(number_format((float) $row['amount'], 2, ',', '.')) ?> <?= e((string) $row['currency']) ?></td>
                                <td><a class="btn btn-primary btn-sm" href="<?= e(url('/panel/odeme/talep/' . (int) $row['id'])) ?>"><?= e(t('billing.view_intent')) ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!($bankEnabled ?? false)): ?>
            <div class="card mb-3">
                <p class="muted mb-0"><?= e(t('billing.bank_disabled')) ?></p>
            </div>
        <?php endif; ?>

        <?php if (empty($accounts ?? [])): ?>
            <div class="card mb-3">
                <p class="muted mb-0"><?= e(t('billing.no_accounts')) ?></p>
            </div>
        <?php else: ?>
            <div class="card mb-3">
                <h2 style="font-size:1.1rem">🏦 <?= e(t('billing.bank_accounts')) ?></h2>
                <div class="grid grid-2">
                    <?php foreach ($accounts as $account): ?>
                        <div class="card" style="background:color-mix(in srgb, var(--c-primary) 4%, transparent)">
                            <strong><?= e($account['bank'] !== '' ? $account['bank'] : t('billing.bank_generic')) ?></strong>
                            <?php if ($account['owner'] !== ''): ?><p class="muted mb-0" style="font-size:13.5px"><?= e($account['owner']) ?></p><?php endif; ?>
                            <p class="mono mb-0" style="font-size:14px;word-break:break-all"><?= e($account['iban'] !== '' ? $account['iban'] : $account['account']) ?></p>
                            <?php if (!empty($account['branch'])): ?><p class="muted mb-0" style="font-size:13px"><?= e($account['branch']) ?></p><?php endif; ?>
                            <?php if (!empty($account['note'])): ?><p class="muted mb-0" style="font-size:13px"><?= e($account['note']) ?></p><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (!empty($bankNote)): ?>
                    <p class="hint mt-2"><?= e(__((string) $bankNote)) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-2 mb-3">
            <div class="card" id="kredi">
                <h2 style="font-size:1.1rem">💰 <?= e(t('billing.buy_credit')) ?></h2>
                <p class="muted" style="font-size:13.5px"><?= e(t('billing.buy_credit_hint', ['min' => number_format((float) $minTopup, 2, ',', '.'), 'currency' => (string) $currency])) ?></p>
                <form method="post" action="<?= e(url('/panel/odeme/kredi')) ?>" class="mt-2">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label><?= e(t('billing.quick_amounts')) ?></label>
                        <div class="row" style="gap:8px;flex-wrap:wrap">
                            <?php foreach (($presets ?? []) as $preset): ?>
                                <button type="button" class="btn btn-outline btn-sm" data-amount="<?= e((string) $preset) ?>">
                                    <?= e(number_format((float) $preset, 0, ',', '.')) ?> <?= e((string) $currency) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="field mt-2">
                        <label for="amount"><?= e(t('billing.amount')) ?></label>
                        <input id="amount" type="number" name="amount" min="<?= e((string) $minTopup) ?>" step="0.01"
                               value="<?= e((string) (($presets[0] ?? 250))) ?>" required>
                    </div>
                    <button class="btn btn-accent mt-2" type="submit"><?= e(t('billing.start_intent')) ?></button>
                </form>
                <script nonce="<?= e(csrf_nonce()) ?>">
                    document.querySelectorAll('[data-amount]').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            var input = document.getElementById('amount');
                            if (input) { input.value = btn.getAttribute('data-amount'); input.focus(); }
                        });
                    });
                </script>
            </div>

            <div class="card" id="paketler">
                <h2 style="font-size:1.1rem">💳 <?= e(t('billing.buy_subscription')) ?></h2>
                <?php
                $trialPlan = null;
                $standardPlan = null;
                foreach (($plans ?? []) as $row) {
                    if ((string) $row['key'] === 'trial') { $trialPlan = $row; }
                    if ((string) $row['key'] === 'standard') { $standardPlan = $row; }
                }
                $activeTrial = !empty($current) && (string) $current['plan_key'] === 'trial'
                    && in_array((string) $current['status'], ['trialing', 'active'], true);
                $money = static fn (float $value): string => number_format($value, 2, ',', '.');
                $standardPrice = $standardPlan !== null ? SubscriptionService::priceFor($standardPlan) : 0.0;
                $trialDays = $trialPlan !== null ? max(1, (int) ($trialPlan['trial_days'] ?? 180)) : 180;
                $trialMonths = max(1, (int) round($trialDays / 30));
                ?>
                <?php if (!empty($current)): ?>
                    <div class="alert alert--info" style="align-items:flex-start">
                        <span aria-hidden="true">ℹ️</span>
                        <div>
                            <strong><?= e(t('billing.current_plan', ['plan' => (string) $current['plan_name'], 'date' => format_date((string) $current['current_period_end'])])) ?></strong>
                            <div style="font-size:12.5px"><?= e(__('Aboneliğinizi yükseltmek için aşağıdaki paketlerden birini seçin.')) ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <p class="muted" style="font-size:13.5px;margin-top:0">
                    <?= e(__('İki basit paket: 6 ay ücretsiz deneme veya tek seferlik Standart Paket. Evrak eklemek her zaman ücretsizdir.')) ?>
                    <?= e(__('Paketi seçtikten sonra havale bilgileri ve ödeme kodunuz gösterilir; bildiriminiz yönetime iletilir.')) ?>
                </p>

                <div class="grid grid-2" style="gap:12px;align-items:stretch">
                    <div class="card plan" style="height:100%">
                        <span class="badge badge--info"><?= e(__('1. Paket')) ?></span>
                        <h3 class="mb-0" style="margin-top:8px"><?= e($trialPlan !== null ? (string) $trialPlan['name'] : __('6 Ay Ücretsiz Deneme')) ?></h3>
                        <div class="plan__price"><?= e('0') ?> <small>/ <?= e($trialMonths . ' ay') ?></small></div>
                        <p class="muted mb-0" style="font-size:13.5px"><?= e(__('Tek araçla, hiçbir ödeme yapmadan tüm sistemi 6 ay boyunca deneyin.')) ?></p>
                        <ul style="font-size:13px">
                            <li><?= e(__('6 ay boyunca tam erişim, ücret yok')) ?></li>
                            <li><?= e(__('Tek araç kaydı')) ?></li>
                            <li><?= e(__('Tüm evraklar ücretsiz, e-posta hatırlatmaları')) ?></li>
                            <li><?= e(__('Deneme sonunda Standart Pakete geçebilirsiniz')) ?></li>
                        </ul>
                        <?php if ($activeTrial): ?>
                            <span class="badge badge--ok"><?= e(__('Aktif deneme · bitiş ') . format_date((string) $current['current_period_end'])) ?></span>
                        <?php elseif (($trialUsed ?? 0) > 0 || !empty($isStandard)): ?>
                            <button class="btn btn-outline btn-block mt-2" type="button" disabled><?= e(__('Deneme daha önce kullanıldı')) ?></button>
                        <?php else: ?>
                            <form method="post" action="<?= e(url('/panel/odeme/deneme')) ?>" class="mt-2">
                                <?= csrf_field() ?>
                                <button class="btn btn-outline btn-block" type="submit"><?= e(__('Ücretsiz denemeyi başlat')) ?></button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="card plan plan--featured" style="height:100%">
                        <span class="badge badge--ok"><?= e(__('2. Paket · En çok tercih edilen')) ?></span>
                        <h3 class="mb-0" style="margin-top:8px"><?= e($standardPlan !== null ? (string) $standardPlan['name'] : 'Standart Paket') ?></h3>
                        <div class="plan__price"><?= e($money($standardPrice)) ?> <small>/ <?= e(__('tek seferlik')) ?></small></div>
                        <p class="muted mb-0" style="font-size:13.5px"><?= e(__('İlk araç için tek seferlik ödeme. Ek araçlarda kademeli indirim uygulanır.')) ?></p>
                        <ul style="font-size:13px">
                            <li><?= e(__('İlk araç: ') . $money($standardPrice)) ?></li>
                            <li><?= e(__('2. araç %20 indirimli: ') . $money($standardPrice * 0.8)) ?></li>
                            <li><?= e(__('3. araç %30 indirimli: ') . $money($standardPrice * 0.7)) ?></li>
                            <li><?= e(__('4. araç ve sonrası %30 indirimli: ') . $money($standardPrice * 0.7)) ?></li>
                            <li><?= e(__('Tüm evrak türleri ücretsiz, süresiz kullanım')) ?></li>
                        </ul>
                        <?php if (!empty($isStandard)): ?>
                            <span class="badge badge--ok"><?= e(__('Aktif paketiniz · bitiş ') . format_date((string) $current['current_period_end'])) ?></span>
                        <?php elseif ($standardPlan !== null): ?>
                            <form method="post" action="<?= e(url('/panel/odeme/abonelik')) ?>" class="mt-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="plan_id" value="<?= (int) $standardPlan['id'] ?>">
                                <button class="btn btn-accent btn-block" type="submit" data-loading-text="<?= e(t('billing.paying')) ?>">
                                    <?= e(__('Paketi seç ve havale bilgilerini al')) ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="muted" style="font-size:13px"><?= e(__('Paket bilgisi yüklenemedi. Lütfen bizimle iletişime geçin.')) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <p class="hint mt-2 mb-0"><?= e(__('Ek araç ücretleri kredi bakiyenizden düşülür. Evraklar için ayrıca ücret alınmaz.')) ?>
                    <?= e(t('billing.credit_hint', ['balance' => number_format((float) ($credit ?? 0), 2, ',', '.'), 'currency' => (string) ($currency ?? '')])) ?></p>
            </div>
        </div>
    </div>
</section>
