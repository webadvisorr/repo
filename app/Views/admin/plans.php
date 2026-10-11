<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.6rem;margin:0 0 4px"><?= e(t('admin.plans')) ?></h1>
        <p class="muted mb-0"><?= e('Fiyat, araç/kullanıcı limiti ve bildirim kanallarını yönetin.') ?></p>
    </div>
</div>

<div class="grid grid-3 mb-3">
    <div class="card stat"><div class="stat__value"><?= (int) $totals['active_subs'] ?></div><div class="stat__label"><?= e('Aktif abonelik') ?></div></div>
    <div class="card stat"><div class="stat__value"><?= e(number_format((float) $totals['mrr'], 2, ',', '.')) ?></div><div class="stat__label"><?= e('Aylık yinelenen gelir (MRR)') ?></div></div>
    <div class="card stat"><div class="stat__value"><?= e(number_format((float) $totals['revenue'], 2, ',', '.')) ?></div><div class="stat__label"><?= e('Toplam tahsilat') ?></div></div>
</div>

<?php foreach ($plans as $plan): ?>
    <form method="post" action="<?= e(url('/yonetim/planlar/kaydet')) ?>" class="card mb-3">
        <?= csrf_field() ?>
        <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
        <div class="row row--between mb-2">
            <h2 style="font-size:1.05rem;margin:0">
                <?= e((string) $plan['name']) ?>
                <span class="muted mono" style="font-size:12.5px">(<?= e((string) $plan['key']) ?>)</span>
            </h2>
            <div class="row">
                <span class="badge badge--info"><?= (int) $plan['subscriber_count'] ?> <?= e('abone') ?></span>
                <span class="badge badge--ok"><?= e(number_format((float) $plan['revenue'], 2, ',', '.')) ?> <?= e('tahsilat') ?></span>
            </div>
        </div>

        <div class="field-grid">
            <div class="field">
                <label><?= e('Plan adı') ?></label>
                <input type="text" name="name" maxlength="80" value="<?= e((string) $plan['name']) ?>" required>
            </div>
            <div class="field">
                <label><?= e('Fiyat') ?></label>
                <input type="number" name="price" step="0.01" min="0" value="<?= e((string) $plan['price']) ?>" required>
            </div>
            <div class="field">
                <label><?= e('Para birimi') ?></label>
                <select name="currency">
                    <?php foreach (['TRY', 'EUR', 'USD', 'GBP'] as $c): ?>
                        <option value="<?= e($c) ?>" <?= (string) $plan['currency'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label><?= e('Periyot') ?></label>
                <select name="billing_interval">
                    <?php foreach (['month' => 'Aylık', 'year' => 'Yıllık', 'lifetime' => 'Ömür boyu'] as $k => $v): ?>
                        <option value="<?= e($k) ?>" <?= (string) $plan['billing_interval'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label><?= e('Maks. araç') ?></label>
                <input type="number" name="max_vehicles" min="1" value="<?= (int) $plan['max_vehicles'] ?>" required>
            </div>
            <div class="field">
                <label><?= e('Maks. kullanıcı') ?></label>
                <input type="number" name="max_users" min="1" value="<?= (int) $plan['max_users'] ?>" required>
            </div>
            <div class="field">
                <label><?= e('Araç başına maks. evrak') ?></label>
                <input type="number" name="max_documents_per_vehicle" min="1" value="<?= (int) $plan['max_documents_per_vehicle'] ?>" required>
            </div>
            <div class="field">
                <label><?= e('Deneme süresi (gün)') ?></label>
                <input type="number" name="trial_days" min="0" max="365" value="<?= (int) $plan['trial_days'] ?>" required>
            </div>
        </div>

        <div class="field">
            <label><?= e('Bildirim kanalları') ?></label>
            <div class="row">
                <?php $channels = explode(',', (string) $plan['channels']); ?>
                <?php foreach (['email' => 'E-posta', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'push' => 'Bildirim (push)'] as $k => $v): ?>
                    <label class="check" style="align-items:center">
                        <input type="checkbox" name="channels[]" value="<?= e($k) ?>" <?= in_array($k, $channels, true) ? 'checked' : '' ?>>
                        <span style="font-size:13.5px"><?= e($v) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="field">
            <label><?= e('Kısa açıklama') ?></label>
            <input type="text" name="description" maxlength="250" value="<?= e((string) ($plan['description'] ?? '')) ?>">
        </div>

        <div class="field check">
            <input type="checkbox" name="is_active" value="1" id="active-<?= (int) $plan['id'] ?>" <?= (int) $plan['is_active'] === 1 ? 'checked' : '' ?>>
            <label for="active-<?= (int) $plan['id'] ?>"><?= e('Plan satışa açık (aktif)') ?></label>
        </div>

        <div class="row">
            <button class="btn btn-accent" type="submit"><?= e(t('common.save')) ?></button>
        </div>
    </form>
    <?php if ((int) $plan['subscriber_count'] === 0): ?>
        <form method="post" action="<?= e(url('/yonetim/planlar/sil')) ?>" data-confirm="<?= e('Bu plan silinecek. Onaylıyor musunuz?') ?>" style="margin-top:-12px;margin-bottom:22px">
            <?= csrf_field() ?>
            <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
            <button class="btn btn-outline btn-sm" type="submit" style="color:var(--c-danger);border-color:var(--c-danger)"><?= e('Planı sil') ?></button>
        </form>
    <?php endif; ?>
<?php endforeach; ?>

<div class="card">
    <h2 style="font-size:1.05rem"><?= e('Yeni plan ekle') ?></h2>
    <form method="post" action="<?= e(url('/yonetim/planlar/ekle')) ?>">
        <?= csrf_field() ?>
        <div class="field-grid">
            <div class="field">
                <label><?= e('Plan anahtarı (benzersiz)') ?></label>
                <input type="text" name="key" pattern="[a-zA-Z0-9_]{2,40}" required placeholder="ornek: kurumsal">
            </div>
            <div class="field">
                <label><?= e('Plan adı') ?></label>
                <input type="text" name="name" maxlength="80" required>
            </div>
            <div class="field">
                <label><?= e('Fiyat') ?></label>
                <input type="number" name="price" step="0.01" min="0" value="0" required>
            </div>
            <div class="field">
                <label><?= e('Para birimi') ?></label>
                <select name="currency">
                    <option value="TRY">TRY</option><option value="EUR">EUR</option><option value="USD">USD</option><option value="GBP">GBP</option>
                </select>
            </div>
            <div class="field">
                <label><?= e('Periyot') ?></label>
                <select name="billing_interval">
                    <option value="month"><?= e('Aylık') ?></option>
                    <option value="year"><?= e('Yıllık') ?></option>
                    <option value="lifetime"><?= e('Ömür boyu') ?></option>
                </select>
            </div>
            <div class="field">
                <label><?= e('Maks. araç') ?></label>
                <input type="number" name="max_vehicles" min="1" value="5" required>
            </div>
            <div class="field">
                <label><?= e('Maks. kullanıcı') ?></label>
                <input type="number" name="max_users" min="1" value="1" required>
            </div>
            <div class="field">
                <label><?= e('Araç başına maks. evrak') ?></label>
                <input type="number" name="max_documents_per_vehicle" min="1" value="20" required>
            </div>
            <div class="field">
                <label><?= e('Deneme süresi (gün)') ?></label>
                <input type="number" name="trial_days" min="0" max="365" value="14" required>
            </div>
        </div>
        <button class="btn btn-primary" type="submit"><?= e(t('common.add')) ?></button>
    </form>
</div>
