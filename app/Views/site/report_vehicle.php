<?php
use App\Core\Settings;
$v = $vehicle;
$brand = (string) Settings::get('brand.title', 'Evrako');
$logo = (string) Settings::get('brand.logo_light', '');
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e((string) $title) ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font: 13px/1.5 -apple-system, Segoe UI, Roboto, Arial, sans-serif; color: #14213D; margin: 0; padding: 32px; background: #fff; }
        .report { max-width: 900px; margin: 0 auto; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #14213D; padding-bottom: 14px; margin-bottom: 18px; }
        .brand { font: 700 20px/1.2 Georgia, serif; letter-spacing: -.02em; }
        .muted { color: #5A6B87; }
        h1 { font-size: 19px; margin: 0 0 4px; }
        .meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px 20px; margin: 16px 0 22px; }
        .meta div span { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #5A6B87; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #E2E8F0; font-size: 12.5px; }
        th { background: #F4F7FB; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #5A6B87; }
        .tag { display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 700; }
        .green { background: #DCFCE7; color: #067A57; }
        .yellow { background: #FEF3C7; color: #8A5A00; }
        .orange { background: #FFEDD5; color: #9A3412; }
        .red { background: #FEE2E2; color: #B3261E; }
        .none { background: #EEF2F7; color: #5A6B87; }
        .foot { margin-top: 26px; font-size: 11px; color: #5A6B87; border-top: 1px solid #E2E8F0; padding-top: 10px; }
        .actions { margin-bottom: 20px; }
        .btn { display: inline-block; padding: 9px 16px; border-radius: 8px; background: #14213D; color: #fff; text-decoration: none; border: 0; cursor: pointer; font-size: 13px; }
        @media print {
            .actions { display: none; }
            body { padding: 0; }
            th { background: #F4F7FB !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
<div class="report">
    <div class="actions">
        <button class="btn" type="button" onclick="window.print()"><?= e(t('report.print')) ?></button>
        <a class="btn" style="background:#5A6B87" href="<?= e(url('/panel/araclar/' . (int) $v['id'])) ?>"><?= e(t('common.back')) ?></a>
    </div>

    <div class="head">
        <div class="brand"><?= e($brand) ?></div>
        <div style="text-align:right">
            <strong><?= e(t('report.title')) ?></strong><br>
            <span class="muted"><?= e(t('report.generated')) ?>: <?= e((string) $generated) ?></span>
        </div>
    </div>

    <h1 class="mono"><?= e((string) $v['plate']) ?></h1>
    <div class="muted"><?= e(trim((string) $v['brand'] . ' ' . (string) $v['model']) ?: label((string) $v['type_labels'])) ?> · <?= e(label((string) $v['type_labels'])) ?></div>

    <div class="meta">
        <div><span><?= e(t('vehicles.year')) ?></span><?= $v['model_year'] ? (int) $v['model_year'] : '-' ?></div>
        <div><span><?= e(t('vehicles.color')) ?></span><?= e((string) ($v['color'] ?: '-')) ?></div>
        <div><span><?= e(t('vehicles.vin')) ?></span><?= e((string) ($v['vin'] ?: '-')) ?></div>
        <div><span><?= e(t('vehicles.driver')) ?></span><?= e((string) ($v['driver_name'] ?: '-')) ?></div>
        <div><span><?= e(__('Kurum')) ?></span><?= e((string) ($membership['org_name'] ?? '-')) ?></div>
    </div>

    <table>
        <thead>
            <tr>
                <th><?= e(t('documents.type')) ?></th>
                <th><?= e(t('documents.issuer')) ?></th>
                <th><?= e(t('documents.policy_no')) ?></th>
                <th><?= e(t('documents.start')) ?></th>
                <th><?= e(t('documents.end')) ?></th>
                <th><?= e(t('vehicles.status')) ?></th>
                <th><?= e(t('documents.cost')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($documents)): ?>
                <tr><td colspan="7" class="muted"><?= e(t('vehicles.no_documents')) ?></td></tr>
            <?php else: ?>
                <?php foreach ($documents as $d): ?>
                    <?php $light = (string) $d['light']; ?>
                    <tr>
                        <td><strong><?= e((string) $d['type_label']) ?></strong></td>
                        <td><?= e((string) ($d['issuer'] ?: '-')) ?></td>
                        <td class="mono"><?= e((string) ($d['policy_no'] ?: '-')) ?></td>
                        <td><?= e(format_date((string) $d['start_date'])) ?></td>
                        <td><strong><?= e(format_date((string) $d['end_date'])) ?></strong></td>
                        <td><span class="tag <?= e($light) ?>"><?= e((string) $d['status'] === 'active' ? light_label($light) : t('documents.status_' . (string) $d['status'])) ?></span></td>
                        <td><?= $d['cost'] !== null ? e(number_format((float) $d['cost'], 2, ',', '.')) . ' ' . e((string) $d['currency']) : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="foot">
        <?= e(t('report.footer')) ?> · <?= e($brand) ?>
    </div>
</div>
<script>if (location.search.indexOf('print=1') !== -1) { window.print(); }</script>
</body>
</html>
