<?php
$query = array_filter([
    'q' => (string) $filters['q'], 'islem' => (string) $filters['action'],
    'varlik' => (string) $filters['entity'], 'kullanici' => (string) $filters['user_id'],
    'gun' => (string) $filters['days'], 'baslangic' => (string) ($filters['from'] ?? ''),
    'bitis' => (string) ($filters['to'] ?? ''),
], static fn ($v) => $v !== '' && $v !== '0');
$pretty = static function (?string $json): string {
    if ($json === null || trim($json) === '') {
        return '';
    }
    $decoded = json_decode($json, true);
    if ($decoded === null) {
        return $json;
    }
    return (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};
?>
<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.6rem;margin:0 0 4px">🛡️ <?= e(t('admin.audit')) ?></h1>
        <p class="muted mb-0"><?= e(__('Yönetici ve sistem işlemlerinin değiştirilemez kaydı.')) ?></p>
    </div>
</div>

<div class="grid grid-4 mb-3">
    <div class="card stat"><div class="stat__value"><?= (int) $totals['all'] ?></div><div class="stat__label"><?= e(__('Toplam kayıt')) ?></div></div>
    <div class="card stat"><div class="stat__value"><?= (int) $totals['today'] ?></div><div class="stat__label"><?= e(__('Bugün')) ?></div></div>
    <div class="card stat"><div class="stat__value"><?= (int) $totals['hour'] ?></div><div class="stat__label"><?= e(__('Son 1 saat')) ?></div></div>
    <div class="card stat"><div class="stat__value"><?= (int) $totals['actors'] ?></div><div class="stat__label"><?= e(__('İşlem yapan kullanıcı')) ?></div></div>
</div>

<form method="get" action="<?= e(url('/yonetim/denetim')) ?>" class="card mb-3">
    <div class="row" style="flex-wrap:wrap">
        <input type="text" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="<?= e(__('İşlem, varlık, kayıt no, IP veya meta ara…')) ?>" style="flex:2;min-width:200px">
        <select name="islem" style="flex:1;min-width:160px">
            <option value=""><?= e(__('Tüm işlemler')) ?></option>
            <?php foreach ($actions as $action): ?>
                <option value="<?= e((string) $action) ?>" <?= (string) $filters['action'] === (string) $action ? 'selected' : '' ?>><?= e((string) $action) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="varlik" style="flex:1;min-width:140px">
            <option value=""><?= e(__('Tüm varlıklar')) ?></option>
            <?php foreach ($entities as $entity): ?>
                <option value="<?= e((string) $entity) ?>" <?= (string) $filters['entity'] === (string) $entity ? 'selected' : '' ?>><?= e((string) $entity) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="kullanici" style="flex:1;min-width:150px">
            <option value="0"><?= e(__('Tüm kullanıcılar')) ?></option>
            <?php foreach ($users as $u): ?>
                <option value="<?= (int) $u['id'] ?>" <?= (int) $filters['user_id'] === (int) $u['id'] ? 'selected' : '' ?>><?= e((string) ($u['full_name'] ?: $u['email'])) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="gun" style="flex:1;min-width:110px">
            <option value="0"><?= e(__('Tüm zamanlar')) ?></option>
            <?php foreach ([1 => 'Son 24 saat', 7 => 'Son 7 gün', 30 => 'Son 30 gün', 90 => 'Son 90 gün'] as $value => $label): ?>
                <option value="<?= $value ?>" <?= (int) $filters['days'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <span style="display:inline-flex;align-items:center;gap:6px;min-height:38px">
            <?php $from = (string) ($filters['from'] ?? ''); $to = (string) ($filters['to'] ?? ''); require BASE_PATH . '/app/Views/partials/date_range.php'; ?>
        </span>
        <button class="btn btn-primary" type="submit"><?= e(t('common.filter')) ?></button>
        <?php if ($query !== []): ?>
            <a class="btn btn-outline" href="<?= e(url('/yonetim/denetim')) ?>"><?= e(__('Temizle')) ?></a>
        <?php endif; ?>
    </div>
</form>

<div class="row row--between mb-2">
    <span class="muted" style="font-size:13px"><?= e('Toplam ' . (int) $total . ' kayıt') ?></span>
</div>

<?php if ($rows === []): ?>
    <div class="card center"><p class="muted mb-0"><?= e(__('Bu filtrelerle denetim kaydı bulunamadı.')) ?></p></div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?= e(__('Zaman')) ?></th>
                    <th><?= e(__('Kullanıcı')) ?></th>
                    <th><?= e(__('İşlem')) ?></th>
                    <th><?= e(__('Varlık')) ?></th>
                    <th>IP</th>
                    <th><?= e(__('Detay')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php $meta = $pretty($row['meta_json'] ?? null); ?>
                <tr>
                    <td class="mono"><?= (int) $row['id'] ?></td>
                    <td class="muted" style="white-space:nowrap"><?= e(format_date((string) $row['created_at'], 'd.m.Y H:i:s')) ?></td>
                    <td>
                        <?php if (!empty($row['user_id'])): ?>
                            <a href="<?= e(url('/yonetim/uyeler/' . (int) $row['user_id'])) ?>"><?= e((string) ($row['full_name'] ?: $row['email'] ?: '#' . $row['user_id'])) ?></a>
                        <?php else: ?>
                            <span class="muted"><?= e(__('Sistem')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="mono" style="font-size:12.5px"><?= e((string) $row['action']) ?></td>
                    <td><?= e((string) ($row['entity'] ?? '—')) ?><?= !empty($row['entity_id']) ? ' #' . e((string) $row['entity_id']) : '' ?></td>
                    <td class="mono muted" style="font-size:12px"><?= e((string) ($row['ip'] ?? '—')) ?></td>
                    <td>
                        <?php if ($meta !== ''): ?>
                            <details>
                                <summary style="cursor:pointer;font-size:12.5px"><?= e(__('Göster')) ?></summary>
                                <pre style="max-width:420px;white-space:pre-wrap;font-size:11.5px;margin:6px 0 0"><?= e($meta) ?></pre>
                            </details>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="row mt-3" style="justify-content:space-between">
        <?php if ($page > 1): ?>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/denetim?' . http_build_query(array_merge($query, ['page' => $page - 1])))) ?>">← <?= e(__('Önceki')) ?></a>
        <?php else: ?><span></span><?php endif; ?>
        <?php if ($hasNext): ?>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/denetim?' . http_build_query(array_merge($query, ['page' => $page + 1])))) ?>"><?= e(__('Sonraki')) ?> →</a>
        <?php endif; ?>
    </div>
<?php endif; ?>
