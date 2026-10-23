<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Database;
use App\Services\CampaignService;
use App\Services\ReminderService;

/**
 * E-posta açılma takibi: 1x1 saydam piksel.
 *
 * Adres imzalıdır (`ReminderService::openToken`), bu yüzden oturum gerekmez.
 * Açılma zamanı `notifications.opened_at` alanına yazılır (bildirim günlüğünde görünür).
 */
final class TrackingController extends Controller
{
    private const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function pixel(string $id, string $token = ''): string
    {
        $bytes = (string) base64_decode(self::PIXEL, true);

        header('Content-Type: image/gif');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Content-Length: ' . strlen($bytes));
        header('X-Robots-Tag: noindex, nofollow');

        $token = (string) preg_replace('/\.gif$/', '', $token);
        $notificationId = (int) $id;

        if ($notificationId > 0 && hash_equals(ReminderService::openToken($notificationId), $token)) {
            try {
                $marked = Database::run(
                    'UPDATE notifications SET opened_at = ? WHERE id = ? AND opened_at IS NULL',
                    [date('Y-m-d H:i:s'), $notificationId]
                );
                // İlk açılmada kampanya alıcı sayacı da güncellenir.
                if ($marked->rowCount() > 0) {
                    CampaignService::markOpened($notificationId);
                }
            } catch (\Throwable) {
                // Takip edilemediyse piksel yine de dönmeli.
            }
        }

        return $bytes;
    }
}
