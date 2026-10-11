<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Guvenlik basliklari, girdi temizleme ve oran sinirlama yardimcilari.
 */
final class Security
{
    private static string $nonce = '';

    public static function nonce(): string
    {
        if (self::$nonce === '') {
            self::$nonce = base64_encode(random_bytes(12));
        }
        return self::$nonce;
    }

    /** Yanit basliklari: XSS, clickjacking, MIME sniffing, veri sizintisi korumalari. */
    public static function sendHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        $nonce = self::nonce();

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-XSS-Protection: 0'); // Modern tarayicilarda CSP tercih edilir.
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(self)');
        header('Cross-Origin-Opener-Policy: same-origin');
        header_remove('X-Powered-By');

        if ((new Request())->isSecure()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        $extraScript = (string) Config::get('security.csp_script_extra', '');
        $csp = "default-src 'self'; "
            . "base-uri 'self'; "
            . "form-action 'self'; "
            . "frame-ancestors 'self'; "
            . "object-src 'none'; "
            . "img-src 'self' data: https:; "
            . "font-src 'self' data:; "
            . "style-src 'self' 'unsafe-inline'; "
            . "script-src 'self' 'nonce-{$nonce}'" . ($extraScript !== '' ? ' ' . $extraScript : '') . '; '
            . "connect-src 'self'";
        header('Content-Security-Policy: ' . $csp);
    }

    /** Rich text (panelden girilen HTML) icin beyaz liste temizligi. */
    public static function cleanHtml(?string $html): string
    {
        $html = (string) $html;
        if ($html === '') {
            return '';
        }
        // Tehlikeli bloklari tamamen kaldir.
        $html = preg_replace('#<\s*(script|style|iframe|object|embed|form|meta|link)[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html) ?? '';
        $html = preg_replace('#<\s*(script|style|iframe|object|embed|form|meta|link|base)[^>]*/?>#is', '', $html) ?? '';
        // Olay ozelliklerini ve javascript: protokolunu temizle.
        $html = preg_replace('#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html) ?? '';
        $html = preg_replace('#(href|src)\s*=\s*("|\')?\s*javascript:[^"\'>]*#i', '$1="#"', $html) ?? '';
        $html = preg_replace('#(href|src)\s*=\s*("|\')?\s*data:text/html[^"\'>]*#i', '$1="#"', $html) ?? '';
        // Sadece izin verilen etiketleri koru.
        $allowed = '<p><br><b><strong><i><em><u><s><ul><ol><li><a><h1><h2><h3><h4><h5><h6>'
            . '<blockquote><code><pre><img><table><thead><tbody><tr><th><td><hr><span><div><small>';
        return strip_tags($html, $allowed);
    }

    /** Metni oldugu gibi guvenli sekilde cikarir (XSS korumasi). */
    public static function escape(?string $value): string
    {
        return Str::escapeHtml($value);
    }

    public static function hashIp(string $ip): string
    {
        return hash_hmac('sha256', $ip, (string) Config::get('app.key', 'evrako'));
    }
}
