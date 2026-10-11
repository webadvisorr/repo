<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Dosya tabanli oran sinirlama (rate limiting).
 * Paylasimli hostingde ek tablo/servis gerektirmez, ek dosya kilidi ile calisir.
 */
final class RateLimiter
{
    public static function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        [$hits, $resetAt] = self::read($key);
        if ($resetAt !== null && $resetAt < time()) {
            self::clear($key);
            return false;
        }
        return count($hits) >= $maxAttempts;
    }

    public static function hit(string $key, int $decaySeconds): void
    {
        $path = self::path($key);
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            return;
        }
        flock($handle, LOCK_EX);
        $raw = stream_get_contents($handle);
        $data = json_decode((string) $raw, true);
        $now = time();
        if (!is_array($data) || ($data['reset_at'] ?? 0) < $now) {
            $data = ['reset_at' => $now + $decaySeconds, 'hits' => []];
        }
        $data['hits'][] = $now;
        $data['hits'] = array_slice($data['hits'], -500);
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string) json_encode($data));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    public static function attemptsLeft(string $key, int $maxAttempts, int $decaySeconds): int
    {
        if (self::tooManyAttempts($key, $maxAttempts, $decaySeconds)) {
            return 0;
        }
        [$hits] = self::read($key);
        return max(0, $maxAttempts - count($hits));
    }

    public static function availableIn(string $key): int
    {
        [, $resetAt] = self::read($key);
        return $resetAt === null ? 0 : max(0, $resetAt - time());
    }

    public static function clear(string $key): void
    {
        @unlink(self::path($key));
    }

    /** @return array{0:array<int,int>,1:?int} */
    private static function read(string $key): array
    {
        $path = self::path($key);
        if (!is_file($path)) {
            return [[], null];
        }
        $raw = @file_get_contents($path);
        $data = json_decode((string) $raw, true);
        if (!is_array($data) || ($data['reset_at'] ?? 0) < time()) {
            return [[], null];
        }
        return [(array) ($data['hits'] ?? []), (int) $data['reset_at']];
    }

    private static function path(string $key): string
    {
        $dir = BASE_PATH . '/storage/cache/rate';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/' . hash('sha256', $key) . '.json';
    }
}
