<?php
/**
 * Yönetim — destek talepleri listesi.
 * $rows, $total, $page, $pages, $filters, $stats
 */
use App\Services\SupportService;

$statusBadge = ['open' => 'badge--warn', 'answered' => 'badge--ok', 'closed' => 'badge--info'];
$queryBase = array_filter([
    'q'         => (string) $filters['q'],
    'durum'     => (string) $filters['status'],
    'baslangic' => (string) ($filters['from'] ?? ''),
    'bitis'     => (string) ($filters['to'] ?? ''),
], static fn ($v) => $v !== '');
?>
<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.6rem;margin:0 0 4px">🎧 <?= e(__('Destek talepleri')) ?></h1>
        <p class="muted mb-0"><?= e(__('Müşteri panelinden açılan talepleri yanıtlayın, durumlarını yönetin.')) ?></p>
    </div>
</div>

<div class="grid grid-4 mb-3">
    <div class="card stat stat--tone-4"><div class="stat__value"><?= (int) ($stats['open'] ?? 0) ?></div><div class="stat__label"><?= e(__('Açık')) ?></div></div>
    <div class="card stat stat--tone-3"><div class="stat__value"><?= (int) ($stats['answered'] ?? 0) ?></div><div class="stat__label"><?= e(__('Yanıtlandı')) ?></div></div>
    <div class="card stat stat--tone-1"><div class="stat__value"><?= (int) ($stats['closed'] ?? 0) ?></div><div class="stat__label"><?= e(__('Kapalı')) ?></div></div>
    <div class="card stat stat--tone-5"><div class="stat__value"><?= (int) ($stats['unread'] ?? 0) ?></div><div class="stat__label"><?= e(__('Yeni müşteri mesajı')) ?></div></div>
</div>

<form method="get" action="<?= e(url('/yonetim/destek')) ?>" class="card mb-3">
    <div class="row" style="flex-wrap:wrap">
        <input type="text" name="q" value="<?= e((string) $filters['q']) ?>"
               placeholder="<?= e(__('Talep no, konu, müşteri adı/e-postası veya mesaj içinde ara…')) ?>" style="flex:2;min-width:220px">
        <select name="durum" style="flex:1;min-width:150px">
            <option value=""><?= e(__('Tüm durumlar')) ?></option>
            <?php foreach (SupportService::STATUSES as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= (string) $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <span style="display:inline-flex;align-items:center;gap:6px;min-height:38px">
            <?php $from = (string) ($filters['from'] ?? ''); $to = (string) ($filters['to'] ?? ''); require BASE_PATH . '/app/Views/partials/date_range.php'; ?>
        </span>
        <button class="btn btn-primary" type="submit"><?= e(t('common.filter')) ?></button>
        <?php if ($queryBase !== []): ?>
            <a class="btn btn-outline" href="<?= e(url('/yonetim/destek')) ?>"><?= e(__('Temizle')) ?></a>
        <?php endif; ?>
    </div>
</form>

<div class="row row--between mb-2">
    <span class="muted" style="font-size:13px"><?= e('Toplam ' . (int) $total . ' talep') ?></span>
</div>

<?php if (($rows ?? []) === []): ?>
    <div class="card center"><p class="muted mb-0"><?= e(__('Bu filtrelerle destek talebi bulunamadı.')) ?></p></div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data">
            <thead>
            <tr>
                <th><?= e(__('Talep')) ?></th>
                <th><?= e(__('Müşteri')) ?></th>
                <th><?= e(__('Konu')) ?></th>
                <th><?= e(__('Kategori')) ?></th>
                <th><?= e(__('Durum')) ?></th>
                <th><?= e(__('Mesaj')) ?></th>
                <th><?= e(__('Son hareket')) ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php $unread = (int) ($row['unread_count'] ?? 0); ?>
                <tr>
                    <td class="mono" style="font-size:12.5px"><?= e((string) $row['ticket_no']) ?></td>
                    <td>
                        <a href="<?= e(url('/yonetim/uyeler/' . (int) $row['user_id'])) ?>"><?= e((string) $row['full_name']) ?></a>
                        <div class="muted" style="font-size:12px"><?= e((string) $row['email']) ?></div>
                    </td>
                    <td>
                        <a href="<?= e(url('/yonetim/destek/' . (int) $row['id'])) ?>"><?= e((string) $row['subject']) ?></a>
                        <?php if ($unread > 0): ?><span class="badge badge--danger" style="margin-inline-start:4px"><?= (int) $unread ?> <?= e('yeni') ?></span><?php endif; ?>
                    </td>
                    <td><span class="badge badge--info"><?= e(SupportService::categoryLabel((string) $row['category'])) ?></span></td>
                    <td><span class="badge <?= e($statusBadge[(string) $row['status']] ?? 'badge--info') ?>"><?= e(SupportService::statusLabel((string) $row['status'])) ?></span></td>
                    <td><?= (int) $row['message_count'] ?></td>
                    <td class="muted" style="font-size:12.5px"><?= e(time_ago((string) ($row['last_reply_at'] ?: $row['created_at']))) ?></td>
                    <td><a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/destek/' . (int) $row['id'])) ?>"><?= e(__('Aç')) ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ((int) $pages > 1): ?>
        <nav class="row mt-3" style="gap:8px;justify-content:center" aria-label="<?= e(__('Sayfalar')) ?>">
            <?php if ((int) $page > 1): ?>
                <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/destek?' . http_build_query($queryBase + ['page' => (int) $page - 1]))) ?>">← <?= e(__('Önceki')) ?></a>
            <?php endif; ?>
            <span class="muted" style="align-self:center;font-size:13px"><?= (int) $page ?> / <?= (int) $pages ?></span>
            <?php if ((int) $page < (int) $pages): ?>
                <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/destek?' . http_build_query($queryBase + ['page' => (int) $page + 1]))) ?>"><?= e(__('Sonraki')) ?> →</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
