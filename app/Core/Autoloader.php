<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Composer'sız PSR-4 benzeri otomatik yükleyici.
 * App\Core\Router  =>  app/Core/Router.php
 */
final class Autoloader
{
    private static array $prefixes = [];

    public static function register(): void
    {
        self::addNamespace('App\\', BASE_PATH . '/app/');
        spl_autoload_register([self::class, 'load']);
    }

    public static function addNamespace(string $prefix, string $dir): void
    {
        self::$prefixes[$prefix] = rtrim($dir, '/') . '/';
    }

    public static function load(string $class): void
    {
        foreach (self::$prefixes as $prefix => $dir) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }
            $relative = substr($class, strlen($prefix));
            $file = $dir . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require $file;
                return;
            }
        }
    }
}
