<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Database;

/**
 * storage/ altındaki dosyaları güvenli şekilde servis eder.
 * - Yol gezinmesi (path traversal) engellenir
 * - Çalıştırılabilir uzantılar asla servis edilmez
 * - Uzun önbellek + nosniff + SVG için sıkı CSP
 */
final class MediaController extends Controller
{
    private const ALLOWED = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'bmp', 'avif', 'apng', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'heic'];

    public function show(): void
    {
        $path = rawurldecode(ltrim(str_replace('/media/', '', $this->request->path()), '/'));

        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            $this->notFound();
        }
        if (!preg_match('#^[a-zA-Z0-9_\-/\.]+$#', $path)) {
            $this->notFound();
        }

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED, true)) {
            $this->notFound();
        }

        $storageRoot = realpath(BASE_PATH . '/storage');
        $absolute = realpath(BASE_PATH . '/storage/' . $path);
        if ($storageRoot === false || $absolute === false
            || !str_starts_with($absolute, $storageRoot . DIRECTORY_SEPARATOR)
            || !is_file($absolute)) {
            $this->notFound();
        }

        // Yalnizca panelden yuklenen (media kaydı olan) dosyalar servis edilir.
        $registered = Database::scalar('SELECT id FROM media WHERE disk_path = ? LIMIT 1', [$path]);
        if ($registered === null) {
            $this->notFound();
        }

        $mime = $this->mime($extension);
        $size = (int) (filesize($absolute) ?: 0);

        if (!headers_sent()) {
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . $size);
            header('X-Content-Type-Options: nosniff');
            header('Content-Disposition: inline; filename="' . basename($path) . '"');
            header('Cache-Control: public, max-age=604800, immutable');
            if ($extension === 'svg') {
                header('Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\'; img-src data:');
            }
        }

        readfile($absolute);
    }

    private function mime(string $extension): string
    {
        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'ico' => 'image/vnd.microsoft.icon',
            'bmp' => 'image/bmp',
            'avif' => 'image/avif',
            'apng' => 'image/apng',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'heic' => 'image/heic',
            default => 'application/octet-stream',
        };
    }

    private function notFound(): never
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo '404';
        exit;
    }
}
