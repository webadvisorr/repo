<?php
use App\Core\Auth;
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
                    <p class="hint mt-2"><?= e((string) $bankNote) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-2 mb-3">
            <div class="card">
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

            <div class="card">
                <h2 style="font-size:1.1rem">💳 <?= e(t('billing.buy_subscription')) ?></h2>
                <?php if (!empty($current)): ?>
                    <p class="muted" style="font-size:13.5px">
                        <?= e(t('billing.current_plan', ['plan' => (string) $current['plan_name'], 'date' => format_date((string) $current['current_period_end'])])) ?>
                    </p>
                <?php endif; ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th><?= e(t('billing.plan')) ?></th><th><?= e(t('billing.price')) ?></th><th><?= e(t('billing.limit')) ?></th><th></th></tr></thead>
                        <tbody>
                        <?php foreach (($plans ?? []) as $plan): ?>
                            <tr>
                                <td><strong><?= e((string) $plan['name']) ?></strong></td>
                                <td class="mono"><?= e(number_format((float) $plan['price'], 2, ',', '.')) ?> <?= e((string) $plan['currency']) ?></td>
                                <td class="muted"><?= e((int) $plan['max_vehicles'] . ' ' . t('billing.vehicle_unit')) ?></td>
                                <td>
                                    <?php if ((float) $plan['price'] > 0): ?>
                                        <form method="post" action="<?= e(url('/panel/odeme/abonelik')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
                                            <button class="btn btn-primary btn-sm" type="submit"><?= e(t('billing.select_plan')) ?></button>
                                        </form>
                                    <?php else: ?>
                                        <span class="muted" style="font-size:13px"><?= e(t('billing.free_plan')) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
