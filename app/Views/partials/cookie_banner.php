<?php
use App\Core\Settings;
if ((string) Settings::get('cookie.enabled', '1') !== '1') {
    return;
}
$title = __((string) Settings::get('cookie.title', __('Çerez kullanımı')));
$text = __((string) Settings::get('cookie.text', __('Deneyiminizi iyileştirmek ve site trafiğini ölçmek için çerezler kullanıyoruz. Zorunlu çerezler sitenin çalışması için gereklidir.')));
$accept = __((string) Settings::get('cookie.accept_label', 'Kabul et'));
$decline = __((string) Settings::get('cookie.decline_label', 'Sadece zorunlu'));
$link = (string) Settings::get('cookie.link', '') ?: '/gizlilik';
?>
<div class="cookie-banner" data-cookie-banner role="dialog" aria-live="polite" aria-label="<?= e($title) ?>" hidden>
    <div class="cookie-banner__body">
        <strong class="cookie-banner__title"><?= e($title) ?></strong>
        <p class="cookie-banner__text"><?= e($text) ?>
            <a href="<?= e(url($link)) ?>"><?= e(t('footer.privacy')) ?></a>
        </p>
    </div>
    <div class="cookie-banner__actions">
        <button type="button" class="btn btn-outline btn-sm" data-cookie-decline><?= e($decline) ?></button>
        <button type="button" class="btn btn-accent btn-sm" data-cookie-accept><?= e($accept) ?></button>
    </div>
</div>
