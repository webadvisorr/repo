<?php
use App\Core\Auth;
$user = Auth::user();
$payment = $payment ?? [];
$status = (string) ($payment['status'] ?? 'pending');
$badge = ['pending' => 'badge--warn', 'paid' => 'badge--ok', 'failed' => 'badge--danger', 'refunded' => 'badge--info'][$status] ?? 'badge--info';
$meta = json_decode((string) ($payment['meta_json'] ?? ''), true);
$declared = is_array($meta) && !empty($meta['declared']) ? $meta['declared'] : null;
?>
<section class="section section--tight">
    <div class="container">
        <div class="row row--between mb-3">
            <div>
                <h1 style="font-size:1.6rem;margin-bottom:4px"><?= e(t('billing.intent_title')) ?></h1>
                <p class="muted mb-0"><?= e(t('billing.intent_subtitle')) ?></p>
            </div>
            <div class="row" style="gap:8px">
                <a class="btn btn-outline btn-sm" href="<?= e(url('/panel/odeme')) ?>"><?= e(t('billing.back_to_billing')) ?></a>
            </div>
        </div>

        <div class="card mb-3">
            <div class="grid grid-3">
                <div>
                    <div class="muted" style="font-size:12.5px"><?= e(t('billing.reference')) ?></div>
                    <div class="mono" style="font-size:1.2rem;font-weight:700;letter-spacing:1px"><?= e((string) $payment['reference']) ?></div>
                </div>
                <div>
                    <div class="muted" style="font-size:12.5px"><?= e(t('billing.amount')) ?></div>
                    <div class="mono" style="font-size:1.2rem;font-weight:700"><?= e(number_format((float) $payment['amount'], 2, ',', '.')) ?> <?= e((string) $payment['currency']) ?></div>
                </div>
                <div>
                    <div class="muted" style="font-size:12.5px"><?= e(t('common.status')) ?></div>
                    <div><span class="badge <?= e($badge) ?>"><?= e(t('billing.status_' . $status)) ?></span></div>
                </div>
            </div>
            <p class="muted mt-2 mb-0" style="font-size:13.5px">
                <?= e((string) $payment['kind'] === 'subscription' ? t('billing.kind_subscription') : t('billing.kind_credit')) ?>
                <?php if (!empty($payment['plan_name'])): ?>· <?= e((string) $payment['plan_name']) ?><?php endif; ?>
                <?php if (!empty($payment['expires_at'])): ?>· <?= e(t('billing.expires_at', ['date' => format_date((string) $payment['expires_at'], 'd.m.Y H:i')])) ?><?php endif; ?>
            </p>
        </div>

        <?php if ($status === 'pending'): ?>
            <div class="card mb-3">
                <h2 style="font-size:1.1rem">1. <?= e(t('billing.step_pay')) ?></h2>
                <p class="muted" style="font-size:13.5px"><?= e(t('billing.pay_hint', ['ref' => (string) $payment['reference']])) ?></p>
                <?php if (empty($accounts)): ?>
                    <p class="muted mb-0"><?= e(t('billing.no_accounts')) ?></p>
                <?php else: ?>
                    <div class="grid grid-2">
                        <?php foreach ($accounts as $account): ?>
                            <div class="card" style="background:color-mix(in srgb, var(--c-primary) 4%, transparent)">
                                <strong><?= e($account['bank'] !== '' ? $account['bank'] : t('billing.bank_generic')) ?></strong>
                                <?php if ($account['owner'] !== ''): ?><p class="muted mb-0" style="font-size:13.5px"><?= e($account['owner']) ?></p><?php endif; ?>
                                <p class="mono mb-0" style="font-size:14px;word-break:break-all"><?= e($account['iban'] !== '' ? $account['iban'] : $account['account']) ?></p>
                                <?php if (!empty($account['branch'])): ?><p class="muted mb-0" style="font-size:13px"><?= e($account['branch']) ?></p><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($bankNote)): ?>
                    <p class="hint mt-2"><?= e(__((string) $bankNote)) ?></p>
                <?php endif; ?>
            </div>

            <div class="card mb-3">
                <h2 style="font-size:1.1rem">2. <?= e(t('billing.step_declare')) ?></h2>
                <?php if ($declared !== null): ?>
                    <p class="muted" style="font-size:13.5px"><?= e(t('billing.declared_at', ['date' => format_date((string) ($declared['at'] ?? ''), 'd.m.Y H:i')])) ?></p>
                    <ul class="muted" style="font-size:13.5px">
                        <?php if (!empty($declared['sender'])): ?><li><?= e(t('billing.declared_sender')) ?>: <?= e((string) $declared['sender']) ?></li><?php endif; ?>
                        <?php if (!empty($declared['bank'])): ?><li><?= e(t('billing.declared_bank')) ?>: <?= e((string) $declared['bank']) ?></li><?php endif; ?>
                        <?php if (!empty($declared['date'])): ?><li><?= e(t('billing.declared_date')) ?>: <?= e((string) $declared['date']) ?></li><?php endif; ?>
                        <?php if (!empty($declared['note'])): ?><li><?= e(t('billing.note')) ?>: <?= e((string) $declared['note']) ?></li><?php endif; ?>
                    </ul>
                    <p class="muted mb-0" style="font-size:13px"><?= e(t('billing.awaiting_approval')) ?></p>
                <?php else: ?>
                    <form method="post" action="<?= e(url('/panel/odeme/talep/' . (int) $payment['id'] . '/bildir')) ?>">
                        <?= csrf_field() ?>
                        <div class="field-grid">
                            <div class="field">
                                <label for="sender"><?= e(t('billing.declared_sender')) ?></label>
                                <input id="sender" type="text" name="sender" maxlength="120" placeholder="<?= e(t('billing.sender_placeholder')) ?>">
                            </div>
                            <div class="field">
                                <label for="dbank"><?= e(t('billing.declared_bank')) ?></label>
                                <input id="dbank" type="text" name="bank" maxlength="120">
                            </div>
                            <div class="field">
                                <label for="ddate"><?= e(t('billing.declared_date')) ?></label>
                                <input id="ddate" type="date" name="date">
                            </div>
                        </div>
                        <div class="field mt-2">
                            <label for="note"><?= e(t('billing.note')) ?></label>
                            <textarea id="note" name="note" maxlength="400" rows="2"></textarea>
                        </div>
                        <button class="btn btn-accent mt-2" type="submit"><?= e(t('billing.declare_button')) ?></button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card mb-3">
                <form method="post" action="<?= e(url('/panel/odeme/talep/' . (int) $payment['id'] . '/iptal')) ?>"
                      data-confirm="<?= e(t('billing.cancel_confirm')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline btn-sm" type="submit" style="color:var(--c-danger);border-color:var(--c-danger)">
                        <?= e(t('billing.cancel_intent')) ?>
                    </button>
                </form>
            </div>
        <?php elseif ($status === 'paid'): ?>
            <div class="card mb-3">
                <p class="mb-0">✅ <?= e(t('billing.paid_ok', ['date' => format_date((string) $payment['paid_at'], 'd.m.Y H:i')])) ?></p>
            </div>
        <?php else: ?>
            <div class="card mb-3">
                <p class="mb-0">⚠️ <?= e(t('billing.failed_intent', ['reason' => (string) ($payment['notes'] ?? '')])) ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($events)): ?>
            <div class="card">
                <h2 style="font-size:1.05rem"><?= e(t('billing.intent_log')) ?></h2>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th><?= e(t('billing.date')) ?></th><th><?= e(t('billing.event')) ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td class="muted"><?= e(format_date((string) $event['created_at'], 'd.m.Y H:i')) ?></td>
                                <td class="mono" style="font-size:13px"><?= e((string) $event['event']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
