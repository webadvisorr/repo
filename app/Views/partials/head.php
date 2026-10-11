<?php
/** <head> içeriği: SEO, OpenGraph, tema renkleri (panelden yönetilir), preloader, favicon. */
use App\Core\Settings;
use App\Core\Translator;

$title = trim((string) ($title ?? ''));
$siteTitle = (string) Settings::get('brand.title', 'evrako');
$pageTitle = $title !== '' ? $title : (string) Settings::get('seo.meta_title', $siteTitle);
$metaDescription = trim((string) ($metaDescription ?? Settings::get('seo.meta_description', '')));
$favicon = (string) Settings::get('brand.favicon', '');
$appleIcon = (string) Settings::get('brand.apple_icon', '');
$ogImage = (string) Settings::get('brand.og_image', '');
$colors = (array) Settings::get('theme.colors', []);
$radius = (int) Settings::get('theme.radius', 14);
$defaultMode = (string) Settings::get('theme.default_mode', 'system');
$preloaderEnabled = (string) Settings::get('preloader.enabled', '1') === '1';
$preloaderType = (string) Settings::get('preloader.type', 'image');
$preloaderMedia = (string) Settings::get('preloader.media', '');
$ogTitle = (string) Settings::get('seo.og_title', '') ?: $pageTitle;
$ogDesc = (string) Settings::get('seo.og_description', '') ?: $metaDescription;
$canonical = (string) Settings::get('seo.canonical', '') ?: (new App\Core\Request())->fullUrl();
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?></title>
<?php if ($metaDescription !== ''): ?>
<meta name="description" content="<?= e($metaDescription) ?>">
<?php endif; ?>
<meta name="keywords" content="<?= e((string) Settings::get('seo.meta_keywords', '')) ?>">
<meta name="robots" content="<?= e((string) Settings::get('seo.robots', 'index,follow')) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">

<meta property="og:type" content="<?= e((string) Settings::get('seo.og_type', 'website')) ?>">
<meta property="og:site_name" content="<?= e($siteTitle) ?>">
<meta property="og:title" content="<?= e($ogTitle) ?>">
<?php if ($ogDesc !== ''): ?>
<meta property="og:description" content="<?= e($ogDesc) ?>">
<?php endif; ?>
<meta property="og:url" content="<?= e((new App\Core\Request())->fullUrl()) ?>">
<?php if ($ogImage !== ''): ?>
<meta property="og:image" content="<?= e(media_url($ogImage)) ?>">
<?php endif; ?>
<meta name="twitter:card" content="<?= e((string) Settings::get('seo.twitter_card', 'summary_large_image')) ?>">
<meta name="twitter:title" content="<?= e($ogTitle) ?>">
<?php if ($ogDesc !== ''): ?>
<meta name="twitter:description" content="<?= e($ogDesc) ?>">
<?php endif; ?>
<?php if ($ogImage !== ''): ?>
<meta name="twitter:image" content="<?= e(media_url($ogImage)) ?>">
<?php endif; ?>

<link rel="icon" href="<?= e($favicon !== '' ? media_url($favicon) : asset('img/favicon.svg')) ?>" type="image/svg+xml">
<?php if ($appleIcon !== ''): ?>
<link rel="apple-touch-icon" href="<?= e(media_url($appleIcon)) ?>">
<?php endif; ?>
<meta name="theme-color" content="<?= e((string) ($colors['light']['primary'] ?? '#0A1D3A')) ?>" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="<?= e((string) ($colors['dark']['primary'] ?? '#12325C')) ?>" media="(prefers-color-scheme: dark)">
<meta name="csrf-token" content="<?= e(App\Core\Csrf::token()) ?>">
<meta name="color-scheme" content="light dark">

<?php /* Panelden seçilen renkler ve tema değişkenleri */ ?>
<style nonce="<?= e(App\Core\Security::nonce()) ?>">
:root{
<?php foreach (($colors['light'] ?? []) as $name => $value): ?>
    --c-<?= e(str_replace('_', '-', (string) $name)) ?>: <?= e((string) $value) ?>;
<?php endforeach; ?>
    --radius: <?= $radius ?>px;
}
[data-theme="dark"]{
<?php foreach (($colors['dark'] ?? []) as $name => $value): ?>
    --c-<?= e(str_replace('_', '-', (string) $name)) ?>: <?= e((string) $value) ?>;
<?php endforeach; ?>
}
#preloader{--preloader-bg: <?= e((string) Settings::get('preloader.background', '#0A1D3A')) ?>; --preloader-spinner: <?= e((string) Settings::get('preloader.spinner_color', '#00C48C')) ?>;}
[data-theme="dark"] #preloader{--preloader-bg: <?= e((string) Settings::get('preloader.background_dark', '#081426')) ?>;}
</style>
<?php /* Tema, sayfa boyanmadan önce uygulanır (yanıp sönme olmaz) */ ?>
<script nonce="<?= e(App\Core\Security::nonce()) ?>">
(function(){try{var m=localStorage.getItem('evrako-theme')||<?= json_attr($defaultMode) ?>;
if(m==='system'){m=window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';}
document.documentElement.setAttribute('data-theme',m);document.documentElement.setAttribute('data-theme-default',<?= json_attr($defaultMode) ?>);
}catch(e){}})();
</script>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<?php if (Translator::isRtl()): ?>
<link rel="stylesheet" href="<?= e(asset('css/rtl.css')) ?>">
<?php endif; ?>
