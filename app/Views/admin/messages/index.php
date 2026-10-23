<?php
$query = array_filter([
    'q' => (string) $filters['q'], 'durum' => (string) $filters['status'],
    'baslangic' => (string) ($filters['from'] ?? ''), 'bitis' => (string) ($filters['to'] ?? ''),
], static fn ($v) => $v !== '');
?>
<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.6rem;margin:0 0 4px">📬 <?= e(__('Mesajlar')) ?></h1>
        <p class="muted mb-0"><?= e(__('İletişim formundan gelen mesajlar. Açtığınız mesaj otomatik okundu işaretlenir.')) ?></p>
    </div>
    <?php if ((int) $totals['unread'] > 0 && (string) $filters['status'] !== 'okunmus'): ?>
        <form method="post" action="<?= e(url('/yonetim/mesajlar/tumu-okundu')) ?>"
              data-confirm="<?= e(__('Tüm okunmamış mesajlar okundu olarak işaretlenecek. Devam edilsin mi?')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="q" value="<?= e((string) $filters['q']) ?>">
            <input type="hidden" name="durum" value="<?= e((string) $filters['status']) ?>">
            <button class="btn btn-outline btn-sm" type="submit"><?= e($query !== [] ? 'Filtredeki tümünü okundu işaretle' : 'Tümünü okundu işaretle') ?></button>
        </form>
    <?php endif; ?>
</div>

<div class="grid grid-4 mb-3">
    <div class="card stat"><div class="stat__value"><?= (int) $totals['all'] ?></div><div class="stat__label"><?= e(__('Toplam mesaj')) ?></div></div>
    <div class="card stat"><div class="stat__value" style="color:var(--c-accent)"><?= (int) $totals['unread'] ?></div><div class="stat__label"><?= e(__('Okunmamış')) ?></div></div>
    <div class="card stat"><div class="stat__value"><?= (int) $totals['today'] ?></div><div class="stat__label"><?= e(__('Bugün')) ?></div></div>
    <div class="card stat"><div class="stat__value"><?= (int) $totals['week'] ?></div><div class="stat__label"><?= e(__('Son 7 gün')) ?></div></div>
</div>

<form method="get" action="<?= e(url('/yonetim/mesajlar')) ?>" class="card mb-3">
    <div class="row">
        <input type="text" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="<?= e(__('Ad, e-posta, telefon, konu veya mesaj içinde ara…')) ?>" style="flex:2;min-width:220px">
        <select name="durum" style="flex:1;min-width:160px">
            <option value=""><?= e(__('Tüm mesajlar')) ?></option>
            <option value="okunmamis" <?= (string) $filters['status'] === 'okunmamis' ? 'selected' : '' ?>><?= e(__('Okunmamış')) ?></option>
            <option value="okunmus" <?= (string) $filters['status'] === 'okunmus' ? 'selected' : '' ?>><?= e(__('Okunmuş')) ?></option>
        </select>
        <span style="display:inline-flex;align-items:center;gap:6px;min-height:38px">
            <?php $from = (string) ($filters['from'] ?? ''); $to = (string) ($filters['to'] ?? ''); require BASE_PATH . '/app/Views/partials/date_range.php'; ?>
        </span>
        <button class="btn btn-primary" type="submit"><?= e(t('common.search')) ?></button>
        <?php if ($query !== []): ?>
            <a class="btn btn-outline" href="<?= e(url('/yonetim/mesajlar')) ?>"><?= e(__('Temizle')) ?></a>
        <?php endif; ?>
    </div>
</form>

<div class="row row--between mb-2">
    <span class="muted" style="font-size:13px"><?= e('Toplam ' . (int) $total . ' mesaj') ?></span>
</div>

<?php if ($rows === []): ?>
    <div class="card center"><p class="muted mb-0"><?= e(__('Bu filtrelerle mesaj bulunamadı.')) ?></p></div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th></th>
                    <th><?= e(__('Gönderen')) ?></th>
                    <th><?= e(__('Konu')) ?></th>
                    <th><?= e(__('Mesaj')) ?></th>
                    <th><?= e(__('Tarih')) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php $unread = (int) $row['is_read'] === 0; ?>
                <tr style="<?= $unread ? 'background:color-mix(in srgb, var(--c-accent) 6%, transparent)' : '' ?>">
                    <td><?= $unread ? '<span class="badge badge--ok">' . e(__('Yeni')) . '</span>' : '<span class="muted">—</span>' ?></td>
                    <td>
                        <strong><?= e((string) $row['name']) ?></strong>
                        <div class="muted" style="font-size:12px"><?= e((string) $row['email']) ?><?= !empty($row['phone']) ? ' · ' . e((string) $row['phone']) : '' ?></div>
                    </td>
                    <td><?= e((string) ($row['subject'] ?? '—')) ?></td>
                    <td style="max-width:340px"><?= e(mb_strimwidth((string) $row['message'], 0, 110, '…')) ?></td>
                    <td class="muted" style="white-space:nowrap"><?= e(format_date((string) $row['created_at'], 'd.m.Y H:i')) ?></td>
                    <td>
                        <div class="row" style="gap:5px;flex-wrap:nowrap">
                            <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/mesajlar/' . (int) $row['id'])) ?>"><?= e(__('Aç')) ?></a>
                            <form method="post" action="<?= e(url('/yonetim/mesajlar/durum')) ?>" style="display:inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <input type="hidden" name="read" value="<?= $unread ? '1' : '0' ?>">
                                <button class="btn btn-outline btn-sm" type="submit"><?= $unread ? e(__('Okundu')) : e(__('Okunmadı')) ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="row mt-3" style="justify-content:space-between">
        <?php if ($page > 1): ?>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/mesajlar?' . http_build_query(array_merge($query, ['page' => $page - 1])))) ?>">← <?= e(__('Önceki')) ?></a>
        <?php else: ?><span></span><?php endif; ?>
        <?php if ($hasNext): ?>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/mesajlar?' . http_build_query(array_merge($query, ['page' => $page + 1])))) ?>"><?= e(__('Sonraki')) ?> →</a>
        <?php endif; ?>
    </div>
<?php endif; ?>
