<?php
use App\Core\Auth;
$user = Auth::user();
?>
<section class="section section--tight">
    <div class="container">
        <div class="row row--between mb-3">
            <div>
                <h1 style="font-size:1.6rem;margin-bottom:4px"><?= e(t('billing.history')) ?></h1>
                <p class="muted mb-0"><?= e(t('billing.history_subtitle')) ?></p>
            </div>
            <div class="row" style="gap:8px">
                <a class="btn btn-outline btn-sm" href="<?= e(url('/panel/odeme')) ?>"><?= e(t('billing.title')) ?></a>
                <a class="btn btn-outline btn-sm" href="<?= e(url('/panel')) ?>"><?= e(t('nav.dashboard')) ?></a>
            </div>
        </div>

        <div class="card">
            <?php if (empty($payments)): ?>
                <p class="muted mb-0"><?= e(t('billing.no_payments')) ?></p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr>
                            <th><?= e(t('billing.date')) ?></th>
                            <th><?= e(t('billing.reference')) ?></th>
                            <th><?= e(t('billing.type')) ?></th>
                            <th><?= e(t('billing.amount')) ?></th>
                            <th><?= e(t('billing.method')) ?></th>
                            <th><?= e(t('common.status')) ?></th>
                            <th></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($payments as $row): ?>
                            <?php
                            $status = (string) $row['status'];
                            $badge = ['pending' => 'badge--warn', 'paid' => 'badge--ok', 'failed' => 'badge--danger', 'refunded' => 'badge--info'][$status] ?? 'badge--info';
                            ?>
                            <tr>
                                <td class="muted"><?= e(format_date((string) $row['created_at'], 'd.m.Y H:i')) ?></td>
                                <td class="mono"><?= e((string) ($row['reference'] ?? '-')) ?></td>
                                <td><?= e((string) $row['kind'] === 'subscription' ? t('billing.kind_subscription') : ((string) $row['kind'] === 'credit' ? t('billing.kind_credit') : t('billing.kind_other'))) ?>
                                    <?php if (!empty($row['plan_name'])): ?>· <?= e((string) $row['plan_name']) ?><?php endif; ?></td>
                                <td class="mono"><?= e(number_format((float) $row['amount'], 2, ',', '.')) ?> <?= e((string) $row['currency']) ?></td>
                                <td class="muted"><?= e((string) $row['method']) ?></td>
                                <td><span class="badge <?= e($badge) ?>"><?= e(t('billing.status_' . $status)) ?></span></td>
                                <td><?php if ($status === 'pending'): ?>
                                    <a class="btn btn-outline btn-sm" href="<?= e(url('/panel/odeme/talep/' . (int) $row['id'])) ?>"><?= e(t('billing.view_intent')) ?></a>
                                <?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
