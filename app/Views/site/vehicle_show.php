<?php
$active = 'vehicles';
$v = $vehicle;
$addMonths = static function (string $date, int $months): string {
    return date('Y-m-d', strtotime($date . ' +' . $months . ' months'));
};
$today = date('Y-m-d');

// Trafik ışığı özeti
$levels = [];
$nextEnd = null;
foreach ($documents as $d) {
    if ((string) $d['status'] === 'active') {
        $levels[] = (string) $d['light'];
        if ($nextEnd === null || (string) $d['end_date'] < $nextEnd) {
            $nextEnd = (string) $d['end_date'];
        }
    }
}
$summaryOrder = ['red' => 4, 'orange' => 3, 'yellow' => 2, 'green' => 1, 'none' => 0];
$summary = 'none';
foreach ($levels as $lvl) {
    if (($summaryOrder[$lvl] ?? 0) > ($summaryOrder[$summary] ?? 0)) {
        $summary = $lvl;
    }
}

// Tekrar kullanılabilir evrak formu
$docForm = function (string $action, array $doc = [], bool $isRenew = false) use ($docTypes, $currency, $addMonths, $today): void {
    $typeId = (int) ($doc['document_type_id'] ?? 0);
    $defaultMonths = 12;
    foreach ($docTypes as $t) {
        if ((int) $t['id'] === $typeId) {
            $defaultMonths = (int) $t['default_validity_months'];
            break;
        }
    }
    if ($isRenew && !empty($doc['end_date'])) {
        $start = date('Y-m-d', strtotime((string) $doc['end_date'] . ' +1 day'));
        if ($start < $today) {
            $start = $today;
        }
    } else {
        $start = $doc['start_date'] ?? $today;
    }
    $end = $isRenew ? $addMonths($start, $defaultMonths) : ($doc['end_date'] ?? '');
    ?>
    <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <?php if ($isRenew): ?>
            <p class="muted" style="font-size:13.5px"><?= e(t('documents.renew_hint')) ?></p>
        <?php endif; ?>
        <div class="grid grid-2">
            <?php if (!$isRenew): ?>
                <div class="field">
                    <label><?= e(t('documents.type')) ?> *</label>
                    <select name="document_type_id" required>
                        <?php foreach ($docTypes as $t): ?>
                            <option value="<?= (int) $t['id'] ?>" data-months="<?= (int) $t['default_validity_months'] ?>" <?= (int) $t['id'] === $typeId ? 'selected' : '' ?>>
                                <?= e(label((string) $t['labels_json'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <div class="field">
                    <label><?= e(t('documents.type')) ?></label>
                    <input type="text" value="<?= e(label((string) $doc['type_labels'])) ?>" disabled>
                </div>
            <?php endif; ?>
            <div class="field">
                <label><?= e(t('documents.issuer')) ?></label>
                <input type="text" name="issuer" maxlength="160" value="<?= e((string) ($doc['issuer'] ?? '')) ?>">
            </div>
            <div class="field">
                <label><?= e(t('documents.policy_no')) ?></label>
                <input class="mono" type="text" name="policy_no" maxlength="80" value="<?= e((string) ($doc['policy_no'] ?? '')) ?>">
            </div>
            <div class="field">
                <label><?= e(t('documents.start')) ?></label>
                <input type="date" name="start_date" value="<?= e((string) $start) ?>">
            </div>
            <div class="field">
                <label><?= e(t('documents.end')) ?> *</label>
                <input type="date" name="end_date" required value="<?= e((string) $end) ?>">
            </div>
            <div class="field">
                <label><?= e(t('documents.cost')) ?></label>
                <input type="text" name="cost" inputmode="decimal" value="<?= $doc['cost'] !== null && $doc['cost'] !== '' ? e((string) $doc['cost']) : '' ?>">
            </div>
            <div class="field">
                <label><?= e(t('documents.currency')) ?></label>
                <select name="currency">
                    <?php foreach (['TRY', 'USD', 'EUR', 'GBP'] as $cur): ?>
                        <option value="<?= e($cur) ?>" <?= (string) ($doc['currency'] ?? $currency) === $cur ? 'selected' : '' ?>><?= e($cur) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="field">
            <label><?= e(t('documents.notes')) ?></label>
            <textarea name="notes" maxlength="2000"><?= e((string) ($doc['notes'] ?? '')) ?></textarea>
        </div>
        <div class="field">
            <label><?= e(t('documents.files')) ?></label>
            <input type="file" name="files[]" multiple accept="image/*,application/pdf">
            <div class="hint"><?= e(t('documents.upload_hint')) ?></div>
        </div>
        <button class="btn btn-accent btn-sm" type="submit"><?= e(t('common.save')) ?></button>
    </form>
    <?php
};
?>
<section class="section section--tight">
    <div class="container">
        <?php require BASE_PATH . '/app/Views/partials/panel_nav.php'; ?>

        <div class="row row--between mb-3">
            <div class="row" style="gap:14px">
                <span class="mock__plate" style="font-size:17px"><?= e((string) $v['plate']) ?></span>
                <div>
                    <h1 style="font-size:1.5rem;margin:0"><?= e(trim((string) $v['brand'] . ' ' . (string) $v['model']) ?: label((string) $v['type_labels'])) ?></h1>
                    <p class="muted mb-0" style="font-size:13.5px"><?= e(label((string) $v['type_labels'])) ?></p>
                </div>
                <span class="badge <?= e(light_badge_class($summary)) ?>">
                    <span style="display:inline-block;width:8px;height:8px;border-radius:99px;background:<?= e(light_color($summary)) ?>"></span>
                    <?= e(light_label($summary)) ?>
                </span>
            </div>
            <div class="row" style="gap:8px">
                <a class="btn btn-outline btn-sm" href="<?= e(url('/panel/araclar')) ?>"><?= e(t('common.back')) ?></a>
                <a class="btn btn-outline btn-sm" href="<?= e(url('/panel/araclar/' . (int) $v['id'] . '/rapor')) ?>" target="_blank" rel="noopener">🖨 <?= e(t('report.title')) ?></a>
                <?php if (!empty($canManageVehicles)): ?>
                    <a class="btn btn-primary btn-sm" href="<?= e(url('/panel/araclar/' . (int) $v['id'] . '/duzenle')) ?>"><?= e(t('common.edit')) ?></a>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="grid grid-4" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px">
                <div><div class="muted" style="font-size:12.5px"><?= e(t('vehicles.year')) ?></div><strong><?= $v['model_year'] ? (int) $v['model_year'] : '-' ?></strong></div>
                <div><div class="muted" style="font-size:12.5px"><?= e(t('vehicles.color')) ?></div><strong><?= e((string) ($v['color'] ?: '-')) ?></strong></div>
                <div><div class="muted" style="font-size:12.5px"><?= e(t('vehicles.vin')) ?></div><strong class="mono"><?= e((string) ($v['vin'] ?: '-')) ?></strong></div>
                <div><div class="muted" style="font-size:12.5px"><?= e(t('vehicles.driver')) ?></div><strong><?= e((string) ($v['driver_name'] ?: '-')) ?></strong></div>
                <div><div class="muted" style="font-size:12.5px"><?= e(t('vehicles.next_due')) ?></div><strong><?= $nextEnd ? e(format_date($nextEnd)) : e(t('documents.no_end')) ?></strong></div>
                <div><div class="muted" style="font-size:12.5px"><?= e(t('vehicles.documents')) ?></div><strong><?= (int) $docCount ?> / <?= $maxDocs >= 9999 ? e(t('vehicles.unlimited')) : (int) $maxDocs ?></strong></div>
            </div>
        </div>

        <h2 style="font-size:1.2rem"><?= e(t('documents.title')) ?></h2>
        <?php if (empty($documents)): ?>
            <div class="card mb-3"><p class="muted mb-0"><?= e(t('vehicles.no_documents')) ?></p></div>
        <?php else: ?>
            <div class="stack mb-3">
                <?php foreach ($documents as $d): ?>
                    <?php $light = (string) $d['light']; $days = $d['days_left']; $isActive = (string) $d['status'] === 'active'; ?>
                    <div class="card">
                        <div class="row row--between mb-2">
                            <div class="row" style="gap:10px">
                                <span style="display:inline-block;width:12px;height:12px;border-radius:99px;background:<?= e(light_color($light)) ?>"></span>
                                <strong style="font-size:1.05rem"><?= e((string) $d['type_label']) ?></strong>
                                <?php if ((string) $d['status'] === 'renewed'): ?>
                                    <span class="badge badge--info"><?= e(t('documents.status_renewed')) ?></span>
                                <?php elseif ((string) $d['status'] === 'cancelled'): ?>
                                    <span class="badge badge--danger"><?= e(t('documents.status_cancelled')) ?></span>
                                <?php else: ?>
                                    <span class="badge <?= e(light_badge_class($light)) ?>"><?= e(light_label($light)) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="muted mono" style="font-size:13px">
                                <?= e(format_date((string) $d['start_date'])) ?> → <strong><?= e(format_date((string) $d['end_date'])) ?></strong>
                                <?php if ($isActive && $days !== null): ?>
                                    ·
                                    <?php if ($days < 0): ?>
                                        <?= e(t('documents.days_over', ['days' => abs($days)])) ?>
                                    <?php elseif ($days === 0): ?>
                                        <?= e(t('documents.today')) ?>
                                    <?php else: ?>
                                        <?= e(t('documents.days_left', ['days' => $days])) ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="row" style="gap:18px;font-size:13.5px">
                            <?php if (!empty($d['issuer'])): ?><span class="muted"><?= e(t('documents.issuer')) ?>: <strong><?= e((string) $d['issuer']) ?></strong></span><?php endif; ?>
                            <?php if (!empty($d['policy_no'])): ?><span class="muted"><?= e(t('documents.policy_no')) ?>: <strong class="mono"><?= e((string) $d['policy_no']) ?></strong></span><?php endif; ?>
                            <?php if ($d['cost'] !== null): ?><span class="muted"><?= e(t('documents.cost')) ?>: <strong><?= e(number_format((float) $d['cost'], 2, ',', '.')) ?> <?= e((string) $d['currency']) ?></strong></span><?php endif; ?>
                        </div>

                        <?php if (!empty($d['files'])): ?>
                            <div class="row mt-2" style="gap:8px">
                                <?php foreach ($d['files'] as $file): ?>
                                    <span class="badge badge--info" style="gap:8px">
                                        📎 <a href="<?= e(storage_url((string) $file['disk_path'])) ?>" target="_blank" rel="noopener" style="color:inherit"><?= e((string) ($file['original_name'] ?: $file['disk_path'])) ?></a>
                                        <span class="muted" style="font-size:11.5px"><?= e(format_bytes((int) $file['size_bytes'])) ?></span>
                                        <?php if (!empty($canManageDocuments)): ?>
                                            <form method="post" action="<?= e(url('/panel/evrak/' . (int) $d['id'] . '/dosya/' . (int) $file['id'] . '/sil')) ?>" style="display:inline">
                                                <?= csrf_field() ?>
                                                <button type="submit" title="<?= e(t('common.delete')) ?>" style="border:0;background:none;cursor:pointer;color:inherit">✕</button>
                                            </form>
                                        <?php endif; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($isActive): ?>
                            <div class="row mt-2" style="gap:8px">
                                <a class="btn btn-outline btn-sm" href="<?= e(url('/panel/evrak/' . (int) $d['id'] . '/takvim.ics')) ?>">📅 <?= e(t('calendar.add_ics')) ?></a>
                                <a class="btn btn-outline btn-sm" target="_blank" rel="noopener"
                                   href="<?= e(google_calendar_url((string) $d['type_label'] . ' — ' . (string) $v['plate'], (string) ($d['start_date'] ?: $d['end_date']), (string) $d['end_date'], (string) ($d['issuer'] ?? ''))) ?>">Google Takvim</a>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($canManageDocuments) && $isActive): ?>
                            <div class="mt-2">
                                <details>
                                    <summary style="cursor:pointer;font-weight:600;color:var(--c-accent)">🔄 <?= e(t('documents.renew')) ?></summary>
                                    <div class="mt-2"><?php $docForm(url('/panel/evrak/' . (int) $d['id'] . '/yenile'), $d, true); ?></div>
                                </details>
                                <details class="mt-2">
                                    <summary style="cursor:pointer;font-weight:600"><?= e(t('common.edit')) ?></summary>
                                    <div class="mt-2"><?php $docForm(url('/panel/evrak/' . (int) $d['id']), $d, false); ?></div>
                                </details>
                                <form method="post" action="<?= e(url('/panel/evrak/' . (int) $d['id'] . '/sil')) ?>" class="mt-2" onsubmit="return confirm('<?= e(t('documents.delete_confirm')) ?>');">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-danger btn-sm" type="submit"><?= e(t('common.delete')) ?></button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($d['renewed_from_id'])): ?>
                            <p class="muted mt-2 mb-0" style="font-size:12.5px">↳ <?= e(t('documents.history')) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($canManageDocuments)): ?>
            <?php if ($docCount >= $maxDocs && $maxDocs < 9999): ?>
                <div class="alert alert--info"><?= e(t('documents.limit', ['max' => $maxDocs])) ?></div>
            <?php else: ?>
                <div class="card">
                    <h3 style="font-size:1.05rem">+ <?= e(t('documents.add')) ?></h3>
                    <?php $docForm(url('/panel/araclar/' . (int) $v['id'] . '/evrak')); ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
