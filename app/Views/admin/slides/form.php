<?php
$isEdit = $slide !== null;
$value = static fn (string $key, string $default = ''): string => (string) ($slide[$key] ?? $default);
$image = $value('image_path');
?>
<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.5rem;margin:0 0 4px"><?= e($isEdit ? 'Slaytı düzenle' : 'Yeni slayt') ?></h1>
        <p class="muted mb-0"><?= e(__('Görsel ve metinler ana sayfa slider’ında gösterilir. Boş alanlar gizlenir.')) ?></p>
    </div>
    <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/slider')) ?>">← <?= e(__('Slider')) ?></a>
</div>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $slide['id'] ?>"><?php endif; ?>

    <div class="card mb-3">
        <h2 style="font-size:1.05rem" data-hint="Slayt metinleri|Ana sayfada büyük başlık, alt başlık ve açıklama olarak görünür. Boş bırakılan alan sitede gösterilmez."><?= e(__('İçerik')) ?></h2>
        <div class="field-grid">
            <div class="field">
                <label for="slide-title"><?= e(__('Başlık')) ?></label>
                <input id="slide-title" type="text" name="title" maxlength="200" value="<?= e($value('title')) ?>" placeholder="<?= e(__('Örn. Evraklarınız süresi dolmadan hatırlatılır')) ?>">
            </div>
            <div class="field">
                <label for="slide-subtitle"><?= e(__('Alt başlık')) ?></label>
                <input id="slide-subtitle" type="text" name="subtitle" maxlength="255" value="<?= e($value('subtitle')) ?>">
            </div>
            <div class="field" style="grid-column:1/-1">
                <label for="slide-description"><?= e(__('Açıklama')) ?></label>
                <textarea id="slide-description" name="description" rows="3" maxlength="2000"><?= e($value('description')) ?></textarea>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <h2 style="font-size:1.05rem" data-hint="Slayt görseli|Yatay (en az 1600×900) bir görsel yükleyin. Yeni görsel yüklemezseniz mevcut görsel korunur; isterseniz kaldırabilirsiniz."><?= e(__('Görsel')) ?></h2>
        <?php if ($image !== ''): ?>
            <div class="media-preview mb-2">
                <img src="<?= e(media_url($image)) ?>" alt="<?= e($value('title')) ?>">
                <span class="mono muted" style="font-size:12px;word-break:break-all"><?= e($image) ?></span>
            </div>
            <label class="check"><input type="checkbox" name="remove_image" value="1"> <?= e(__('Mevcut görseli kaldır')) ?></label>
        <?php endif; ?>
        <div class="field mt-2">
            <label for="slide-image"><?= e($isEdit ? 'Yeni görsel yükle (isteğe bağlı)' : 'Slayt görseli') ?></label>
            <input id="slide-image" type="file" name="image" accept=".jpg,.jpeg,.png,.gif,.webp,.avif,.apng">
            <p class="hint"><?= e(__('Yatay görsel önerilir (en az 1600×900). En fazla 8 MB; jpg, png, webp, gif, avif.')) ?></p>
        </div>
    </div>

    <div class="card mb-3">
        <h2 style="font-size:1.05rem" data-hint="Buton ve yerleşim|Buton yazısı, gideceği adres, aynı/yeni sekme, metin konumu ve karartma oranını belirler."><?= e(__('Buton ve yerleşim')) ?></h2>
        <div class="field-grid">
            <div class="field">
                <label for="slide-button-text"><?= e(__('Buton metni')) ?></label>
                <input id="slide-button-text" type="text" name="button_text" maxlength="80" value="<?= e($value('button_text')) ?>" placeholder="<?= e(__('Örn. Ücretsiz başla')) ?>">
            </div>
            <div class="field">
                <label for="slide-button-url"><?= e(__('Buton bağlantısı')) ?></label>
                <input id="slide-button-url" type="text" name="button_url" maxlength="255" value="<?= e($value('button_url')) ?>" placeholder="<?= e('/kayit veya https://…') ?>">
                <p class="hint"><?= e(__('Güvenlik için yalnızca / ile başlayan yol veya http(s) adresi kabul edilir.')) ?></p>
            </div>
            <div class="field">
                <label for="slide-target"><?= e(__('Bağlantı hedefi')) ?></label>
                <select id="slide-target" name="button_target">
                    <option value="_self" <?= $value('button_target', '_self') === '_self' ? 'selected' : '' ?>><?= e(__('Aynı sekme')) ?></option>
                    <option value="_blank" <?= $value('button_target') === '_blank' ? 'selected' : '' ?>><?= e(__('Yeni sekme')) ?></option>
                </select>
            </div>
            <div class="field">
                <label for="slide-position"><?= e(__('Metin konumu')) ?></label>
                <select id="slide-position" name="text_position">
                    <?php foreach (['left' => 'Sol', 'center' => 'Orta', 'right' => 'Sağ'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $value('text_position', 'left') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="slide-overlay"><?= e(__('Karartma yoğunluğu (%)')) ?></label>
                <input id="slide-overlay" type="number" name="overlay_opacity" min="0" max="100" value="<?= (int) ($slide['overlay_opacity'] ?? 35) ?>">
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <h2 style="font-size:1.05rem" data-hint="Yayın ayarları|Dil, sıra ve yayın durumu. Aynı dilde birden fazla slayt varsa küçük sıra numarası önce gösterilir."><?= e(__('Yayın')) ?></h2>
        <div class="field-grid">
            <div class="field">
                <label for="slide-locale"><?= e(__('Dil')) ?></label>
                <select id="slide-locale" name="locale">
                    <?php foreach ($locales as $code => $label): ?>
                        <option value="<?= e($code) ?>" <?= $value('locale') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="hint"><?= e(__('“Tüm diller” seçilirse slayt her dilde gösterilir.')) ?></p>
            </div>
            <div class="field">
                <label for="slide-order"><?= e(__('Sıra')) ?></label>
                <input id="slide-order" type="number" name="sort_order" min="-999" max="9999" value="<?= (int) ($slide['sort_order'] ?? 0) ?>">
                <p class="hint"><?= e(__('Küçük sayı önce gösterilir.')) ?></p>
            </div>
            <div class="field" style="justify-content:flex-end">
                <label class="check" style="margin-top:26px">
                    <input type="checkbox" name="is_active" value="1" <?= (int) ($slide['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <?= e(__('Yayında')) ?>
                </label>
            </div>
        </div>
    </div>

    <div class="row">
        <button class="btn btn-accent" type="submit"><?= e($isEdit ? 'Değişiklikleri kaydet' : 'Slaytı ekle') ?></button>
        <a class="btn btn-outline" href="<?= e(url('/yonetim/slider')) ?>"><?= e(__('Vazgeç')) ?></a>
    </div>
</form>
