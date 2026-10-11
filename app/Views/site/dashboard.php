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
            <a class="btn btn-outline btn-sm" href="<?= e(url('/cikis')) ?>"><?= e(t('auth.logout')) ?></a>
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
