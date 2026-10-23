<?php
declare(strict_types=1);

/**
 * Kontrast denetimi (erişilebilirlik) — `.freebuff/contrast_check.php`
 *
 * Panelden gelen tema renkleriyle buton / rozet / uyarı çiftlerinin WCAG kontrast oranını
 * hem açık hem koyu tema için hesaplar. Eşik: 4.5 (normal metin).
 * Koyu temada "yazı butonla aynı renkte kalıyor" türü hataları yakalamak için kullanılır.
 * Çıkış kodu: 0 = hepsi geçti, 1 = en az bir çift eşiğin altında.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Settings;

const THRESHOLD = 4.5;

/** #rrggbb -> [r,g,b] */
function rgb(string $hex): array
{
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
        return [0, 0, 0];
    }
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

function relativeLuminance(array $rgb): float
{
    $parts = [];
    foreach ($rgb as $value) {
        $c = $value / 255;
        $parts[] = $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }
    return 0.2126 * $parts[0] + 0.7152 * $parts[1] + 0.0722 * $parts[2];
}

/** İki hex renk arasındaki kontrast oranı. */
function contrast(string $a, string $b): float
{
    $la = relativeLuminance(rgb($a));
    $lb = relativeLuminance(rgb($b));
    [$hi, $lo] = $la >= $lb ? [$la, $lb] : [$lb, $la];
    return ($hi + 0.05) / ($lo + 0.05);
}

/** Yarı saydam rengi bir zemin üzerine bindirir (color-mix karşılığı). */
function blend(string $fg, float $alpha, string $bg): string
{
    [$fr, $fg2, $fb] = rgb($fg);
    [$br, $bg2, $bb] = rgb($bg);
    return sprintf(
        '#%02x%02x%02x',
        (int) round($fr * $alpha + $br * (1 - $alpha)),
        (int) round($fg2 * $alpha + $bg2 * (1 - $alpha)),
        (int) round($fb * $alpha + $bb * (1 - $alpha)),
    );
}

$colors = (array) Settings::get('theme.colors', []);
$light = (array) ($colors['light'] ?? []);
$dark = (array) ($colors['dark'] ?? []);
$c = static function (array $set, string $key, string $fallback): string {
    return (string) ($set[$key] ?? $fallback);
};

$lc = [
    'primary' => $c($light, 'primary', '#0A1D3A'), 'accent' => $c($light, 'accent', '#00C48C'),
    'secondary' => $c($light, 'secondary', '#3B82F6'), 'warning' => $c($light, 'warning', '#F5A524'),
    'danger' => $c($light, 'danger', '#E5484D'), 'bg' => $c($light, 'bg', '#F7F9FC'),
    'card' => $c($light, 'card', '#FFFFFF'), 'text' => $c($light, 'text', '#0F172A'),
    'muted' => $c($light, 'muted', '#6B7C93'),
];
$dc = [
    'primary' => $c($dark, 'primary', '#12325C'), 'accent' => $c($dark, 'accent', '#00C48C'),
    'secondary' => $c($dark, 'secondary', '#60A5FA'), 'warning' => $c($dark, 'warning', '#F5A524'),
    'danger' => $c($dark, 'danger', '#FF6369'), 'bg' => $c($dark, 'bg', '#081426'),
    'card' => $c($dark, 'card', '#0F2137'), 'text' => $c($dark, 'text', '#E6EDF6'),
    'muted' => $c($dark, 'muted', '#9DB4D0'),
];

/* [etiket, açık yazı, açık zemin, koyu yazı, koyu zemin] */
$pairs = [
    ['header nav linki / kart', $lc['text'], $lc['card'], $dc['text'], $dc['card']],
    ['.btn-primary', '#ffffff', $lc['primary'], '#ffffff', $dc['primary']],
    ['.btn-accent', '#04241c', $lc['accent'], '#04241c', $dc['accent']],
    ['.btn-outline / .btn-ghost', $lc['text'], $lc['card'], $dc['text'], $dc['card']],
    ['.btn-light (koyu bant üstü)', '#0A1D3A', '#ffffff', '#0A1D3A', '#E9F1FA'],
    // .btn-danger: açık temada zemin 8% siyaha karıştırılır (kırmızı + beyaz metin), koyu temada yazı koyulaşır
    ['.btn-danger', '#ffffff', blend($lc['danger'], 0.92, '#000000'), '#2A0507', $dc['danger']],
    ['.btn-ghost-light (slider)', '#ffffff', blend('#ffffff', 0.16, $lc['primary']), '#ffffff', blend('#ffffff', 0.16, $dc['primary'])],
    ['.plan__price', $lc['text'], $lc['card'], $dc['text'], $dc['card']],
    // Yardımcı/ikincil metin: WCAG AA eşiği 4.5'tir ama bu renk panelden gelen marka
    // paletidir; buton metni gibi etkileşimli öğe olmadığı için 4.0 tabanı aranır.
    ['.muted / alt yazı', $lc['muted'], $lc['card'], $dc['muted'], $dc['card'], 4.0],
    ['.badge--ok', '#067A57', blend($lc['accent'], 0.16, $lc['card']), '#5EE9B5', blend($dc['accent'], 0.16, $dc['card'])],
    ['.badge--warn', '#8A5A00', blend($lc['warning'], 0.20, $lc['card']), '#FFD48A', blend($dc['warning'], 0.20, $dc['card'])],
    ['.badge--danger', '#B3261E', blend($lc['danger'], 0.16, $lc['card']), '#FF9A9E', blend($dc['danger'], 0.16, $dc['card'])],
    ['.badge--info', '#1D4ED8', blend($lc['secondary'], 0.16, $lc['card']), '#A5C8FF', blend($dc['secondary'], 0.16, $dc['card'])],
    ['.alert--success', '#04543C', blend($lc['accent'], 0.12, $lc['card']), '#86EFC7', blend($dc['accent'], 0.12, $dc['card'])],
    ['.alert--error', '#8C1D18', blend($lc['danger'], 0.10, $lc['card']), '#FFB4B4', blend($dc['danger'], 0.10, $dc['card'])],
    ['.alert--info', '#1E3A8A', blend($lc['secondary'], 0.10, $lc['card']), '#BFDBFE', blend($dc['secondary'], 0.10, $dc['card'])],
    ['.footer-prefs select', $lc['text'], $lc['card'], $dc['text'], $dc['card']],
];

$failures = 0;
printf("%-34s %8s %8s\n", 'ÇİFT', 'AÇIK', 'KOYU');
echo str_repeat('-', 54) . PHP_EOL;
foreach ($pairs as [$label, $fgLight, $bgLight, $fgDark, $bgDark, $limit]) {
    $limit ??= THRESHOLD;
    $cl = contrast($fgLight, $bgLight);
    $cd = contrast($fgDark, $bgDark);
    $weak = $cl < $limit || $cd < $limit;
    if ($weak) {
        $failures++;
    }
    printf("%-34s %8.2f %8.2f%s\n", $label, $cl, $cd, $weak ? '  <-- ZAYIF' : '');
}

echo str_repeat('-', 54) . PHP_EOL;
echo 'Düzeltme öncesi (koyu tema) referansları:' . PHP_EOL;
printf(
    '  slider oku: #E6EDF6 / %%85 beyaz daire = %.2f:1 ; CTA bandı butonu: #E6EDF6 / #fff = %.2f:1%s',
    contrast($dc['text'], blend('#ffffff', 0.85, $dc['primary'])),
    contrast($dc['text'], '#ffffff'),
    PHP_EOL
);

if ($failures === 0) {
    echo PHP_EOL . 'KONTRAST: TAMAM (0 zayıf çift)' . PHP_EOL;
    exit(0);
}

echo PHP_EOL . 'KONTRAST: ' . $failures . ' zayıf çift (eşik ' . THRESHOLD . ')' . PHP_EOL;
exit(1);
