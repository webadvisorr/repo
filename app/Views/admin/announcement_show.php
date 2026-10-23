<?php
/**
 * Duyuru detayı: içerik önizlemesi, okunma istatistikleri ve alıcı listesi.
 * $broadcast, $stats, $audience, $recipients
 */
use App\Services\InboxService;

$audienceLabel = $audience['user_id'] > 0
    ? 'Tek kullanıcı (# ' . (int) $audience['user_id'] . ')'
    : ($audience['org_id'] > 0
        ? 'Kurum #' . (int) $audience['org_id']
        : ($audience['plan_key'] !== '' ? 'Paket: ' . $audience['plan_key'] : 'Tüm kullanıcılar'));
$rate = $stats['recipients'] > 0 ? round($stats['read'] / $stats['recipients'] * 100, 1) : 0.0;
$link = (string) ($broadcast['link_url'] ?? '');
?>
<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.5rem;margin:0 0 4px">📢 <?= e((string) $broadcast['title']) ?></h1>
        <p class="muted mb-0">
            <?= e('Gönderim: ' . format_date((string) ($broadcast['sent_at'] ?? $broadcast['created_at']), 'd.m.Y H:i')) ?>
            · <?= e(__('Hedef: ')) ?><?= e($audienceLabel) ?>
            <?php if (!empty($broadcast['author'])): ?> · <?= e((string) $broadcast['author']) ?><?php endif; ?>
        </p>
    </div>
    <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/duyurular')) ?>">← <?= e(__('Bildirim')) ?></a>
</div>

<div class="grid grid-4 mb-3">
    <div class="card stat"><div class="stat__value"><?= (int) $stats['recipients'] ?></div><div class="stat__label"><?= e(__('Ulaşan kullanıcı')) ?></div></div>
    <div class="card stat"><div class="stat__value"><?= (int) $stats['read'] ?></div><div class="stat__label"><?= e(__('Okuyan')) ?></div></div>
    <div class="card stat"><div class="stat__value"><?= (int) $stats['unread'] ?></div><div class="stat__label"><?= e(__('Okunmamış')) ?></div></div>
    <div class="card stat"><div class="stat__value"><?= e((string) $rate) ?>%</div><div class="stat__label"><?= e(__('Okunma oranı')) ?></div></div>
</div>

<div class="grid grid-2 mb-3" style="gap:16px;align-items:start">
    <div class="card">
        <h2 style="font-size:1.05rem"><?= e(__('İçerik')) ?></h2>
        <?php if (!empty($broadcast['image_path'])): ?>
            <img src="<?= e((string) InboxService::imageUrl((string) $broadcast['image_path'])) ?>" alt=""
                 style="width:100%;max-height:220px;object-fit:cover;border-radius:12px;margin-bottom:12px">
        <?php endif; ?>
        <p style="white-space:pre-wrap;font-size:14px;line-height:1.7"><?= e((string) ($broadcast['body'] ?? '')) ?></p>
        <?php if ($link !== ''): ?>
            <p class="mb-0">
                <span class="btn btn-accent btn-sm" style="pointer-events:none"><?= e((string) ($broadcast['link_label'] ?: 'Detayı gör')) ?></span>
                <span class="muted mono" style="font-size:12px;margin-inline-start:8px;word-break:break-all"><?= e($link) ?></span>
            </p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 style="font-size:1.05rem"><?= e(__('Alıcılar')) ?></h2>
        <?php if ($recipients === []): ?>
            <p class="muted mb-0"><?= e(__('Kayıt bulunamadı.')) ?></p>
        <?php else: ?>
            <div class="table-wrap" style="max-height:340px;overflow:auto">
                <table class="data">
                    <thead><tr><th><?= e(__('Kullanıcı')) ?></th><th><?= e(__('Durum')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($recipients as $recipient): ?>
                        <tr>
                            <td>
                                <?= e((string) $recipient['full_name']) ?>
                                <div class="muted" style="font-size:12px"><?= e((string) $recipient['email']) ?></div>
                            </td>
                            <td>
                                <?php if ($recipient['read_at'] === null): ?>
                                    <span class="badge badge--warn"><?= e(__('Okunmadı')) ?></span>
                                <?php else: ?>
                                    <span class="badge badge--ok"><?= e(__('Okundu')) ?></span>
                                    <div class="muted" style="font-size:11.5px"><?= e(format_date((string) $recipient['read_at'], 'd.m.Y H:i')) ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<form method="post" action="<?= e(url('/yonetim/duyurular/sil')) ?>"
      data-confirm="<?= e(__('Bildirim ve tüm kullanıcı bildirimleri silinecek. Emin misiniz?')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $broadcast['id'] ?>">
    <button class="btn btn-danger btn-sm" type="submit"><?= e(__('Bildirimi sil')) ?></button>
</form>
