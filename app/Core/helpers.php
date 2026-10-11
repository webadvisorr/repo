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
    return url('/assets/' . ltrim($path, '/')) . '?v=' . Config::get('app.asset_version', '1');
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
