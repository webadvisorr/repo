<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Guvenli dosya yukleme.
 * - Uzanti + MIME (finfo) + boyut kontrolu
 * - Yuklenen dosya web kokune degil storage/ altina yazilir (calistirilamaz)
 * - Rastgele dosya adi (uzanti korunur), orijinal ad veritabaninda saklanir
 */
final class Uploader
{
    public const EXTENSIONS = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'bmp', 'avif', 'apng'],
        'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'webp', 'heic'],
    ];

    private const BLOCKED = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pl', 'py', 'rb', 'cgi', 'sh', 'asp', 'aspx', 'jsp', 'exe', 'dll', 'so', 'bat', 'cmd', 'htaccess', 'html', 'htm', 'js', 'mjs'];

    private const MIME_MAP = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
        'gif' => ['image/gif'], 'webp' => ['image/webp'], 'svg' => ['image/svg+xml', 'text/plain', 'text/html', 'application/xml', 'text/xml'],
        'ico' => ['image/vnd.microsoft.icon', 'image/x-icon', 'image/x-ico', 'application/octet-stream'],
        'bmp' => ['image/bmp', 'image/x-ms-bmp'], 'avif' => ['image/avif'], 'apng' => ['image/apng', 'image/png'],
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'heic' => ['image/heic', 'image/heif'],
    ];

    /**
     * @param array{name:string,tmp_name:string,error:int,size:int} $file
     * @return array{ok:bool,error?:string,media?:array}
     */
    public static function store(array $file, string $kind, array $options = []): array
    {
        $maxBytes = (int) ($options['max_bytes'] ?? (int) Config::get('uploads.max_image_bytes', 8 * 1024 * 1024));
        $type = (string) ($options['type'] ?? 'image');
        $allowed = (array) ($options['extensions'] ?? self::EXTENSIONS[$type] ?? self::EXTENSIONS['image']);

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => t('upload.error_' . (int) $file['error'])];
        }
        if ((int) $file['size'] > $maxBytes) {
            return ['ok' => false, 'error' => t('upload.too_large', ['max' => round($maxBytes / 1048576, 1)])];
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => t('upload.invalid')];
        }

        $original = (string) $file['name'];
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($extension === '' || in_array($extension, self::BLOCKED, true)) {
            return ['ok' => false, 'error' => t('upload.extension_blocked')];
        }
        if (!in_array($extension, $allowed, true)) {
            return ['ok' => false, 'error' => t('upload.extension_not_allowed', ['ext' => $extension])];
        }

        // MIME kontrolu gercek icerige gore (finfo) - sahte uzantiya karsi koruma.
        $detected = self::detectMime($file['tmp_name'], $extension);
        $expected = self::MIME_MAP[$extension] ?? [];
        if ($expected !== [] && $detected !== null && !in_array($detected, $expected, true)) {
            // SVG metin tabanli oldugu icin ek kontrol: icerikte <svg olmali.
            if (!($extension === 'svg' && str_contains(strtolower((string) @file_get_contents($file['tmp_name'], false, null, 0, 2048)), '<svg'))) {
                return ['ok' => false, 'error' => t('upload.mime_mismatch')];
            }
        }

        // SVG icinde zararli kod olmamali.
        if ($extension === 'svg') {
            $raw = (string) file_get_contents($file['tmp_name']);
            if (preg_match('/<\s*script|on[a-z]+\s*=|javascript:|<\s*foreignObject|<!ENTITY/i', $raw)) {
                return ['ok' => false, 'error' => t('upload.svg_unsafe')];
            }
        }

        $relativeDir = 'uploads/' . $kind . '/' . date('Y/m');
        $absoluteDir = BASE_PATH . '/storage/' . $relativeDir;
        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0775, true)) {
            return ['ok' => false, 'error' => t('upload.storage_failed')];
        }

        $filename = date('YmdHis') . '-' . Str::random(8) . '.' . $extension;
        $absolute = $absoluteDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $absolute)) {
            return ['ok' => false, 'error' => t('upload.move_failed')];
        }
        @chmod($absolute, 0644);

        $width = $height = null;
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true)) {
            $info = @getimagesize($absolute);
            if (is_array($info)) {
                $width = (int) $info[0];
                $height = (int) $info[1];
            }
        }

        $mediaId = Database::insert('media', [
            'kind'          => $kind,
            'disk_path'     => $relativeDir . '/' . $filename,
            'original_name' => Str::limit($original, 250, ''),
            'mime'          => $detected,
            'extension'     => $extension,
            'size_bytes'    => (int) $file['size'],
            'width'         => $width,
            'height'        => $height,
            'alt_text'      => $options['alt_text'] ?? null,
            'uploaded_by'   => Auth::id(),
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        Audit::log('media.upload', 'media', $mediaId, ['kind' => $kind, 'name' => $original]);

        return ['ok' => true, 'media' => [
            'id'     => $mediaId,
            'path'   => $relativeDir . '/' . $filename,
            'url'    => storage_url($relativeDir . '/' . $filename),
            'width'  => $width,
            'height' => $height,
            'mime'   => $detected,
        ]];
    }

    private static function detectMime(string $path, string $extension): ?string
    {
        if ($extension === 'svg' || $extension === 'ico') {
            return null; // finfo bu ikisinde guvenilir degil; icerik kontrolu yapiliyor.
        }
        if (!function_exists('finfo_open')) {
            return null;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }
        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);
        return is_string($mime) ? $mime : null;
    }

    public static function delete(int $mediaId): bool
    {
        $media = Database::first('SELECT * FROM media WHERE id = ?', [$mediaId]);
        if ($media === null) {
            return false;
        }
        $path = BASE_PATH . '/storage/' . (string) $media['disk_path'];
        if (is_file($path)) {
            @unlink($path);
        }
        Database::delete('media', 'id = ?', [$mediaId]);
        Audit::log('media.delete', 'media', $mediaId);
        return true;
    }

    public static function url(?string $diskPath): ?string
    {
        return $diskPath === null || $diskPath === '' ? null : storage_url($diskPath);
    }
}
