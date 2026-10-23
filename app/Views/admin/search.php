<?php
/** Yönetim hızlı arama sonuçları: müşteri, kurum ve araç. */
?>
<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.6rem;margin:0 0 4px">🔍 <?= e(__('Arama')) ?></h1>
        <p class="muted mb-0">
            <?php if ($q !== ''): ?>
                <?= e('“' . $q . '” için sonuçlar') ?>
            <?php else: ?>
                <?= e(__('Müşteriyi ad, e-posta veya telefonla arayın.')) ?>
            <?php endif; ?>
        </p>
    </div>
</div>

<form method="get" action="<?= e(url('/yonetim/arama')) ?>" class="card mb-3">
    <div class="row" style="flex-wrap:wrap">
        <input type="search" name="q" value="<?= e($q) ?>" style="flex:2;min-width:240px"
               placeholder="<?= e(__('Ad, e-posta, telefon, plaka veya kurum…')) ?>" autofocus>
        <button class="btn btn-primary" type="submit"><?= e(__('Ara')) ?></button>
    </div>
</form>

<div id="sonuclar">
<?php if ($q === ''): ?>
    <div class="card center"><p class="muted mb-0"><?= e(__('Aramak için yukarıdaki alana yazın.')) ?></p></div>
<?php elseif ($users === [] && $organizations === [] && $vehicles === []): ?>
    <div class="card center"><p class="muted mb-0"><?= e(__('Sonuç bulunamadı.')) ?></p></div>
<?php else: ?>
    <?php if ($users !== []): ?>
        <div class="card mb-3">
            <h2 style="font-size:1.05rem">👥 <?= e(__('Müşteriler')) ?> (<?= count($users) ?>)</h2>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>#</th><th><?= e(__('Ad')) ?></th><th><?= e(__('E-posta')) ?></th><th><?= e(__('Telefon')) ?></th><th><?= e(__('Kurum')) ?></th><th><?= e(__('Durum')) ?></th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="mono"><?= (int) $user['id'] ?></td>
                            <td><strong><?= e((string) $user['full_name']) ?></strong><?php if ((string) $user['role'] !== 'customer'): ?> <span class="badge badge--info"><?= e((string) $user['role']) ?></span><?php endif; ?></td>
                            <td><a href="mailto:<?= e((string) $user['email']) ?>"><?= e((string) $user['email']) ?></a></td>
                            <td><?= e((string) ($user['phone'] ?: '-')) ?></td>
                            <td><?= e((string) ($user['organization_name'] ?: '-')) ?></td>
                            <td><span class="badge <?= (string) $user['status'] === 'active' ? 'badge--ok' : 'badge--danger' ?>"><?= e((string) $user['status']) ?></span></td>
                            <td><a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/uyeler/' . (int) $user['id'])) ?>"><?= e(__('Aç')) ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($organizations !== []): ?>
        <div class="card mb-3">
            <h2 style="font-size:1.05rem">🏢 <?= e(__('Kurumlar')) ?> (<?= count($organizations) ?>)</h2>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>#</th><th><?= e(__('Kurum')) ?></th><th><?= e(__('Sahip')) ?></th><th><?= e(__('Telefon')) ?></th><th><?= e(__('Şehir')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($organizations as $org): ?>
                        <tr>
                            <td class="mono"><?= (int) $org['id'] ?></td>
                            <td><?= e((string) $org['name']) ?></td>
                            <td><a href="<?= e(url('/yonetim/uyeler?q=' . urlencode((string) $org['owner_email']))) ?>"><?= e((string) ($org['owner_name'] ?: $org['owner_email'])) ?></a></td>
                            <td><?= e((string) ($org['phone'] ?: '-')) ?></td>
                            <td><?= e((string) ($org['city'] ?: '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($vehicles !== []): ?>
        <div class="card">
            <h2 style="font-size:1.05rem">🚗 <?= e(__('Araçlar')) ?> (<?= count($vehicles) ?>)</h2>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th><?= e(__('Plaka')) ?></th><th><?= e(__('Araç')) ?></th><th><?= e(__('Kurum')) ?></th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($vehicles as $vehicle): ?>
                        <tr>
                            <td class="mono"><a href="<?= e(url('/yonetim/araclar/' . (int) $vehicle['id'])) ?>"><?= e((string) $vehicle['plate']) ?></a></td>
                            <td><?= e(trim((string) $vehicle['brand'] . ' ' . (string) $vehicle['model'])) ?></td>
                            <td><?= e((string) ($vehicle['organization_name'] ?: '-')) ?></td>
                            <td><a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/araclar/' . (int) $vehicle['id'])) ?>"><?= e(__('Aç')) ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
</div>
