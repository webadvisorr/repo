<?php
declare(strict_types=1);

namespace App\Core;

final class Str
{
    public static function random(int $bytes = 16): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function slug(string $text): string
    {
        $tr = ['ş' => 's', 'Ş' => 's', 'ı' => 'i', 'İ' => 'i', 'ğ' => 'g', 'Ğ' => 'g',
               'ü' => 'u', 'Ü' => 'u', 'ö' => 'o', 'Ö' => 'o', 'ç' => 'c', 'Ç' => 'c'];
        $text = strtr($text, $tr);
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text) ?: $text;
        $text = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $text) ?? '');
        return trim($text, '-');
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** XSS korumasi: HTML'e guvenli gomme. */
    public static function escapeHtml(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function limit(?string $text, int $length, string $end = '…'): string
    {
        $text = (string) $text;
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length) . $end;
    }

    public static function mask(string $value, int $keep = 4): string
    {
        $len = strlen($value);
        if ($len <= $keep) {
            return str_repeat('*', $len);
        }
        return str_repeat('*', $len - $keep) . substr($value, -$keep);
    }
}
