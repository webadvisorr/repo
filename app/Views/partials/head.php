<?php
/**
 * <head> içeriği: SEO, OpenGraph, tema renkleri (panelden yönetilir), preloader, favicon, analytics.
 * Sayfalar şu değişkenleri geçerek meta alanlarını özelleştirebilir:
 *   $title, $metaDescription, $canonical, $robots, $ogImage, $ogType, $jsonLd
 */
use App\Core\Request;
use App\Core\Security;
use App\Core\Settings;
use App\Core\Translator;

$title = trim((string) ($title ?? ''));
$siteTitle = (string) Settings::get('brand.title', 'Evrako');
$pageTitle = $title !== '' ? __($title) : __((string) Settings::get('seo.meta_title', $siteTitle));
$metaDescription = trim(__((string) ($metaDescription ?? Settings::get('seo.meta_description', ''))));
$favicon = (string) Settings::get('brand.favicon', '');
$appleIcon = (string) Settings::get('brand.apple_icon', '');
$colors = (array) Settings::get('theme.colors', []);
$radius = (int) Settings::get('theme.radius', 14);
$defaultMode = (string) Settings::get('theme.default_mode', 'system');
$ogTitle = (string) Settings::get('seo.og_title', '') ?: $pageTitle;
$ogDesc = (string) Settings::get('seo.og_description', '') ?: $metaDescription;

// --- Kanonik adres: sayfa bazlı > (yalnızca ana sayfada) panel ayarı > geçerli adres ---
$request = new Request();
$currentUrl = $request->fullUrl();
$canonicalInput = trim((string) ($canonical ?? ''));
if ($canonicalInput !== '') {
    $canonicalUrl = $canonicalInput;
} elseif ($request->path() === '/' && (string) Settings::get('seo.canonical', '') !== '') {
    $canonicalUrl = (string) Settings::get('seo.canonical', '');
} else {
    $canonicalUrl = $currentUrl;
}

// --- OpenGraph görseli: sayfa bazlı > panel ayarı > markalı varsayılan ---
$ogImageSetting = trim((string) ($ogImage ?? Settings::get('brand.og_image', '')));
$ogImageUrl = $ogImageSetting !== '' ? media_url($ogImageSetting) : url('/assets/brand/og-default.svg');

$robotsValue = trim((string) ($robots ?? '')) ?: (string) Settings::get('seo.robots', 'index,follow');
$ogTypeValue = trim((string) ($ogType ?? '')) ?: (string) Settings::get('seo.og_type', 'website');

// --- Çok dillilik: hreflang alternatifleri ---
$queryString = (string) (parse_url($currentUrl, PHP_URL_QUERY) ?? '');
$baseUrl = strtok($currentUrl, '?') ?: $currentUrl;
parse_str($queryString, $queryParams);
$alternates = [];
foreach (Translator::SUPPORTED as $code) {
    $params = $queryParams;
    $params['dil'] = $code;
    $alternates[$code] = $baseUrl . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

// --- Analitik (çerez onayına saygılı) ---
$analyticsEnabled = (string) Settings::get('analytics.enabled', '0') === '1';
$analytics = [
    'provider'        => (string) Settings::get('analytics.provider', 'ga4'),
    'gaId'            => (string) Settings::get('analytics.ga_id', ''),
    'domain'          => (string) Settings::get('analytics.plausible_domain', ''),
    'custom'          => (string) Settings::get('analytics.custom_script', ''),
    'consentRequired' => (string) Settings::get('analytics.consent_required', '1') === '1',
    'nonce'           => Security::nonce(),
];
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?></title>
<?php if ($metaDescription !== ''): ?>
<meta name="description" content="<?= e($metaDescription) ?>">
<?php endif; ?>
<meta name="keywords" content="<?= e((string) Settings::get('seo.meta_keywords', '')) ?>">
<meta name="robots" content="<?= e($robotsValue) ?>">
<link rel="canonical" href="<?= e($canonicalUrl) ?>">
<?php foreach ($alternates as $code => $alternate): ?>
<link rel="alternate" hreflang="<?= e($code) ?>" href="<?= e($alternate) ?>">
<?php endforeach; ?>
<link rel="alternate" hreflang="x-default" href="<?= e($currentUrl) ?>">

<meta property="og:type" content="<?= e($ogTypeValue) ?>">
<meta property="og:site_name" content="<?= e($siteTitle) ?>">
<meta property="og:title" content="<?= e($ogTitle) ?>">
<?php if ($ogDesc !== ''): ?>
<meta property="og:description" content="<?= e($ogDesc) ?>">
<?php endif; ?>
<meta property="og:url" content="<?= e($currentUrl) ?>">
<meta property="og:locale" content="<?= e(str_replace('-', '_', Translator::locale())) ?>">
<meta property="og:image" content="<?= e($ogImageUrl) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="<?= e($siteTitle) ?>">
<meta name="twitter:card" content="<?= e((string) Settings::get('seo.twitter_card', 'summary_large_image')) ?>">
<meta name="twitter:title" content="<?= e($ogTitle) ?>">
<?php if ($ogDesc !== ''): ?>
<meta name="twitter:description" content="<?= e($ogDesc) ?>">
<?php endif; ?>
<meta name="twitter:image" content="<?= e($ogImageUrl) ?>">

<link rel="icon" href="<?= e($favicon !== '' ? media_url($favicon) : asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e($appleIcon !== '' ? media_url($appleIcon) : asset('img/favicon.svg')) ?>">
<meta name="theme-color" content="<?= e((string) ($colors['light']['primary'] ?? '#0A1D3A')) ?>" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="<?= e((string) ($colors['dark']['primary'] ?? '#12325C')) ?>" media="(prefers-color-scheme: dark)">
<meta name="csrf-token" content="<?= e(App\Core\Csrf::token()) ?>">
<meta name="color-scheme" content="light dark">
<?php if ($analyticsEnabled): ?>
<meta name="evrako-analytics" content="1">
<?php endif; ?>

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

<?php if (!empty($jsonLd)): ?>
<?php foreach ((array) $jsonLd as $schema): ?>
<script type="application/ld+json" nonce="<?= e(App\Core\Security::nonce()) ?>"><?= json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php endforeach; ?>
<?php endif; ?>

<?php if ($analyticsEnabled): ?>
<script type="application/json" id="evrako-analytics"><?= json_encode($analytics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php endif; ?>

<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<?php if (Translator::isRtl()): ?>
<link rel="stylesheet" href="<?= e(asset('css/rtl.css')) ?>">
<?php endif; ?>
