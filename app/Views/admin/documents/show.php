<?php
$statusLabel = ['active' => 'Aktif', 'renewed' => 'Yenilendi', 'cancelled' => 'İptal'];
$statusBadge = ['active' => 'badge--ok', 'renewed' => 'badge--info', 'cancelled' => 'badge--danger'];
$d = $daysLeft;
?>
<div class="row row--between mb-3">
    <div>
        <div class="row" style="gap:8px">
            <h1 style="font-size:1.6rem;margin:0"><?= e($typeLabel) ?></h1>
            <span class="badge <?= e($statusBadge[(string) $document['status']] ?? 'badge--info') ?>"><?= e($statusLabel[(string) $document['status']] ?? (string) $document['status']) ?></span>
        </div>
        <p class="muted mb-0">
            <a class="mono" href="<?= e(url('/yonetim/araclar/' . (int) $document['vehicle_id'])) ?>"><strong><?= e((string) $document['plate']) ?></strong></a>
            · <?= e(trim((string) $document['brand'] . ' ' . (string) $document['model']) ?: '—') ?>
            · <?= e((string) $document['organization_name']) ?>
        </p>
    </div>
    <div class="row">
        <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/evraklar')) ?>">← <?= e(__('Evraklar')) ?></a>
        <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/araclar/' . (int) $document['vehicle_id'])) ?>"><?= e(__('Araç detayı')) ?></a>
        <?php if (!empty($document['owner_id'])): ?>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/uyeler/' . (int) $document['owner_id'])) ?>"><?= e(__('Üye detayı')) ?></a>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-2 mb-3">
    <div class="card">
        <h2 style="font-size:1.05rem"><?= e(__('Evrak bilgileri')) ?></h2>
        <div class="grid grid-2" style="gap:8px;font-size:14px">
            <div><span class="muted"><?= e(__('Kurum')) ?>:</span> <?= e((string) ($document['issuer'] ?? '—')) ?></div>
            <div><span class="muted"><?= e(__('Poliçe / evrak no')) ?>:</span> <span class="mono"><?= e((string) ($document['policy_no'] ?? '—')) ?></span></div>
            <div><span class="muted"><?= e(__('Başlangıç')) ?>:</span> <?= e(!empty($document['start_date']) ? format_date((string) $document['start_date']) : '—') ?></div>
            <div><span class="muted"><?= e(__('Bitiş')) ?>:</span> <strong><?= e(format_date((string) $document['end_date'])) ?></strong></div>
            <div>
                <span class="muted"><?= e(__('Kalan')) ?>:</span>
                <span class="badge <?= (string) $document['status'] !== 'active' ? 'badge--info' : ($d === null ? 'badge--info' : ($d < 0 ? 'badge--danger' : ($d < 7 ? 'badge--warn' : 'badge--ok'))) ?>">
                    <?= $d === null ? '—' : e($d < 0 ? abs($d) . ' gün geçti' : $d . ' gün') ?>
                </span>
            </div>
            <div><span class="muted"><?= e(__('Ücret')) ?>:</span> <?= $document['cost'] !== null ? e(number_format((float) $document['cost'], 2, ',', '.') . ' ' . (string) $document['currency']) : '—' ?></div>
            <div><span class="muted"><?= e(__('Hatırlatma günleri')) ?>:</span> <?= e((string) ($document['reminder_offsets'] ?? 'Varsayılan')) ?></div>
            <div><span class="muted"><?= e(__('Kayıt')) ?>:</span> <?= e(format_date((string) $document['created_at'], 'd.m.Y H:i')) ?></div>
        </div>
        <?php if (!empty($document['notes'])): ?>
            <p class="mt-2 mb-0" style="font-size:13.5px"><span class="muted"><?= e(__('Not')) ?>:</span> <?= e((string) $document['notes']) ?></p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 style="font-size:1.05rem"><?= e(__('Dosyalar')) ?> (<?= count($files) ?>)</h2>
        <?php if ($files === []): ?>
            <p class="muted mb-0"><?= e(__('Bu evraka dosya yüklenmemiş.')) ?><?= !empty($document['requires_file']) ? ' ' . e(__('Bu evrak türünde dosya bekleniyor.')) : '' ?></p>
        <?php else: ?>
            <ul style="margin:0;padding-inline-start:18px;font-size:13.5px;line-height:1.9">
                <?php foreach ($files as $file): ?>
                    <li>
                        <a href="<?= e(media_url((string) $file['disk_path'])) ?>" target="_blank" rel="noopener"><?= e((string) ($file['label'] ?: $file['original_name'])) ?></a>
                        <span class="muted">· <?= e(format_bytes((int) $file['size_bytes'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h2 style="font-size:1.05rem"><?= e(__('Bildirim geçmişi')) ?></h2>
    <?php if ($notifications === []): ?>
        <p class="muted mb-0"><?= e(__('Bu evrak için gönderilmiş bildirim yok.')) ?></p>
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
