<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Security;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Translator;

/** XSS korumali cikti. */
function e(?string $value): string
{
    return Str::escapeHtml($value);
}

/** Ceviri: t('auth.login') */
function t(string $key, array $replace = []): string
{
    return Translator::get($key, $replace);
}

/**
 * Gorunumlerdeki sabit Turkce metni aktif dile cevirir: __('Kaydet')
 * Sozlukte yoksa metnin kendisi doner (tr'de her zaman kendisi).
 */
function __(string $text, array $replace = []): string
{
    return Translator::text($text, $replace);
}

/** Ayar okuma: setting('brand.title') */
function setting(string $key, mixed $default = null): mixed
{
    return Settings::get($key, $default);
}

/** Ayar degeri olarak gorsel/medya yolu -> tam URL. */
function media_url(?string $diskPath, ?string $fallback = null): string
{
    if ($diskPath === null || $diskPath === '') {
        return $fallback ?? '';
    }
    return storage_url($diskPath);
}

/** storage/ altindaki dosya icin tarayici URL'i (guvenli indirme ucu). */
function storage_url(string $diskPath): string
{
    return url('/media/' . implode('/', array_map('rawurlencode', explode('/', $diskPath))));
}

function asset(string $path): string
{
    $relative = ltrim($path, '/');
    // Dosya zamanı varsa onu kullan: CSS/JS güncellenince tarayıcı önbelleği
    // kendiliğinden tazelenir, config'deki sürümü elle artırmak gerekmez.
    $mtime = @filemtime(BASE_PATH . '/assets/' . $relative);
    $version = $mtime !== false ? (string) $mtime : (string) Config::get('app.asset_version', '1');
    return url('/assets/' . $relative) . '?v=' . $version;
}

function url(string $path = '/'): string
{
    $base = rtrim((string) Config::get('app.url', ''), '/');
    if ($base === '') {
        $base = '';
    }
    return $base . '/' . ltrim($path, '/');
}

function admin_url(string $path = ''): string
{
    return url('/yonetim/' . ltrim($path, '/'));
}

/** WhatsApp sohbet baglantisi; ayarlarda numara yoksa bos string. */
function whatsapp_url(string $message = ''): string
{
    $number = preg_replace('/[^0-9]/', '', trim((string) Settings::get('brand.whatsapp', ''))) ?? '';
    if ($number === '') {
        return '';
    }
    // wa.me uluslararası biçim ister: 00… / 0… ile başlayan yerel numaraları +90'a çevir.
    if (str_starts_with($number, '00')) {
        $number = substr($number, 2);
    } elseif (str_starts_with($number, '0')) {
        $number = '90' . substr($number, 1);
    }
    return 'https://wa.me/' . $number . ($message !== '' ? '?text=' . rawurlencode($message) : '');
}

function csrf_field(): string
{
    return Csrf::field();
}

function current_path(): string
{
    return (new Request())->path();
}

function is_active(string $prefix): string
{
    return str_starts_with(current_path(), $prefix) ? ' is-active' : '';
}

function user(): ?array
{
    return Auth::user();
}

/** Eski form degerini geri getir (dogrulama hatasindan sonra). */
function old(string $key, mixed $default = ''): mixed
{
    $bag = (array) App\Core\Session::get('_old', []);
    return $bag[$key] ?? $default;
}

function csrf_nonce(): string
{
    return Security::nonce();
}

/** JSON-LD / JS icin guvenli gomme. */
function json_attr(mixed $data): string
{
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
}


/** JSON etiket sözlüğünden geçerli dilde metin getirir: arac/evrak türü adları. */
function label(?string $labelsJson, ?string $locale = null): string
{
    if ($labelsJson === null || $labelsJson === '') {
        return '';
    }
    $labels = json_decode($labelsJson, true);
    if (!is_array($labels)) {
        return '';
    }
    $locale ??= Translator::locale();
    return (string) ($labels[$locale] ?? $labels['tr'] ?? $labels['en'] ?? reset($labels) ?: '');
}

/** Belge boyutunu okunabilir biçime çevirir. */
function format_bytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $index = 0;
    $value = (float) $bytes;
    while ($value >= 1024 && $index < count($units) - 1) {
        $value /= 1024;
        $index++;
    }
    return round($value, $index === 0 ? 0 : 1) . ' ' . $units[$index];
}

function format_date(?string $date, string $format = 'd.m.Y'): string
{
    if ($date === null || $date === '') {
        return '-';
    }
    $ts = strtotime($date);
    return $ts === false ? '-' : date($format, $ts);
}

/** Formdan gelen tarihi (Y-m-d) doğrular; geçersizse null döner. */
function db_date(mixed $value): ?string
{
    $value = trim((string) $value);
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) !== 1) {
        return null;
    }
    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $value : null;
}

/** "az önce", "5 dk önce", "3 sa önce", "2 gün önce" gibi kısa zaman ifadesi. */
function time_ago(?string $date): string
{
    if ($date === null || $date === '') {
        return '';
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return '';
    }
    $diff = time() - $ts;
    if ($diff < 0) {
        return format_date($date, 'd.m.Y H:i');
    }
    if ($diff < 60) {
        return 'az önce';
    }
    if ($diff < 3600) {
        return intdiv($diff, 60) . ' dk önce';
    }
    if ($diff < 86400) {
        return intdiv($diff, 3600) . ' sa önce';
    }
    if ($diff < 604800) {
        return intdiv($diff, 86400) . ' gün önce';
    }
    return format_date($date, 'd.m.Y');
}

function days_left(?string $date): ?int
{
    if ($date === null || $date === '') {
        return null;
    }
    $ts = strtotime(date('Y-m-d', strtotime($date)));
    if ($ts === false) {
        return null;
    }
    $today = strtotime(date('Y-m-d'));
    return (int) floor(($ts - $today) / 86400);
}

/** Kalan güne göre trafik ışığı seviyesi (uzun süreli işler için kısayol). */
function light_level(?int $days): string
{
    if ($days === null) {
        return 'none';
    }
    if ($days < 0) {
        return 'red';
    }
    if ($days < 7) {
        return 'orange';
    }
    if ($days < 30) {
        return 'yellow';
    }
    return 'green';
}

/** Trafik ışığı için rozet CSS sınıfı. */
function light_badge_class(string $light): string
{
    return match ($light) {
        'green'  => 'badge--ok',
        'yellow' => 'badge--warn',
        'orange' => 'badge--warn',
        'red'    => 'badge--danger',
        default  => 'badge--info',
    };
}

/** Trafik ışığı için renk değeri (nokta göstergesi). */
function light_color(string $light): string
{
    return match ($light) {
        'green'  => '#16A34A',
        'yellow' => '#F5A524',
        'orange' => '#F97316',
        'red'    => '#E5484D',
        default  => '#94A3B8',
    };
}

/** Trafik ışığı seviyesinin çevrilmiş etiketi. */
function light_label(string $light): string
{
    return t('vehicles.light_' . $light);
}

/** ICS/Google Takvim için tarih biçimi: YYYYMMDD. */
function ics_date(string $date): string
{
    $ts = strtotime($date);
    return $ts === false ? date('Ymd') : date('Ymd', $ts);
}

/** Google Takvim'e etkinlik ekleme bağlantısı (tüm gün etkinlik). */
function google_calendar_url(string $text, string $startDate, string $endDate, string $details = ''): string
{
    // Bitiş tarihi Google'da özel (hariç) olduğu için bir gün eklenir.
    $end = date('Ymd', strtotime($endDate . ' +1 day') ?: time());
    $params = [
        'action'  => 'TEMPLATE',
        'text'    => $text,
        'dates'   => ics_date($startDate) . '/' . $end,
        'details' => $details,
    ];
    return 'https://calendar.google.com/calendar/render?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function old_has_error(string $field): bool
{
    $bag = (array) App\Core\Session::get('_errors', []);
    return isset($bag[$field]);
}

function error_first(string $field): string
{
    $bag = (array) App\Core\Session::get('_errors', []);
    return (string) ($bag[$field][0] ?? '');
}
