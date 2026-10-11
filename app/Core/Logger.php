<?php
declare(strict_types=1);

namespace App\Core;

final class Logger
{
    private const LEVELS = ['debug', 'info', 'warning', 'error'];

    public static function debug(string $message, array $context = []): void  { self::write('debug', $message, $context); }
    public static function info(string $message, array $context = []): void   { self::write('info', $message, $context); }
    public static function warning(string $message, array $context = []): void { self::write('warning', $message, $context); }
    public static function error(string $message, array $context = []): void  { self::write('error', $message, $context); }

    public static function write(string $level, string $message, array $context = []): void
    {
        if (!in_array($level, self::LEVELS, true)) {
            $level = 'info';
        }
        $threshold = (string) Config::get('app.log_level', 'warning');
        if (array_search($level, self::LEVELS, true) < array_search($threshold, self::LEVELS, true)) {
            return;
        }

        // Hassas veriyi loglamayalim.
        foreach (['password', 'password_confirmation', 'token', 'secret', 'api_key'] as $secret) {
            unset($context[$secret]);
        }

        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = sprintf(
            "[%s] %s: %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context === [] ? '' : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        @file_put_contents($dir . '/' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }
}
