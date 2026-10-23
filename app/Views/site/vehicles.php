<?php
$active = 'vehicles';
$maxVehicles = (int) ($limits['max_vehicles'] ?? 0);
$unlimited = $maxVehicles >= 9999;
?>
<section class="section section--tight">
    <div class="container">
        <?php require BASE_PATH . '/app/Views/partials/panel_nav.php'; ?>

        <div class="row row--between mb-3">
            <div>
                <h1 style="font-size:1.7rem;margin-bottom:4px"><?= e(t('vehicles.title')) ?></h1>
                <p class="muted mb-0">
                    <?= e(t('vehicles.plan_usage', [
                        'used' => (int) $used,
                        'max'  => $unlimited ? t('vehicles.unlimited') : $maxVehicles,
                    ])) ?>
                </p>
            </div>
            <?php if (!empty($canManage) && !empty($canAddResult['ok'])): ?>
                <a class="btn btn-accent" href="<?= e(url('/panel/araclar/yeni')) ?>">+ <?= e(t('vehicles.add')) ?></a>
            <?php endif; ?>
        </div>

        <?php if (!empty($canManage) && empty($canAddResult['ok'])): ?>
            <div class="alert alert--info">
                <span><?= e((string) $canAddResult['error']) ?></span>
                <?php if (!empty($canAddResult['whatsapp'])): ?>
                    <a class="btn btn-accent btn-sm" style="margin-inline-start:10px" href="<?= e((string) $canAddResult['whatsapp']) ?>" target="_blank" rel="noopener noreferrer">WhatsApp'tan yazın</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if (empty($canManage)): ?>
            <div class="alert alert--info"><?= e(t('vehicles.read_only')) ?></div>
        <?php endif; ?>

        <form method="get" action="<?= e(url('/panel/araclar')) ?>" class="card mb-3">
            <div class="grid grid-3">
                <div class="field mb-0">
                    <label for="f-q"><?= e(t('common.search')) ?></label>
                    <input id="f-q" type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="<?= e(t('vehicles.search_placeholder')) ?>">
                </div>
                <div class="field mb-0">
                    <label for="f-tur"><?= e(t('vehicles.type')) ?></label>
                    <select id="f-tur" name="tur">
                        <option value="0"><?= e(t('common.all')) ?></option>
                        <?php foreach ($types as $type): ?>
                            <option value="<?= (int) $type['id'] ?>" <?= (int) ($filters['type_id'] ?? 0) === (int) $type['id'] ? 'selected' : '' ?>>
                                <?= e(label((string) $type['labels_json'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field mb-0">
                    <label for="f-durum"><?= e(t('vehicles.status')) ?></label>
                    <select id="f-durum" name="durum">
                        <option value=""><?= e(t('common.all')) ?></option>
                        <?php foreach (['active' => 'vehicles.status_active', 'sold' => 'vehicles.status_sold', 'archived' => 'vehicles.status_archived'] as $value => $key): ?>
                            <option value="<?= e($value) ?>" <?= (string) ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e(t($key)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row mt-2">
                <button class="btn btn-primary btn-sm" type="submit"><?= e(t('common.filter')) ?></button>
                <a class="btn btn-outline btn-sm" href="<?= e(url('/panel/araclar')) ?>"><?= e(t('common.default')) ?></a>
            </div>
        </form>

        <?php if (empty($vehicles)): ?>
            <div class="card center">
                <div class="feature-icon" style="margin-inline:auto">🚗</div>
                <h2 style="font-size:1.2rem"><?= e(t('vehicles.empty')) ?></h2>
                <p class="muted"><?= e(t('vehicles.empty_hint')) ?></p>
                <?php if (!empty($canManage)): ?>
                    <a class="btn btn-accent" href="<?= e(url('/panel/araclar/yeni')) ?>">+ <?= e(t('vehicles.add')) ?></a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="grid grid-2">
                <?php foreach ($vehicles as $v): ?>
                    <?php $light = (string) $v['light']; ?>
                    <a class="card card--hover" style="text-decoration:none;color:inherit" href="<?= e(url('/panel/araclar/' . (int) $v['id'])) ?>">
                        <div class="row row--between mb-2">
                            <span class="mock__plate" style="font-size:15px"><?= e((string) $v['plate']) ?></span>
                            <span class="badge <?= e(light_badge_class($light)) ?>">
                                <span style="display:inline-block;width:8px;height:8px;border-radius:99px;background:<?= e(light_color($light)) ?>"></span>
                                <?= e(light_label($light)) ?>
                            </span>
                        </div>
                        <div style="font-weight:600"><?= e(trim((string) $v['brand'] . ' ' . (string) $v['model']) ?: (string) $v['type_label']) ?></div>
                        <div class="muted" style="font-size:13.5px"><?= e((string) $v['type_label']) ?></div>
                        <div class="row mt-2" style="gap:16px;font-size:13.5px">
                            <span class="muted"><?= e(t('vehicles.documents')) ?>: <strong><?= (int) $v['active_docs'] ?></strong></span>
                            <span class="muted"><?= e(t('vehicles.next_due')) ?>:
                                <strong><?= $v['next_end'] ? e(format_date((string) $v['next_end'])) : e(t('documents.no_end')) ?></strong>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
