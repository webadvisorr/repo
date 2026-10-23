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
    /** Arayüz sabit metinleri: locale => (Türkçe metin => çeviri). */
    private static array $uiMaps = [];
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

    /**
     * Ziyaretcinin/kullanicinin dilini belirler.
     * Sirasi: cerez (kullanicinin acik secimi) > hesap tercihi > tarayici > varsayilan.
     * Cerez once gelir; aksi halde giris yapan kullanici dil degistirdiginde secim geri donerdi.
     */
    public static function detect(): string
    {
        $cookie = (new Request())->cookie('evrako_locale');
        if ($cookie !== null && in_array($cookie, self::SUPPORTED, true)) {
            return $cookie;
        }
        $user = Auth::user();
        if ($user !== null && in_array((string) $user['locale'], self::SUPPORTED, true)) {
            return (string) $user['locale'];
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

    /**
     * Görünümlerdeki sabit Türkçe metinleri geçerli dile çevirir.
     * Kaynak metin sözlükte yoksa İngilizce haritaya, o da yoksa metnin kendisine düşer.
     */
    public static function text(string $text, array $replace = []): string
    {
        if (self::$locale === 'tr' || $text === '') {
            return self::applyReplace($text, $replace);
        }
        $value = self::uiLookup(self::$locale, $text);
        if ($value === null && self::$locale !== 'en') {
            $value = self::uiLookup('en', $text);
        }
        return self::applyReplace($value ?? $text, $replace);
    }

    private static function applyReplace(string $value, array $replace): string
    {
        foreach ($replace as $search => $replacement) {
            $value = str_replace(':' . $search, (string) $replacement, $value);
            $value = str_replace('{' . $search . '}', (string) $replacement, $value);
        }
        return $value;
    }

    private static function uiLookup(string $locale, string $text): ?string
    {
        if (!array_key_exists($locale, self::$uiMaps)) {
            $file = BASE_PATH . '/lang/ui/' . $locale . '.php';
            self::$uiMaps[$locale] = is_file($file) ? (array) require $file : [];
        }
        $value = self::$uiMaps[$locale][$text] ?? null;
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * Son çare: hazır HTML içindeki sözlükte tanımlı Türkçe metinleri çevirir.
     * Yeni yazılan ekranlar `__()` kullanır; bu geçiş yalnızca panel gibi eski
     * (sabit metinli) ekranlarda kalan artıkları kapatmak için çalışır.
     * <script>/<style> bloklarına ve 6 karakterden kısa sözlüklere dokunmaz.
     */
    public static function translateDocument(string $html): string
    {
        if (self::$locale === 'tr' || $html === '') {
            return $html;
        }
        $map = self::uiMap(self::$locale);
        if ($map === []) {
            return $html;
        }
        $pairs = [];
        foreach ($map as $source => $target) {
            $source = (string) $source;
            if (mb_strlen($source) < 6 || !is_string($target) || $target === '' || $target === $source) {
                continue;
            }
            $pairs[$source] = $target;
        }
        if ($pairs === []) {
            return $html;
        }
        uksort($pairs, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        $segments = preg_split('~(<script\b.*?</script>|<style\b.*?</style>)~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($segments)) {
            return $html;
        }
        foreach ($segments as $index => $segment) {
            if ($index % 2 === 1) {
                continue; // script/style bloğu
            }
            foreach ($pairs as $source => $target) {
                $segment = preg_replace(
                    '~(?<![\p{L}\p{N}])' . preg_quote($source, '~') . '(?![\p{L}\p{N}])~u',
                    str_replace(['\\', '$'], ['\\\\', '\\$'], $target),
                    $segment
                ) ?? $segment;
            }
            $segments[$index] = $segment;
        }
        return implode('', $segments);
    }

    private static function uiMap(string $locale): array
    {
        if (!array_key_exists($locale, self::$uiMaps)) {
            $file = BASE_PATH . '/lang/ui/' . $locale . '.php';
            self::$uiMaps[$locale] = is_file($file) ? (array) require $file : [];
        }
        return self::$uiMaps[$locale];
    }

    /** Arayuzde gosterilecek dil listesi. */
    public static function options(): array
    {
        return self::NAMES;
    }
}
