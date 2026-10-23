<?php
$statusLabel = ['active' => 'Aktif', 'sold' => 'Satıldı', 'archived' => 'Arşiv'];
$statusBadge = ['active' => 'badge--ok', 'sold' => 'badge--info', 'archived' => 'badge--warn'];
$docBadge = ['active' => 'badge--ok', 'renewed' => 'badge--info', 'cancelled' => 'badge--danger'];
?>
<div class="row row--between mb-3">
    <div>
        <div class="row" style="gap:8px">
            <h1 class="mono" style="font-size:1.7rem;margin:0"><?= e((string) $vehicle['plate']) ?></h1>
            <span class="badge <?= e($statusBadge[(string) $vehicle['status']] ?? 'badge--info') ?>"><?= e($statusLabel[(string) $vehicle['status']] ?? (string) $vehicle['status']) ?></span>
        </div>
        <p class="muted mb-0"><?= e($typeLabel) ?> · <?= e(trim((string) $vehicle['brand'] . ' ' . (string) $vehicle['model']) ?: '—') ?></p>
    </div>
    <div class="row">
        <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/araclar')) ?>">← <?= e(__('Araçlar')) ?></a>
        <?php if (!empty($vehicle['owner_id'])): ?>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/uyeler/' . (int) $vehicle['owner_id'])) ?>"><?= e(__('Üye detayı')) ?></a>
        <?php endif; ?>
        <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/evraklar?q=' . urlencode((string) $vehicle['plate']))) ?>"><?= e(__('Evraklar')) ?></a>
    </div>
</div>

<form method="post" action="<?= e(url('/yonetim/araclar/durum')) ?>" class="card mb-3">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $vehicle['id'] ?>">
    <div class="row" style="align-items:flex-end;flex-wrap:wrap">
        <div class="field mb-0" style="min-width:220px">
            <label for="vehicle-status"><?= e(__('Araç durumu')) ?></label>
            <select id="vehicle-status" name="status">
                <?php foreach ($statusLabel as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= (string) $vehicle['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit"><?= e(__('Durumu güncelle')) ?></button>
        <span class="muted" style="font-size:12.5px"><?= e(__('Satılan/arşivlenen araç müşteri panelinde pasif görünür; kayıt silinmez.')) ?></span>
    </div>
</form>

<div class="grid grid-2 mb-3">
    <div class="card">
        <h2 style="font-size:1.05rem"><?= e(__('Araç bilgileri')) ?></h2>
        <div class="grid grid-2" style="gap:8px;font-size:14px">
            <div><span class="muted"><?= e(__('Tür')) ?>:</span> <?= e($typeLabel) ?></div>
            <div><span class="muted"><?= e(__('Marka / model')) ?>:</span> <?= e(trim((string) $vehicle['brand'] . ' ' . (string) $vehicle['model']) ?: '—') ?></div>
            <div><span class="muted"><?= e(__('Model yılı')) ?>:</span> <?= e((string) ($vehicle['model_year'] ?? '—')) ?></div>
            <div><span class="muted"><?= e(__('Renk')) ?>:</span> <?= e((string) ($vehicle['color'] ?? '—')) ?></div>
            <div><span class="muted">VIN:</span> <span class="mono"><?= e((string) ($vehicle['vin'] ?? '—')) ?></span></div>
            <div><span class="muted"><?= e(__('Motor no')) ?>:</span> <span class="mono"><?= e((string) ($vehicle['engine_no'] ?? '—')) ?></span></div>
            <div><span class="muted"><?= e(__('Kilometre')) ?>:</span> <?= e(!empty($vehicle['odometer']) ? number_format((int) $vehicle['odometer'], 0, ',', '.') . ' km' : '—') ?></div>
            <div><span class="muted"><?= e(__('Sürücü')) ?>:</span> <?= e((string) ($vehicle['driver_name'] ?? '—')) ?></div>
            <div><span class="muted"><?= e(__('Kayıt tarihi')) ?>:</span> <?= e(format_date((string) $vehicle['created_at'], 'd.m.Y H:i')) ?></div>
            <div><span class="muted"><?= e(__('Son güncelleme')) ?>:</span> <?= e(!empty($vehicle['updated_at']) ? format_date((string) $vehicle['updated_at'], 'd.m.Y H:i') : '—') ?></div>
        </div>
        <?php if (!empty($vehicle['notes'])): ?>
            <p class="mt-2 mb-0" style="font-size:13.5px"><span class="muted"><?= e(__('Not')) ?>:</span> <?= e((string) $vehicle['notes']) ?></p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 style="font-size:1.05rem"><?= e(__('Sahip / kurum')) ?></h2>
        <p class="mb-1"><strong><?= e((string) ($vehicle['owner_name'] ?? '—')) ?></strong></p>
        <p class="muted mb-1" style="font-size:13.5px"><?= e((string) ($vehicle['owner_email'] ?? '')) ?><?= !empty($vehicle['owner_phone']) ? ' · ' . e((string) $vehicle['owner_phone']) : '' ?></p>
        <p class="muted mb-1" style="font-size:13.5px"><?= e((string) $vehicle['organization_name']) ?></p>
        <p class="mb-0" style="font-size:13.5px">
            <?= e(__('Plan')) ?>: <strong><?= e((string) ($vehicle['plan_name'] ?? '—')) ?></strong>
            · <?= e(__('Kredi')) ?>: <strong><?= e(number_format((float) $vehicle['credit_balance'], 2, ',', '.')) ?></strong>
        </p>
    </div>
</div>

<div class="card mb-3">
    <div class="row row--between">
        <h2 style="font-size:1.05rem;margin:0"><?= e(__('Evraklar')) ?> (<?= count($documents) ?>)</h2>
        <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/evraklar?q=' . urlencode((string) $vehicle['plate']))) ?>"><?= e(__('Listede filtrele')) ?></a>
    </div>
    <?php if ($documents === []): ?>
        <p class="muted mb-0 mt-2"><?= e(__('Bu araç için evrak kaydı yok.')) ?></p>
    <?php else: ?>
        <div class="table-wrap mt-2">
            <table class="data">
                <thead><tr><th><?= e(__('Tür')) ?></th><th><?= e(__('Kurum / poliçe')) ?></th><th><?= e(__('Bitiş')) ?></th><th><?= e(__('Kalan')) ?></th><th><?= e(__('Dosya')) ?></th><th><?= e(__('Durum')) ?></th><th></th></tr></thead>
                <tbody>
                <?php foreach ($documents as $doc): ?>
                    <tr>
                        <td><span style="display:inline-block;width:9px;height:9px;border-radius:99px;background:<?= e((string) $doc['type_color']) ?>"></span> <?= e((string) $doc['type_label']) ?></td>
                        <td><?= e((string) ($doc['issuer'] ?? '—')) ?><div class="muted mono" style="font-size:12px"><?= e((string) ($doc['policy_no'] ?? '')) ?></div></td>
                        <td><?= e(format_date((string) $doc['end_date'])) ?></td>
                        <td>
                            <?php $d = $doc['days_left']; ?>
                            <span class="badge <?= $d === null ? 'badge--info' : ($d < 0 ? 'badge--danger' : ($d < 7 ? 'badge--warn' : 'badge--ok')) ?>"><?= $d === null ? '—' : e($d < 0 ? abs($d) . ' gün geçti' : $d . ' gün') ?></span>
                        </td>
                        <td><?= count((array) $doc['files']) ?></td>
                        <td><span class="badge <?= e($docBadge[(string) $doc['status']] ?? 'badge--info') ?>"><?= e((string) $doc['status']) ?></span></td>
                        <td><a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/evraklar/' . (int) $doc['id'])) ?>"><?= e(__('Detay')) ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h2 style="font-size:1.05rem"><?= e(__('Son bildirimler')) ?></h2>
    <?php if ($notifications === []): ?>
        <p class="muted mb-0"><?= e(__('Bu araç için gönderilmiş bildirim yok.')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th><?= e(__('Kanal')) ?></th><th><?= e(__('Alıcı')) ?></th><th><?= e(__('Konu')) ?></th><th><?= e(__('Durum')) ?></th><th><?= e(__('Zaman')) ?></th><th></th></tr></thead>
                <tbody>
                <?php foreach ($notifications as $n): ?>
                    <tr>
                        <td class="mono"><?= e((string) $n['channel']) ?></td>
                        <td><?= e((string) $n['recipient']) ?></td>
                        <td><?= e((string) ($n['subject'] ?? $n['template_key'])) ?></td>
                        <td><span class="badge <?= (string) $n['status'] === 'sent' ? 'badge--ok' : ((string) $n['status'] === 'failed' ? 'badge--danger' : 'badge--info') ?>"><?= e((string) $n['status']) ?></span></td>
                        <td class="muted"><?= e(format_date((string) $n['created_at'], 'd.m.Y H:i')) ?></td>
                        <td><a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/bildirimler/' . (int) $n['id'])) ?>"><?= e(__('Detay')) ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
