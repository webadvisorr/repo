<?php
use App\Core\Auth;
use App\Core\Settings;
if ((string) Settings::get('sticky_cta.enabled', '1') !== '1') {
    return;
}
if (Auth::check()) {
    return;
}
$path = current_path();
foreach (['/giris', '/kayit', '/tesekkurler', '/panel', '/sifremi-unuttum'] as $skip) {
    if (str_starts_with($path, $skip)) {
        return;
    }
}
$text = __((string) Settings::get('sticky_cta.text', __('Araç evraklarınızı ücretsiz takip edin.')));
$buttonText = __((string) Settings::get('sticky_cta.button_text', __('Ücretsiz başla')));
$buttonUrl = trim((string) Settings::get('sticky_cta.button_url', ''));
?>
<div class="sticky-cta" data-sticky-cta hidden>
    <span class="sticky-cta__text"><?= e($text) ?></span>
    <?php if ($buttonUrl !== ''): ?>
        <a class="btn btn-accent btn-sm" href="<?= e(url($buttonUrl)) ?>"><?= e($buttonText) ?></a>
    <?php else: ?>
        <button class="btn btn-accent btn-sm" type="button" data-open-drawer="register"><?= e($buttonText) ?></button>
    <?php endif; ?>
    <button type="button" class="sticky-cta__close" data-sticky-cta-close aria-label="<?= e(t('common.close')) ?>">&times;</button>
</div>
