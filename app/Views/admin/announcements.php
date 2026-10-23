<?php
/**
 * Site içi duyuru listesi.
 * $rows: [{broadcast, stats}], $totals, $audienceTotal, $available
 */
$readRate = $totals['recipients'] > 0 ? round($totals['read'] / $totals['recipients'] * 100, 1) : 0.0;
?>
<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.6rem;margin:0 0 4px">📢 <?= e(__('Bildirim')) ?></h1>
        <p class="muted mb-0"><?= e(__('Müşteri panelindeki zile resim, metin ve bağlantı içeren özel bildirim gönderin. Hatırlatmalar da otomatik olarak buraya düşer.')) ?></p>
    </div>
    <a class="btn btn-accent" href="<?= e(url('/yonetim/duyurular/yeni')) ?>">+ <?= e(__('Yeni bildirim')) ?></a>
</div>

<?php if (!$available): ?>
    <div class="alert alert--error">
        <?= e(__('Bildirim tabloları henüz oluşturulmadı. Sistem → Güncellemeleri uygula adımını çalıştırın.')) ?>
    </div>
<?php endif; ?>

<div class="grid grid-4 mb-3">
    <div class="card stat"><div class="stat__value"><?= (int) $totals['broadcasts'] ?></div><div class="stat__label"><?= e(__('Gönderilen bildirim')) ?></div></div>
    <div class="card stat"><div class="stat__value"><?= (int) $totals['recipients'] ?></div><div class="stat__label"><?= e(__('Ulaşan bildirim')) ?></div></div>
    <div class="card stat"><div class="stat__value"><?= e((string) $readRate) ?>%</div><div class="stat__label"><?= e(__('Okunma oranı')) ?></div></div>
    <div class="card stat"><div class="stat__value"><?= (int) $audienceTotal ?></div><div class="stat__label"><?= e(__('Bildirim alabilecek kullanıcı')) ?></div></div>
</div>

<?php if (!empty($pushEnabled)): ?>
    <div class="card mb-3">
        <div class="row row--between" style="align-items:flex-start;gap:12px;flex-wrap:wrap">
            <div style="flex:1;min-width:240px">
                <h2 style="font-size:1.05rem">📱 <?= e(__('Cihaz bildirimleri (Web Push)')) ?></h2>
                <p class="muted mb-0" style="font-size:13.5px">
                    <?= e(__('Müşteri panelinde bildirimlerini açan cihazlara duyurular anında telefon/bilgisayar bildirimi olarak da gider.')) ?>
                </p>
                <p class="muted mb-0 mt-2" style="font-size:13px">
                    <?= e('Bağlı cihaz: ' . (int) $pushDevices . ' · Gönderim bekleyen: ' . (int) $pushPending) ?>
                </p>
            </div>
            <form method="post" action="<?= e(url('/yonetim/duyurular/push-gonder')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-outline btn-sm" type="submit" <?= ((int) $pushPending) > 0 ? '' : 'disabled' ?>>
                    <?= e(__('Bekleyen cihaz bildirimlerini gönder')) ?>
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($rows === []): ?>
    <div class="card center">
        <div class="feature-icon" style="margin-inline:auto">📢</div>
        <h2 style="font-size:1.15rem"><?= e(__('Henüz bildirim gönderilmedi')) ?></h2>
        <p class="muted"><?= e(__('İlk bildiriminizi hazırlayın: başlık, metin, isteğe bağlı görsel ve “Detayı gör” bağlantısı ekleyin.')) ?></p>
        <a class="btn btn-accent" href="<?= e(url('/yonetim/duyurular/yeni')) ?>">+ <?= e(__('Yeni bildirim')) ?></a>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data">
            <thead>
            <tr>
                <th>#</th>
                <th><?= e(__('Bildirim')) ?></th>
                <th><?= e(__('Hedef kitle')) ?></th>
                <th><?= e(__('Ulaşan')) ?></th>
                <th><?= e(__('Okunma')) ?></th>
                <th><?= e(__('Gönderim')) ?></th>
                <th><?= e(__('İşlem')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                $broadcast = $row['broadcast'];
                $stats = $row['stats'];
                $audience = App\Services\InboxService::audienceOf($broadcast);
                $audienceLabel = $audience['user_id'] > 0
                    ? 'Tek kullanıcı'
                    : ($audience['org_id'] > 0
                        ? 'Kurum #' . (int) $audience['org_id']
                        : ($audience['plan_key'] !== '' ? 'Paket: ' . $audience['plan_key'] : 'Tüm kullanıcılar'));
                $rate = $stats['recipients'] > 0 ? round($stats['read'] / $stats['recipients'] * 100) : 0;
                ?>
                <tr>
                    <td class="mono"><?= (int) $broadcast['id'] ?></td>
                    <td>
                        <div class="row" style="gap:9px;align-items:center;flex-wrap:nowrap">
                            <?php if (!empty($broadcast['image_path'])): ?>
                                <img src="<?= e((string) App\Services\InboxService::imageUrl((string) $broadcast['image_path'])) ?>"
                                     alt="" style="width:38px;height:38px;border-radius:9px;object-fit:cover;flex:none">
                            <?php else: ?>
                                <span style="width:38px;height:38px;border-radius:9px;display:grid;place-items:center;background:color-mix(in srgb,var(--c-primary) 7%,transparent);flex:none">📣</span>
                            <?php endif; ?>
                            <div style="min-width:0">
                                <a href="<?= e(url('/yonetim/duyurular/' . (int) $broadcast['id'])) ?>"><?= e((string) $broadcast['title']) ?></a>
                                <div class="muted" style="font-size:12.5px"><?= e(mb_strimwidth((string) ($broadcast['body'] ?? ''), 0, 70, '…')) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge badge--info"><?= e($audienceLabel) ?></span></td>
                    <td><?= (int) $stats['recipients'] ?></td>
                    <td><?= (int) $stats['read'] ?> <span class="muted">(<?= (int) $rate ?>%)</span></td>
                    <td><?= e(format_date((string) ($broadcast['sent_at'] ?? $broadcast['created_at']), 'd.m.Y H:i')) ?></td>
                    <td>
                        <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/duyurular/' . (int) $broadcast['id'])) ?>"><?= e(__('Detay')) ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
