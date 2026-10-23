<?php
/** Logo (açık/koyu) yoksa SVG varsayılana düşer. */
use App\Core\Settings;
$logoLight = (string) Settings::get('brand.logo_light', '');
$logoDark = (string) Settings::get('brand.logo_dark', '');
$siteTitle = (string) Settings::get('brand.title', 'Evrako');
?>
<?php if ($logoLight !== '' || $logoDark !== ''): ?>
    <?php if ($logoLight !== '' && $logoDark !== ''): ?>
        <img src="<?= e(media_url($logoLight)) ?>" alt="<?= e($siteTitle) ?>" class="brand__logo brand__logo--light">
        <img src="<?= e(media_url($logoDark)) ?>" alt="<?= e($siteTitle) ?>" class="brand__logo brand__logo--dark">
    <?php else: ?>
        <img src="<?= e(media_url($logoLight !== '' ? $logoLight : $logoDark)) ?>" alt="<?= e($siteTitle) ?>">
    <?php endif; ?>
<?php else: ?>
    <svg width="38" height="38" viewBox="0 0 64 64" aria-hidden="true">
        <rect width="64" height="64" rx="16" fill="#0A1D3A"/>
        <rect x="10" y="18" width="44" height="28" rx="7" fill="#fff"/>
        <path d="M20 32.5 L26.5 39 L44 23" fill="none" stroke="#00C48C" stroke-width="5.2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <span class="brand__text"><?= e($siteTitle) ?></span>
<?php endif; ?>
