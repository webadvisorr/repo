<?php
use App\Services\ProfileChangeService;

$pending = $pending ?? [];
$history = $history ?? [];
$statusLabels = [
    'approved'  => 'Onaylandı',
    'rejected'  => 'Reddedildi',
    'cancelled' => 'Geri çekildi',
    'pending'   => 'Onay bekliyor',
];
?>
<div class="row row--between mb-3" style="align-items:flex-start;gap:12px;flex-wrap:wrap">
    <div>
        <h1 style="font-size:1.5rem;margin:0 0 4px">👤 Profil talepleri</h1>
        <p class="muted mb-0">Müşteriler doğrudan değiştiremez; talep onaylandığında bilgiler hesaba uygulanır.</p>
    </div>
    <span class="badge <?= count($pending) > 0 ? 'badge--warn' : 'badge--ok' ?>"><?= count($pending) ?> onay bekliyor</span>
</div>

<?php if ($pending === []): ?>
    <div class="card center">
        <div class="feature-icon" style="margin-inline:auto">✅</div>
        <h2 style="font-size:1.1rem">Bekleyen talep yok</h2>
        <p class="muted mb-0">Müşteri panelinden gelen profil değişiklik talepleri burada listelenir.</p>
    </div>
<?php else: ?>
    <div class="grid" style="gap:14px">
        <?php foreach ($pending as $row): ?>
            <?php $rows = ProfileChangeService::describe($row, $row); ?>
            <div class="card">
                <div class="row row--between" style="align-items:flex-start;gap:12px;flex-wrap:wrap">
                    <div>
                        <strong><?= e((string) $row['current_name']) ?></strong>
                        <span class="muted">· <?= e((string) $row['current_email']) ?></span>
                        <div class="muted" style="font-size:12.5px"><?= e(format_date((string) $row['created_at'], 'd.m.Y H:i')) ?> · Talep #<?= (int) $row['id'] ?></div>
                    </div>
                    <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/uyeler/' . (int) $row['user_id'])) ?>">Müşteriyi aç</a>
                </div>

                <div class="table-wrap mt-2">
                    <table class="data">
                        <thead><tr><th>Alan</th><th>Mevcut</th><th>İstenen</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $change): ?>
                            <tr>
                                <td><?= e($change['label']) ?></td>
                                <td class="muted"><?= e($change['old']) ?></td>
                                <td><strong><?= e($change['new']) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="grid grid-2 mt-2" style="gap:10px">
                    <form method="post" action="<?= e(url('/yonetim/profil-talepleri/onayla')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                        <div class="field">
                            <label for="note-<?= (int) $row['id'] ?>">Onay notu <span class="muted">(opsiyonel)</span></label>
                            <input id="note-<?= (int) $row['id'] ?>" type="text" name="note" maxlength="250" placeholder="Müşteriye gösterilecek kısa not">
                        </div>
                        <button class="btn btn-accent btn-sm" type="submit">Talebi onayla</button>
                    </form>
                    <form method="post" action="<?= e(url('/yonetim/profil-talepleri/reddet')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                        <div class="field">
                            <label for="rnote-<?= (int) $row['id'] ?>">Ret gerekçesi</label>
                            <input id="rnote-<?= (int) $row['id'] ?>" type="text" name="note" maxlength="250" placeholder="Örn. bilgiler doğrulanamadı">
                        </div>
                        <button class="btn btn-danger btn-sm" type="submit">Talebi reddet</button>
                    </form>
                </div>
                <?php if (in_array('email', array_column($rows, 'field'), true)): ?>
                    <p class="hint mb-0 mt-2">E-posta değişikliğinde onay sonrası yeni adrese doğrulama kodu gönderilir; müşteri kodu girene kadar panele erişemez.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($history !== []): ?>
    <div class="card mt-3">
        <h2 style="font-size:1.05rem">🕘 Geçmiş talepler</h2>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Tarih</th><th>Müşteri</th><th>Değişiklikler</th><th>Durum</th><th>İnceleyen</th><th>Not</th></tr></thead>
                <tbody>
                <?php foreach ($history as $row): ?>
                    <?php $rows = ProfileChangeService::describe($row, $row); ?>
                    <tr>
                        <td class="mono"><?= e(format_date((string) $row['reviewed_at'], 'd.m.Y H:i')) ?></td>
                        <td><?= e((string) $row['current_name']) ?><div class="muted" style="font-size:12px"><?= e((string) $row['current_email']) ?></div></td>
                        <td style="font-size:12.5px">
                            <?php foreach ($rows as $change): ?>
                                <div><?= e($change['label']) ?>: <?= e($change['new']) ?></div>
                            <?php endforeach; ?>
                        </td>
                        <td><span class="badge <?= (string) $row['status'] === 'approved' ? 'badge--ok' : ((string) $row['status'] === 'rejected' ? 'badge--danger' : 'badge--info') ?>"><?= e($statusLabels[(string) $row['status']] ?? (string) $row['status']) ?></span></td>
                        <td class="muted"><?= e((string) ($row['reviewer'] ?? '—')) ?></td>
                        <td class="muted" style="font-size:12.5px"><?= e((string) ($row['review_note'] ?? '') ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
