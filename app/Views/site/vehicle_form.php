<?php
$active = 'vehicles';
$isEdit = !empty($vehicle);
$val = static function (string $field, $default = '') use ($vehicle) {
    $old = old($field, null);
    if ($old !== null && $old !== '') {
        return $old;
    }
    return $vehicle[$field] ?? $default;
};
?>
<section class="section section--tight">
    <div class="container" style="max-width:820px">
        <?php require BASE_PATH . '/app/Views/partials/panel_nav.php'; ?>

        <div class="row row--between mb-3">
            <h1 style="font-size:1.6rem;margin:0"><?= e($isEdit ? t('vehicles.edit') : t('vehicles.add')) ?></h1>
            <a class="btn btn-outline btn-sm" href="<?= e($isEdit ? url('/panel/araclar/' . (int) $vehicle['id']) : url('/panel/araclar')) ?>"><?= e(t('common.back')) ?></a>
        </div>

        <?php if (!$isEdit && !empty($vehicleFee)): ?>
            <?php $feeMoney = \App\Services\VehiclePricing::money((float) $vehicleFee['price'], (string) $vehicleFee['currency']); ?>
            <div class="alert <?= !empty($vehicleFee['enough']) ? 'alert--info' : 'alert--error' ?>" role="status">
                <span aria-hidden="true"><?= !empty($vehicleFee['enough']) ? 'ℹ️' : '⚠️' ?></span>
                <div>
                    <strong><?= e(__(':no. araç için :fee', ['no' => (string) $vehicleFee['no'], 'fee' => $feeMoney])) ?></strong>
                    <?php if ((int) $vehicleFee['discount'] > 0): ?>
                        <span class="badge badge--ok">%<?= (int) $vehicleFee['discount'] ?> <?= e('indirim') ?></span>
                    <?php endif; ?>
                    <div style="font-size:13.5px;margin-top:2px">
                        <?= e(__('Bu tutar kredi bakiyenizden düşülecek. Evraklar için ayrıca ücret alınmaz.')) ?>
                        <?= e(' Bakiyeniz: ' . \App\Services\VehiclePricing::money((float) $vehicleFee['balance'], (string) $vehicleFee['currency'])) ?>
                    </div>
                    <?php if (empty($vehicleFee['enough'])): ?>
                        <a class="btn btn-sm btn-accent mt-2" href="<?= e(url('/panel/odeme')) ?>"><?= e(__('Kredi yükle')) ?></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e($formAction) ?>" class="card">
            <?= csrf_field() ?>
            <div class="grid grid-2">
                <div class="field">
                    <label for="plate"><?= e(t('vehicles.plate')) ?> *</label>
                    <input id="plate" class="mono <?= old_has_error('plate') ? 'invalid' : '' ?>" type="text" name="plate" required maxlength="24" value="<?= e((string) $val('plate')) ?>" placeholder="AA 123 A">
                    <?php if (old_has_error('plate')): ?><div class="form-error"><?= e(error_first('plate')) ?></div><?php endif; ?>
                </div>
                <div class="field">
                    <label for="vehicle_type_id"><?= e(t('vehicles.type')) ?> *</label>
                    <select id="vehicle_type_id" name="vehicle_type_id" required>
                        <option value=""><?= e(t('vehicles.choose')) ?></option>
                        <?php foreach ($types as $type): ?>
                            <option value="<?= (int) $type['id'] ?>" <?= (int) $val('vehicle_type_id') === (int) $type['id'] ? 'selected' : '' ?>>
                                <?= e(label((string) $type['labels_json'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="brand"><?= e(t('vehicles.brand')) ?></label>
                    <input id="brand" type="text" name="brand" maxlength="80" value="<?= e((string) $val('brand')) ?>">
                </div>
                <div class="field">
                    <label for="model"><?= e(t('vehicles.model')) ?></label>
                    <input id="model" type="text" name="model" maxlength="80" value="<?= e((string) $val('model')) ?>">
                </div>
                <div class="field">
                    <label for="model_year"><?= e(t('vehicles.year')) ?></label>
                    <input id="model_year" type="number" name="model_year" min="1950" max="2100" value="<?= e((string) $val('model_year')) ?>">
                </div>
                <div class="field">
                    <label for="color"><?= e(t('vehicles.color')) ?></label>
                    <input id="color" type="text" name="color" maxlength="40" value="<?= e((string) $val('color')) ?>">
                </div>
                <div class="field">
                    <label for="vin"><?= e(t('vehicles.vin')) ?></label>
                    <input id="vin" class="mono" type="text" name="vin" maxlength="40" value="<?= e((string) $val('vin')) ?>">
                </div>
                <div class="field">
                    <label for="engine_no"><?= e(t('vehicles.engine_no')) ?></label>
                    <input id="engine_no" class="mono" type="text" name="engine_no" maxlength="40" value="<?= e((string) $val('engine_no')) ?>">
                </div>
                <div class="field">
                    <label for="odometer"><?= e(t('vehicles.odometer')) ?></label>
                    <input id="odometer" type="number" name="odometer" min="0" value="<?= e((string) $val('odometer')) ?>">
                </div>
                <div class="field">
                    <label for="driver_name"><?= e(t('vehicles.driver')) ?></label>
                    <input id="driver_name" type="text" name="driver_name" maxlength="120" value="<?= e((string) $val('driver_name')) ?>">
                </div>
            </div>

            <?php if ($isEdit): ?>
                <div class="field">
                    <label for="status"><?= e(t('vehicles.status')) ?></label>
                    <select id="status" name="status">
                        <?php foreach (['active' => 'vehicles.status_active', 'sold' => 'vehicles.status_sold', 'archived' => 'vehicles.status_archived'] as $value => $key): ?>
                            <option value="<?= e($value) ?>" <?= (string) $val('status', 'active') === $value ? 'selected' : '' ?>><?= e(t($key)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="field">
                <label for="notes"><?= e(t('vehicles.notes')) ?></label>
                <textarea id="notes" name="notes" maxlength="2000"><?= e((string) $val('notes')) ?></textarea>
            </div>

            <div class="row">
                <button class="btn btn-accent" type="submit"><?= e(t('common.save')) ?></button>
                <a class="btn btn-outline" href="<?= e($isEdit ? url('/panel/araclar/' . (int) $vehicle['id']) : url('/panel/araclar')) ?>"><?= e(t('common.cancel')) ?></a>
            </div>
        </form>

        <?php if ($isEdit): ?>
            <form method="post" action="<?= e(url('/panel/araclar/' . (int) $vehicle['id'] . '/arsivle')) ?>" class="mt-3"
                  onsubmit="return confirm('<?= e(t('vehicles.archive_confirm')) ?>');">
                <?= csrf_field() ?>
                <button class="btn btn-danger btn-sm" type="submit"><?= e(t('vehicles.archive')) ?></button>
            </form>
        <?php endif; ?>
    </div>
</section>
