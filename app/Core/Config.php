<?php
declare(strict_types=1);

namespace App\Core;

/** config/config.php içeriğini nokta (dot) notasyonu ile okur. */
final class Config
{
    private static array $items = [];

    public static function load(array $items): void
    {
        self::$items = $items;
    }

    public static function all(): array
    {
        return self::$items;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $value = self::$items;
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        $ref = &self::$items;
        foreach ($parts as $part) {
            if (!isset($ref[$part]) || !is_array($ref[$part])) {
                $ref[$part] = [];
            }
            $ref = &$ref[$part];
        }
        $ref = $value;
    }

    public static function installed(): bool
    {
        return is_file(BASE_PATH . '/config/config.php') && trim((string)self::get('app.key', '')) !== '';
    }
}
