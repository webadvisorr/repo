<?php
use App\Core\Auth;
$user = Auth::user();
?>
<section class="section section--tight">
    <div class="container">
        <div class="row row--between mb-3">
            <div>
                <h1 style="font-size:1.7rem;margin-bottom:4px"><?= e(t('admin.welcome', ['name' => (string) ($user['full_name'] ?? '')])) ?></h1>
                <p class="muted mb-0"><?= e('Araç panelinizden evraklarınızı yönetin.') ?></p>
            </div>
            <div class="row" style="gap:8px">
                <a class="btn btn-accent btn-sm" href="<?= e(url('/panel/odeme')) ?>"><?= e(t('billing.title')) ?></a>
                <a class="btn btn-outline btn-sm" href="<?= e(url('/panel/odemelerim')) ?>"><?= e(t('billing.history')) ?></a>
                <a class="btn btn-outline btn-sm" href="<?= e(url('/cikis')) ?>"><?= e(t('auth.logout')) ?></a>
            </div>
        </div>

        <div class="grid grid-3 mb-3">
            <div class="card stat">
                <div class="stat__value"><?= (int) ($vehicleCount ?? 0) ?></div>
                <div class="stat__label"><?= e('Kayıtlı araç') ?></div>
            </div>
            <div class="card stat">
                <div class="stat__value"><?= (int) ($documentCount ?? 0) ?></div>
                <div class="stat__label"><?= e('Aktif evrak') ?></div>
            </div>
            <div class="card stat">
                <div class="stat__value"><?= count($upcoming ?? []) ?></div>
                <div class="stat__label"><?= e('Yaklaşan bitiş') ?></div>
            </div>
        </div>


        <div class="grid grid-2 mb-3">
            <div class="card">
                <h2 style="font-size:1.05rem">💳 <?= e('Aboneliğim') ?></h2>
                <?php if (!empty($subscription)): ?>
                    <p class="mb-0">
                        <strong><?= e((string) $subscription['plan_name']) ?></strong>
                        <span class="badge badge--ok"><?= e((string) $subscription['status']) ?></span>
                    </p>
                    <p class="muted mb-0" style="font-size:13.5px">
                        <?= e('Dönem bitişi: ' . format_date((string) $subscription['current_period_end'])) ?>
                        · <?= e('Limit: ' . (int) ($limits['max_vehicles'] ?? 0) . ' araç') ?>
                    </p>
                    <?php if ((int) $subscription['cancel_at_period_end'] === 1): ?>
                        <p class="muted mb-0" style="font-size:13px;color:var(--c-warning)"><?= e('Dönem sonunda iptal edilecek.') ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="muted mb-0"><?= e('Aktif aboneliğiniz yok.') ?></p>
                <?php endif; ?>
            </div>
            <div class="card">
                <h2 style="font-size:1.05rem">💰 <?= e('Kredi bakiyem') ?></h2>
                <div class="stat__value" style="color:var(--c-accent)"><?= e(number_format((float) ($credit ?? 0), 2, ',', '.')) ?> <?= e((string) ($currency ?? 'TRY')) ?></div>
                <p class="muted mb-0" style="font-size:13.5px"><?= e('SMS paketi veya abonelik yenilemesi için kullanılabilir.') ?></p>
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
            <h2 style="font-size:1.15rem"><?= e('Yaklaşan evrak bitişleri') ?></h2>
            <?php if (empty($upcoming)): ?>
                <p class="muted mb-0"><?= e('Henüz araç veya evrak kaydınız yok. Araç modülü bir sonraki adımda devreye alınacak.') ?></p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th><?= e('Plaka') ?></th><th><?= e('Evrak') ?></th><th><?= e('Bitiş') ?></th><th><?= e('Kalan') ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($upcoming as $row): ?>
                            <?php $days = days_left((string) $row['end_date']); ?>
                            <tr>
                                <td class="mono"><?= e((string) $row['plate']) ?></td>
                                <td><?= e(label((string) $row['labels_json'])) ?></td>
                                <td><?= e(format_date((string) $row['end_date'])) ?></td>
                                <td>
                                    <span class="badge <?= $days !== null && $days < 8 ? 'badge--danger' : 'badge--ok' ?>">
                                        <?= $days === null ? '-' : e((string) $days . ' gün') ?>
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
