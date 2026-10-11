<?php
declare(strict_types=1);

namespace App\Core;

/** Basit PHP sablon motoru (harici bagimlilik yok). */
final class View
{
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function render(string $template, array $data = [], ?string $layout = 'layouts/site'): string
    {
        $content = self::renderPartial($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::renderPartial($layout, array_merge($data, ['content' => $content]));
    }

    public static function renderPartial(string $template, array $data = []): string
    {
        $path = self::resolve($template);
        if ($path === null) {
            Logger::error('Sablon bulunamadi: ' . $template);
            return '';
        }
        $data = array_merge(self::$shared, $data);
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $path;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    public static function exists(string $template): bool
    {
        return self::resolve($template) !== null;
    }

    private static function resolve(string $template): ?string
    {
        if (str_contains($template, '..') || str_starts_with($template, '/')) {
            throw new \InvalidArgumentException('Gecersiz sablon yolu.');
        }
        $path = BASE_PATH . '/app/Views/' . $template . '.php';
        return is_file($path) ? $path : null;
    }
}
