<?php
declare(strict_types=1);

/**
 * Test yardımcısı: SMTP sink'in kaydettiği .eml dosyasından bir bölümü çözer.
 * Kullanım: php .freebuff/mail-part.php <dosya> [html|text|headers|raw]
 */

$file = (string) ($argv[1] ?? '');
$part = (string) ($argv[2] ?? 'html');
$raw = is_file($file) ? (string) file_get_contents($file) : '';

if ($raw === '') {
    fwrite(STDERR, "Dosya okunamadı: {$file}\n");
    exit(1);
}

if ($part === 'raw' || $part === 'headers') {
    $headers = $raw;
    if ($part === 'headers' && preg_match('/\r?\n\r?\n/', $raw, $m, PREG_OFFSET_CAPTURE)) {
        $headers = substr($raw, 0, (int) $m[0][1]);
    }
    echo $headers;
    exit(0);
}

$contentType = $part === 'text' ? 'text/plain' : 'text/html';
$pattern = '/Content-Type: ' . preg_quote($contentType, '/') . '[^\r\n]*\r?\n[^\r\n]*Content-Transfer-Encoding: base64\r?\n\r?\n(.*?)(?=\r?\n--)/s';

if (preg_match($pattern, $raw, $m)) {
    echo (string) base64_decode(preg_replace('/\s+/', '', $m[1]) ?? '', true);
    exit(0);
}

fwrite(STDERR, "Bölüm bulunamadı: {$contentType}\n");
exit(2);
