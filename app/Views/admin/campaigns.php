<?php
/** Kampanya listesi (Faz 4). $rows: [{campaign, stats}], $totals, $smtpReady, $queue, $dailyLimit, $sentToday */
$statusBadge = [
    'draft'     => ['badge--info', 'Taslak'],
    'scheduled' => ['badge--warn', 'Planlandı'],
    'sending'   => ['badge--info', 'Gönderiliyor'],
    'sent'      => ['badge--ok', 'Gönderildi'],
    'cancelled' => ['badge--danger', 'İptal'],
];
$openRate = $totals['sent'] > 0 ? round($totals['opened'] / $totals['sent'] * 100, 1) : 0.0;
?>
<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.6rem;margin:0 0 4px"><?= e(t('admin.campaigns')) ?></h1>
        <p class="muted mb-0"><?= e(__('Hedef kitleyi seçin, içeriği yazın ve kampanyayı e-posta ile gönderin. Gönderim, hatırlatma kuyruğuyla aynı altyapıyı kullanır.')) ?></p>
    </div>
    <a class="btn btn-accent" href="<?= e(url('/yonetim/kampanya/yeni')) ?>">+ <?= e(__('Yeni kampanya')) ?></a>
</div>

<?php if (!$smtpReady): ?>
    <div class="alert alert--error"><?= e(__('SMTP tanımlı değil — kampanya gönderimi yapılamaz. Ayarlar → E-posta sekmesinden SMTP bilgilerini girin.')) ?></div>
<?php endif; ?>

<div class="grid grid-4 mb-3">
    <div class="card stat"><div class="stat__value"><?= (int) $totals['recipients'] ?></div><div class="stat__label"><?= e(__('Toplam alıcı')) ?></div></div>
    <div class="card stat"><div class="stat__value"><?= (int) $totals['sent'] ?></div><div class="stat__label"><?= e(__('Gönderilen e-posta')) ?></div></div>
    <div class="card stat"><div class="stat__value"><?= e((string) $openRate) ?>%</div><div class="stat__label"><?= e(__('Açılma oranı')) ?></div></div>
    <div class="card stat"><div class="stat__value"><?= (int) $totals['failed'] ?></div><div class="stat__label"><?= e(__('Hatalı gönderim')) ?></div></div>
</div>

<div class="card mb-3">
    <h2 style="font-size:1.05rem"><?= e(__('Gönderim kuyruğu')) ?></h2>
    <p class="muted mb-0" style="font-size:13.5px">
        <?= e(__('Kuyrukta bekleyen: ')) ?><strong><?= (int) $queue ?></strong>
        · <?= e(__('bugün gönderilen: ')) ?><strong><?= (int) $sentToday ?></strong> / <?= (int) $dailyLimit ?>
        · <?= e(__('Kampanyalar da aynı günlük limite tabidir.')) ?>
    </p>
    <p class="muted mb-0 mt-2" style="font-size:13.5px">
        <a href="<?= e(url('/yonetim/bildirimler')) ?>"><?= e(__('E-posta Günlüğü')) ?></a> ·
        <a href="<?= e(url('/yonetim/ayarlar/reminders')) ?>"><?= e(__('Hatırlatma ayarları')) ?></a>
    </p>
</div>

<?php if ($rows === []): ?>
    <div class="card center">
        <div class="feature-icon" style="margin-inline:auto">📣</div>
        <h2 style="font-size:1.15rem"><?= e(__('Henüz kampanya yok')) ?></h2>
        <p class="muted"><?= e(__('İlk kampanyanızı oluşturun: hedef kitleyi seçin, önizleyin, test gönderin ve yayınlayın.')) ?></p>
        <a class="btn btn-accent" href="<?= e(url('/yonetim/kampanya/yeni')) ?>">+ <?= e(__('Yeni kampanya')) ?></a>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data">
            <thead>
            <tr>
                <th>#</th>
                <th><?= e(__('Kampanya')) ?></th>
                <th><?= e(__('Durum')) ?></th>
                <th><?= e(__('Alıcı')) ?></th>
                <th><?= e(__('Gönderilen')) ?></th>
                <th><?= e(__('Açılan')) ?></th>
                <th><?= e(__('Planlanan')) ?></th>
                <th><?= e(__('İşlem')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                $campaign = $row['campaign'];
                $stats = $row['stats'];
                [$badgeClass, $badgeLabel] = $statusBadge[(string) $campaign['status']] ?? ['badge--info', (string) $campaign['status']];
                ?>
                <tr>
                    <td class="mono"><?= (int) $campaign['id'] ?></td>
                    <td>
                        <a href="<?= e(url('/yonetim/kampanya/' . (int) $campaign['id'])) ?>"><?= e((string) $campaign['name']) ?></a>
                        <div class="muted" style="font-size:12.5px"><?= e((string) $campaign['subject']) ?></div>
                    </td>
                    <td><span class="badge <?= e($badgeClass) ?>"><?= e($badgeLabel) ?></span></td>
                    <td><?= (int) $stats['total'] ?></td>
                    <td><?= (int) $stats['sent'] ?></td>
                    <td><?= (int) $stats['opened'] ?></td>
                    <td class="muted" style="font-size:13px">
                        <?= $campaign['scheduled_at'] ? e(format_date((string) $campaign['scheduled_at'], 'd.m.Y H:i')) : '—' ?>
                    </td>
                    <td>
                        <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/kampanya/' . (int) $campaign['id'])) ?>"><?= e(__('Detay')) ?></a>
                        <?php if (!in_array((string) $campaign['status'], ['sending', 'sent'], true)): ?>
                            <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/kampanya/' . (int) $campaign['id'] . '/duzenle')) ?>"><?= e(__('Düzenle')) ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
