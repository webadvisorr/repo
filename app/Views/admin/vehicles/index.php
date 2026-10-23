<?php
$lightBadge = [
    'green'  => 'badge--ok',
    'yellow' => 'badge--warn',
    'orange' => 'badge--warn',
    'red'    => 'badge--danger',
    'none'   => 'badge--info',
];
$lightLabel = [
    'green'  => 'Güncel',
    'yellow' => '30 günden az',
    'orange' => '7 günden az',
    'red'    => 'Süresi geçti',
    'none'   => 'Evrak yok',
];
$statusLabel = ['active' => 'Aktif', 'sold' => 'Satıldı', 'archived' => 'Arşiv'];
$statusBadge = ['active' => 'badge--ok', 'sold' => 'badge--info', 'archived' => 'badge--warn'];
$query = array_filter([
    'q' => (string) $filters['q'], 'tur' => (string) $filters['type_id'],
    'durum' => (string) $filters['status'], 'vade' => (string) $filters['vade'],
], static fn ($v) => $v !== '' && $v !== '0');
?>
<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.6rem;margin:0 0 4px">🚗 <?= e(t('admin.vehicles')) ?></h1>
        <p class="muted mb-0"><?= e(__('Tüm kurumların araçları, sahipleri ve evrak süreleri.')) ?></p>
    </div>
    <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/evraklar')) ?>"><?= e(__('Evrakları gör')) ?></a>
</div>

<div class="grid grid-4 mb-3">
    <div class="card stat"><div class="stat__value"><?= (int) $totals['vehicles'] ?></div><div class="stat__label"><?= e(__('Toplam araç')) ?></div></div>
    <div class="card stat"><div class="stat__value"><?= (int) $totals['active'] ?></div><div class="stat__label"><?= e(__('Aktif araç')) ?></div></div>
    <div class="card stat"><div class="stat__value"><?= (int) $totals['active_docs'] ?></div><div class="stat__label"><?= e(__('Aktif evrak')) ?></div></div>
    <div class="card stat"><div class="stat__value" style="color:var(--c-warning)"><?= (int) $totals['expiring30'] ?></div><div class="stat__label"><?= e(__('30 günde bitecek')) ?></div></div>
</div>

<form method="get" action="<?= e(url('/yonetim/araclar')) ?>" class="card mb-3">
    <div class="row" style="flex-wrap:wrap">
        <input type="text" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="<?= e(__('Plaka, marka, model, sürücü, kurum veya e-posta ara…')) ?>" style="flex:2;min-width:220px">
        <select name="tur" style="flex:1;min-width:140px">
            <option value="0"><?= e(__('Tüm araç türleri')) ?></option>
            <?php foreach ($types as $type): ?>
                <option value="<?= (int) $type['id'] ?>" <?= (int) $filters['type_id'] === (int) $type['id'] ? 'selected' : '' ?>><?= e(label((string) $type['labels_json'])) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="durum" style="flex:1;min-width:120px">
            <option value=""><?= e(__('Tüm durumlar')) ?></option>
            <?php foreach ($statusLabel as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= (string) $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="vade" style="flex:1;min-width:140px">
            <option value=""><?= e(__('Tüm evrak süreleri')) ?></option>
            <?php foreach (['30' => '30 günde bitecek', '7' => '7 günde bitecek', 'gecmis' => 'Süresi geçmiş', 'evrak-yok' => 'Evrakı olmayan'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= (string) $filters['vade'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-primary" type="submit"><?= e(t('common.filter')) ?></button>
        <?php if ($query !== []): ?>
            <a class="btn btn-outline" href="<?= e(url('/yonetim/araclar')) ?>"><?= e(__('Temizle')) ?></a>
        <?php endif; ?>
    </div>
</form>

<div class="row row--between mb-2">
    <span class="muted" style="font-size:13px"><?= e('Toplam ' . (int) $total . ' araç') ?></span>
</div>

<?php if ($rows === []): ?>
    <div class="card center">
        <h2 style="font-size:1.15rem"><?= e(__('Araç bulunamadı')) ?></h2>
        <p class="muted mb-0"><?= e(__('Filtreleri değiştirin veya müşteriler araç eklediğinde burada görünür.')) ?></p>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th><?= e(__('Plaka')) ?></th>
                    <th><?= e(__('Araç')) ?></th>
                    <th><?= e(__('Sahip / kurum')) ?></th>
                    <th><?= e(__('En yakın bitiş')) ?></th>
                    <th><?= e(__('Evrak')) ?></th>
                    <th><?= e(__('Durum')) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="mono"><a href="<?= e(url('/yonetim/araclar/' . (int) $row['id'])) ?>"><strong><?= e((string) $row['plate']) ?></strong></a></td>
                    <td>
                        <?= e(trim((string) $row['brand'] . ' ' . (string) $row['model'])) ?: '—' ?>
                        <div class="muted" style="font-size:12px"><?= e((string) $row['type_label']) ?></div>
                    </td>
                    <td>
                        <?php if (!empty($row['owner_id'])): ?>
                            <a href="<?= e(url('/yonetim/uyeler/' . (int) $row['owner_id'])) ?>"><?= e((string) ($row['owner_name'] ?? $row['owner_email'] ?? '—')) ?></a>
                        <?php else: ?>
                            <?= e((string) ($row['owner_name'] ?? '—')) ?>
                        <?php endif; ?>
                        <div class="muted" style="font-size:12px"><?= e((string) $row['organization_name']) ?></div>
                    </td>
                    <td>
                        <?php if (!empty($row['next_end'])): ?>
                            <?= e(format_date((string) $row['next_end'])) ?>
                            <div><span class="badge <?= e($lightBadge[$row['light']] ?? 'badge--info') ?>"><?= e($lightLabel[$row['light']] ?? '') ?></span></div>
                        <?php else: ?>
                            <span class="badge badge--info"><?= e(__('Evrak yok')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int) $row['active_docs'] ?></td>
                    <td><span class="badge <?= e($statusBadge[(string) $row['status']] ?? 'badge--info') ?>"><?= e($statusLabel[(string) $row['status']] ?? (string) $row['status']) ?></span></td>
                    <td><a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/araclar/' . (int) $row['id'])) ?>"><?= e(__('Detay')) ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="row mt-3" style="justify-content:space-between">
        <?php if ($page > 1): ?>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/araclar?' . http_build_query(array_merge($query, ['page' => $page - 1])))) ?>">← <?= e(__('Önceki')) ?></a>
        <?php else: ?><span></span><?php endif; ?>
        <?php if ($hasNext): ?>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/araclar?' . http_build_query(array_merge($query, ['page' => $page + 1])))) ?>"><?= e(__('Sonraki')) ?> →</a>
        <?php endif; ?>
    </div>
<?php endif; ?>
