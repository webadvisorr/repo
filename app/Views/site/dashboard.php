<?php
use App\Core\Auth;
$user = Auth::user();
?>
<section class="section section--tight">
    <div class="container">
        <div class="row row--between mb-3">
            <div>
                <h1 style="font-size:1.7rem;margin-bottom:4px"><?= e(t('admin.welcome', ['name' => (string) ($user['full_name'] ?? '')])) ?></h1>
                <p class="muted mb-0"><?= e(__('Araç panelinizden evraklarınızı yönetin.')) ?></p>
            </div>
            <div class="row" style="gap:8px">
                <a class="btn btn-danger btn-sm" href="<?= e(url('/cikis')) ?>"><?= e(t('auth.logout')) ?></a>
            </div>
        </div>

        <?php $active = 'dashboard'; require BASE_PATH . '/app/Views/partials/panel_nav.php'; ?>

        <div class="grid grid-3 mb-3">
            <a class="card stat card--hover" style="text-decoration:none;color:inherit" href="<?= e(url('/panel/araclar')) ?>">
                <div class="stat__value"><?= (int) ($vehicleCount ?? 0) ?></div>
                <div class="stat__label"><?= e(t('vehicles.title')) ?></div>
            </a>
            <div class="card stat">
                <div class="stat__value"><?= (int) ($documentCount ?? 0) ?></div>
                <div class="stat__label"><?= e(__('Aktif evrak')) ?></div>
            </div>
            <div class="card stat">
                <div class="stat__value"><?= count($upcoming ?? []) ?></div>
                <div class="stat__label"><?= e(__('Yaklaşan bitiş')) ?></div>
            </div>
        </div>


        <div class="grid grid-2 mb-3">
            <div class="card">
                <h2 style="font-size:1.05rem">💳 <?= e(__('Aboneliğim')) ?></h2>
                <?php if (!empty($subscription)): ?>
                    <p class="mb-0">
                        <strong><?= e((string) $subscription['plan_name']) ?></strong>
                        <span class="badge badge--ok"><?= e((string) $subscription['status']) ?></span>
                    </p>
                    <p class="muted mb-0" style="font-size:13.5px">
                        <?= e(__('Dönem bitişi: ') . format_date((string) $subscription['current_period_end'])) ?>
                        · <?= e(t('billing.interval') . ': ' . t('billing.interval_' . (string) ($subscription['billing_interval'] ?? 'month'))) ?>
                        · <?= e(__('Limit: :count araç', ['count' => (int) ($limits['max_vehicles'] ?? 0)])) ?>
                    </p>
                    <?php if ((int) $subscription['cancel_at_period_end'] === 1): ?>
                        <p class="muted mb-0" style="font-size:13px;color:var(--c-warning)"><?= e(__('Dönem sonunda iptal edilecek.')) ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="muted mb-0"><?= e(__('Aktif aboneliğiniz yok.')) ?></p>
                <?php endif; ?>
            </div>
            <div class="card">
                <h2 style="font-size:1.05rem">💰 <?= e(__('Kredi bakiyem')) ?></h2>
                <div class="stat__value" style="color:var(--c-accent)"><?= e(number_format((float) ($credit ?? 0), 2, ',', '.')) ?> <?= e((string) ($currency ?? 'TRY')) ?></div>
                <p class="muted mb-0" style="font-size:13.5px"><?= e(__('SMS paketi veya abonelik yenilemesi için kullanılabilir.')) ?></p>
                <?php if (!empty($ledger)): ?>
                    <div class="mono muted mt-2" style="font-size:12px;line-height:1.9">
                        <?php foreach ($ledger as $entry): ?>
                            <?= e(format_date((string) $entry['created_at'], 'd.m.Y')) ?>
                            <?= e(((string) $entry['direction'] === 'credit' ? '+' : '−') . number_format((float) $entry['amount'], 2, ',', '.')) ?>
                            <?= e('→ ' . number_format((float) $entry['balance_after'], 2, ',', '.')) ?><br>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card">
            <h2 style="font-size:1.15rem"><?= e(__('Yaklaşan evrak bitişleri')) ?></h2>
            <?php if (empty($upcoming)): ?>
                <p class="muted mb-0"><?= e(t('vehicles.empty_hint')) ?> <a href="<?= e(url('/panel/araclar/yeni')) ?>"><?= e(t('vehicles.add')) ?></a></p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th><?= e(__('Plaka')) ?></th><th><?= e(__('Evrak')) ?></th><th><?= e(__('Bitiş')) ?></th><th><?= e(__('Kalan')) ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($upcoming as $row): ?>
                            <?php $days = days_left((string) $row['end_date']); ?>
                            <tr>
                                <td class="mono"><?= e((string) $row['plate']) ?></td>
                                <td><?= e(label((string) $row['labels_json'])) ?></td>
                                <td><?= e(format_date((string) $row['end_date'])) ?></td>
                                <td>
                                    <span class="badge <?= $days !== null && $days < 8 ? 'badge--danger' : 'badge--ok' ?>">
                                        <?= $days === null ? '-' : e(__(':count gün', ['count' => (string) $days])) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
