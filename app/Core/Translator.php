<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 6 dilli sozluk: tr, en, ru, ar, fa, el (Yunanca).
 * RTL diller: ar, fa -> otomatik yon destegi.
 */
final class Translator
{
    public const SUPPORTED = ['tr', 'en', 'ru', 'ar', 'fa', 'el'];
    public const RTL = ['ar', 'fa'];
    public const NAMES = [
        'tr' => 'Türkçe',
        'en' => 'English',
        'ru' => 'Русский',
        'ar' => 'العربية',
        'fa' => 'فارسی',
        'el' => 'Ελληνικά',
    ];

    private static array $loaded = [];
    private static string $locale = 'tr';

    public static function setLocale(string $locale): void
    {
        self::$locale = in_array($locale, self::SUPPORTED, true) ? $locale : 'tr';
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function isRtl(?string $locale = null): bool
    {
        return in_array($locale ?? self::$locale, self::RTL, true);
    }

    public static function direction(): string
    {
        return self::isRtl() ? 'rtl' : 'ltr';
    }

    /** Ziyaretcinin/kullanicinin dilini belirler: kullanici > cerez > tarayici > varsayilan. */
    public static function detect(): string
    {
        $user = Auth::user();
        if ($user !== null && in_array((string) $user['locale'], self::SUPPORTED, true)) {
            return (string) $user['locale'];
        }
        $cookie = (new Request())->cookie('evrako_locale');
        if ($cookie !== null && in_array($cookie, self::SUPPORTED, true)) {
            return $cookie;
        }
        $header = (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        foreach (explode(',', $header) as $part) {
            $code = strtolower(substr(trim(explode(';', $part)[0]), 0, 2));
            if (in_array($code, self::SUPPORTED, true)) {
                return $code;
            }
        }
        return (string) Config::get('app.locale', 'tr');
    }

    public static function get(string $key, array $replace = []): string
    {
        $value = self::lookup(self::$locale, $key);
        if ($value === null && self::$locale !== 'tr') {
            $value = self::lookup('tr', $key);
        }
        $value ??= $key;

        foreach ($replace as $search => $replacement) {
            $value = str_replace(':' . $search, (string) $replacement, $value);
            $value = str_replace('{' . $search . '}', (string) $replacement, $value);
        }
        return $value;
    }

    private static function lookup(string $locale, string $key): ?string
    {
        if (!isset(self::$loaded[$locale])) {
            $file = BASE_PATH . '/lang/' . $locale . '.php';
            self::$loaded[$locale] = is_file($file) ? (array) require $file : [];
        }
        $segments = explode('.', $key);
        $value = self::$loaded[$locale];
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }
        return is_string($value) ? $value : null;
    }

    /** Arayuzde gosterilecek dil listesi. */
    public static function options(): array
    {
        return self::NAMES;
    }
}
