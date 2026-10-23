<?php
$statusLabel = ['active' => 'Aktif', 'renewed' => 'Yenilendi', 'cancelled' => 'İptal'];
$statusBadge = ['active' => 'badge--ok', 'renewed' => 'badge--info', 'cancelled' => 'badge--danger'];
$query = array_filter([
    'q' => (string) $filters['q'], 'tur' => (string) $filters['type_id'],
    'durum' => (string) $filters['status'], 'vade' => (string) $filters['vade'],
], static fn ($v) => $v !== '' && $v !== '0');
?>
<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.6rem;margin:0 0 4px">📄 <?= e(t('admin.documents')) ?></h1>
        <p class="muted mb-0"><?= e(__('Tüm kurumların evrakları; süre, dosya ve bildirim durumuyla birlikte.')) ?></p>
    </div>
    <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/araclar')) ?>"><?= e(__('Araçları gör')) ?></a>
</div>

<div class="grid grid-4 mb-3">
    <div class="card stat"><div class="stat__value"><?= (int) $totals['active'] ?></div><div class="stat__label"><?= e(__('Aktif evrak')) ?></div></div>
    <div class="card stat"><div class="stat__value" style="color:var(--c-warning)"><?= (int) $totals['expiring30'] ?></div><div class="stat__label"><?= e(__('30 günde bitecek')) ?></div></div>
    <div class="card stat"><div class="stat__value" style="color:var(--c-danger)"><?= (int) $totals['expired'] ?></div><div class="stat__label"><?= e(__('Süresi geçmiş')) ?></div></div>
    <div class="card stat"><div class="stat__value"><?= (int) $totals['files'] ?></div><div class="stat__label"><?= e(__('Yüklü dosya')) ?></div></div>
</div>

<form method="get" action="<?= e(url('/yonetim/evraklar')) ?>" class="card mb-3">
    <div class="row" style="flex-wrap:wrap">
        <input type="text" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="<?= e(__('Plaka, poliçe, kurum, evrak türü veya e-posta ara…')) ?>" style="flex:2;min-width:220px">
        <select name="tur" style="flex:1;min-width:150px">
            <option value="0"><?= e(__('Tüm evrak türleri')) ?></option>
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
        <select name="vade" style="flex:1;min-width:150px">
            <option value=""><?= e(__('Tüm süreler')) ?></option>
            <?php foreach (['30' => '30 günde bitecek', '7' => '7 günde bitecek', 'gecmis' => 'Süresi geçmiş', 'yaklasan' => 'Süresi gelmemiş'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= (string) $filters['vade'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-primary" type="submit"><?= e(t('common.filter')) ?></button>
        <?php if ($query !== []): ?>
            <a class="btn btn-outline" href="<?= e(url('/yonetim/evraklar')) ?>"><?= e(__('Temizle')) ?></a>
        <?php endif; ?>
    </div>
</form>

<div class="row row--between mb-2">
    <span class="muted" style="font-size:13px"><?= e('Toplam ' . (int) $total . ' evrak') ?></span>
</div>

<?php if ($rows === []): ?>
    <div class="card center">
        <h2 style="font-size:1.15rem"><?= e(__('Evrak bulunamadı')) ?></h2>
        <p class="muted mb-0"><?= e(__('Filtreleri değiştirin; müşteriler evrak eklediğinde burada listelenir.')) ?></p>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th><?= e(__('Plaka')) ?></th>
                    <th><?= e(__('Evrak')) ?></th>
                    <th><?= e(__('Kurum / poliçe')) ?></th>
                    <th><?= e(__('Bitiş')) ?></th>
                    <th><?= e(__('Kalan')) ?></th>
                    <th><?= e(__('Sahip')) ?></th>
                    <th><?= e(__('Dosya')) ?></th>
                    <th><?= e(__('Durum')) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php $d = $row['days_left']; ?>
                <tr>
                    <td class="mono"><a href="<?= e(url('/yonetim/araclar/' . (int) $row['vehicle_id'])) ?>"><strong><?= e((string) $row['plate']) ?></strong></a></td>
                    <td>
                        <span style="display:inline-block;width:9px;height:9px;border-radius:99px;background:<?= e((string) $row['type_color']) ?>"></span>
                        <?= e((string) $row['type_label']) ?>
                    </td>
                    <td><?= e((string) ($row['issuer'] ?? '—')) ?><div class="muted mono" style="font-size:12px"><?= e((string) ($row['policy_no'] ?? '')) ?></div></td>
                    <td><?= e(format_date((string) $row['end_date'])) ?></td>
                    <td>
                        <span class="badge <?= (string) $row['status'] !== 'active' ? 'badge--info' : ($d === null ? 'badge--info' : ($d < 0 ? 'badge--danger' : ($d < 7 ? 'badge--warn' : 'badge--ok'))) ?>">
                            <?= $d === null ? '—' : e($d < 0 ? abs($d) . ' gün geçti' : $d . ' gün') ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!empty($row['owner_id'])): ?>
                            <a href="<?= e(url('/yonetim/uyeler/' . (int) $row['owner_id'])) ?>"><?= e((string) ($row['owner_name'] ?? $row['owner_email'] ?? '—')) ?></a>
                        <?php else: ?><?= e((string) ($row['owner_name'] ?? '—')) ?><?php endif; ?>
                        <div class="muted" style="font-size:12px"><?= e((string) $row['organization_name']) ?></div>
                    </td>
                    <td><?= (int) $row['files_count'] ?></td>
                    <td><span class="badge <?= e($statusBadge[(string) $row['status']] ?? 'badge--info') ?>"><?= e($statusLabel[(string) $row['status']] ?? (string) $row['status']) ?></span></td>
                    <td><a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/evraklar/' . (int) $row['id'])) ?>"><?= e(__('Detay')) ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="row mt-3" style="justify-content:space-between">
        <?php if ($page > 1): ?>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/evraklar?' . http_build_query(array_merge($query, ['page' => $page - 1])))) ?>">← <?= e(__('Önceki')) ?></a>
        <?php else: ?><span></span><?php endif; ?>
        <?php if ($hasNext): ?>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/evraklar?' . http_build_query(array_merge($query, ['page' => $page + 1])))) ?>"><?= e(__('Sonraki')) ?> →</a>
        <?php endif; ?>
    </div>
<?php endif; ?>
