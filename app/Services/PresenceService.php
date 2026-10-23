<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Request;

/**
 * Canlı ziyaretçi sayacı.
 *
 * Site sayfalarına gelen her istek `visitor_sessions` tablosunda oturum bazında
 * güncellenir. Yönetim panelindeki "Şu an sitede" kutusu son 5 dakikada sayfa
 * görüntüleyen tekil oturumları sayar.
 */
final class PresenceService
{
    public const WINDOW_MINUTES = 5;

    private static ?bool $available = null;

    public static function available(): bool
    {
        if (self::$available !== null) {
            return self::$available;
        }
        try {
            Database::scalar('SELECT id FROM visitor_sessions LIMIT 1');
            self::$available = true;
        } catch (\Throwable) {
            self::$available = false;
        }
        return self::$available;
    }

    /** Sayfa görüntülemesini kaydeder (varlık sorgusu + upsert). */
    public static function touch(Request $request): void
    {
        if (!self::available() || $request->method() !== 'GET') {
            return;
        }
        $path = $request->path();
        foreach (['/assets', '/media', '/mail-piksel', '/cron-hatirlatma', '/abonelikten-cikar', '/robots.txt', '/sitemap.xml', '/favicon'] as $skip) {
            if (str_starts_with($path, $skip)) {
                return;
            }
        }

        $sessionId = session_id();
        if ($sessionId === '') {
            return;
        }
        $key = substr(hash('sha256', $sessionId . '|' . (string) Config::get('app.key', 'evrako')), 0, 40);
        $now = date('Y-m-d H:i:s');

        try {
            Database::run(
                'INSERT INTO visitor_sessions (visitor_key, user_id, ip, user_agent, path, first_seen, last_seen)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), ip = VALUES(ip),
                   user_agent = VALUES(user_agent), path = VALUES(path), last_seen = VALUES(last_seen)',
                [
                    $key,
                    Auth::id(),
                    mb_substr($request->ip(), 0, 45),
                    mb_substr($request->userAgent(), 0, 255),
                    mb_substr($path, 0, 255),
                    $now,
                    $now,
                ]
            );
            if (random_int(1, 50) === 1) {
                Database::run('DELETE FROM visitor_sessions WHERE last_seen < DATE_SUB(NOW(), INTERVAL 2 DAY)');
            }
        } catch (\Throwable) {
            // Ziyaretçi sayacı asla sayfayı engellemez.
        }
    }

    public static function onlineCount(int $minutes = self::WINDOW_MINUTES): int
    {
        if (!self::available()) {
            return 0;
        }
        try {
            return (int) Database::scalar(
                'SELECT COUNT(*) FROM visitor_sessions WHERE last_seen >= DATE_SUB(NOW(), INTERVAL ? MINUTE)',
                [max(1, $minutes)]
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Son N dakikadaki tekil ziyaretçiler (giriş yapanlar adıyla). @return array<int,array> */
    public static function onlineVisitors(int $minutes = self::WINDOW_MINUTES, int $limit = 25): array
    {
        if (!self::available()) {
            return [];
        }
        try {
            return Database::select(
                'SELECT v.visitor_key, v.path, v.last_seen, v.user_id, u.full_name, u.email, u.role
                 FROM visitor_sessions v
                 LEFT JOIN users u ON u.id = v.user_id
                 WHERE v.last_seen >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
                 ORDER BY v.last_seen DESC LIMIT ' . max(1, min(100, $limit)),
                [max(1, $minutes)]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /** Bugün siteye gelen tekil ziyaretçi. */
    public static function todayCount(): int
    {
        if (!self::available()) {
            return 0;
        }
        try {
            return (int) Database::scalar('SELECT COUNT(*) FROM visitor_sessions WHERE first_seen >= CURDATE()');
        } catch (\Throwable) {
            return 0;
        }
    }
}
