<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Veritabanı güncellemelerini (migration) SSH'sız uygular.
 * app/Database/migrations/*.sql dosyaları sırayla çalıştırılır ve `migrations` tablosuna işlenir.
 * Zaten var olan kolon/tablo hataları tolere edilir (idempotent davranış).
 */
final class Migrator
{
    private const DIR = '/app/Database/migrations';

    /** @return array<int,string> */
    public static function all(): array
    {
        $files = glob(BASE_PATH . self::DIR . '/*.sql') ?: [];
        $names = array_map(static fn (string $f): string => basename($f), $files);
        sort($names, SORT_STRING);
        return $names;
    }

    /** @return array<int,string> */
    public static function applied(): array
    {
        try {
            return array_map(
                static fn (array $row): string => (string) $row['filename'],
                Database::select('SELECT filename FROM migrations ORDER BY filename ASC')
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int,string> */
    public static function pending(): array
    {
        return array_values(array_diff(self::all(), self::applied()));
    }

    public static function isUpToDate(): bool
    {
        return self::pending() === [];
    }

    /**
     * @return array{ok:bool,executed:array<int,string>,skipped:array<int,string>,errors:array<int,string>}
     */
    public static function migrate(): array
    {
        $result = ['ok' => true, 'executed' => [], 'skipped' => [], 'errors' => []];

        foreach (self::pending() as $filename) {
            $path = BASE_PATH . self::DIR . '/' . $filename;
            if (!is_file($path)) {
                continue;
            }
            $sql = (string) file_get_contents($path);
            $statements = self::split($sql);
            $ok = true;

            foreach ($statements as $statement) {
                try {
                    Database::connect()->exec($statement);
                } catch (\PDOException $e) {
                    $message = $e->getMessage();
                    // Zaten uygulanmis degisiklikler: sessizce atla.
                    if (preg_match('/Duplicate column name|Duplicate key name|already exists|Duplicate entry/i', $message)) {
                        $result['skipped'][] = $filename . ': ' . Str::limit($message, 120, '');
                        continue;
                    }
                    $ok = false;
                    $result['ok'] = false;
                    $result['errors'][] = $filename . ': ' . Str::limit($message, 220, '');
                    Logger::error('Migration hatasi', ['file' => $filename, 'error' => $message]);
                    break;
                }
            }

            if ($ok) {
                Database::insert('migrations', [
                    'filename'   => $filename,
                    'applied_at' => date('Y-m-d H:i:s'),
                ]);
                $result['executed'][] = $filename;
                Audit::log('system.migration', 'migration', null, ['filename' => $filename]);
            }
        }

        return $result;
    }

    /** Yeni kurulumlarda şema zaten güncel olduğu için migration'ları "uygulandı" olarak işaretler. */
    public static function markAllApplied(): int
    {
        $count = 0;
        foreach (self::all() as $filename) {
            if (Database::scalar('SELECT id FROM migrations WHERE filename = ? LIMIT 1', [$filename])) {
                continue;
            }
            Database::insert('migrations', ['filename' => $filename, 'applied_at' => date('Y-m-d H:i:s')]);
            $count++;
        }
        return $count;
    }

    /** @return array<int,string> çalıştırılacak SQL ifadeleri */
    private static function split(string $sql): array
    {
        // Yorum satırlarını temizle
        $lines = preg_split('/\r\n|\r|\n/', $sql) ?: [];
        $clean = [];
        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
                continue;
            }
            $clean[] = $line;
        }
        $statements = [];
        foreach (explode(';', implode("\n", $clean)) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $statements[] = $statement;
            }
        }
        return $statements;
    }
}
