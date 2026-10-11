<?php
declare(strict_types=1);

namespace App\Core;

/** Veritabaninda tutulan site/panel ayarlari (logo, favicon, renk, meta, preloader ...). */
final class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        self::$cache = [];
        try {
            foreach (Database::select('SELECT `key`, `value`, is_json FROM settings') as $row) {
                self::$cache[(string) $row['key']] = (int) $row['is_json'] === 1
                    ? (json_decode((string) $row['value'], true) ?? [])
                    : (string) $row['value'];
            }
        } catch (\Throwable $e) {
            Logger::warning('Ayarlar okunamadi: ' . $e->getMessage());
            self::$cache = [];
        }
        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function group(string $group): array
    {
        $out = [];
        try {
            foreach (Database::select('SELECT `key`, `value`, is_json FROM settings WHERE `group` = ?', [$group]) as $row) {
                $key = (string) $row['key'];
                $out[$key] = (int) $row['is_json'] === 1 ? (json_decode((string) $row['value'], true) ?? []) : (string) $row['value'];
            }
        } catch (\Throwable) {
        }
        return $out;
    }

    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
        $isJson = is_array($value) || is_object($value);
        $payload = $isJson ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;

        $exists = Database::scalar('SELECT id FROM settings WHERE `key` = ? LIMIT 1', [$key]);
        if ($exists) {
            Database::run(
                'UPDATE settings SET `value` = ?, is_json = ?, `group` = ?, updated_by = ?, updated_at = ? WHERE `key` = ?',
                [$payload, $isJson ? 1 : 0, $group, Auth::id(), date('Y-m-d H:i:s'), $key]
            );
        } else {
            Database::insert('settings', [
                'group'      => $group,
                'key'        => $key,
                'value'      => $payload,
                'is_json'    => $isJson ? 1 : 0,
                'updated_by' => Auth::id(),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        self::$cache = null;
    }

    public static function forget(string $key): void
    {
        Database::delete('settings', '`key` = ?', [$key]);
        self::$cache = null;
    }

    /** Varsayilan ayar setini yazar (kurulum ve "varsayilanlara don" icin). */
    public static function seedDefaults(): void
    {
        $defaults = require BASE_PATH . '/app/Database/default_settings.php';
        foreach ($defaults as $key => $definition) {
            if (Database::scalar('SELECT id FROM settings WHERE `key` = ? LIMIT 1', [$key])) {
                continue;
            }
            $value = $definition['value'];
            $isJson = is_array($value) ? 1 : 0;
            Database::insert('settings', [
                'group'      => $definition['group'] ?? 'general',
                'key'        => $key,
                'value'      => $isJson === 1 ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value,
                'is_json'    => $isJson,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        self::$cache = null;
    }
}
