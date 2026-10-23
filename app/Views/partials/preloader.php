<?php
use App\Core\Settings;
if ((string) Settings::get('preloader.enabled', '1') !== '1') {
    return;
}
$type = (string) Settings::get('preloader.type', 'image');
$media = (string) Settings::get('preloader.media', '');
$min = (string) Settings::get('preloader.min_seconds', '0.6');
$max = (string) Settings::get('preloader.max_seconds', '6');
$siteTitle = (string) Settings::get('brand.title', 'Evrako');
?>
<div id="preloader" data-min="<?= e($min) ?>" data-max="<?= e($max) ?>" role="status" aria-live="polite" aria-label="<?= e($siteTitle) ?>">
    <?php if ($type === 'image' && $media !== ''): ?>
        <img src="<?= e(media_url($media)) ?>" alt="<?= e($siteTitle) ?>">
    <?php else: ?>
        <div class="spinner"></div>
    <?php endif; ?>
</div>
