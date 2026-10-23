<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;

/**
 * Günlük zamanlanmış işler (cron).
 * Hem `cron/reminders.php` (CLI / paylaşımlı hosting cron) hem de paneldeki
 * "Şimdi çalıştır" düğmesi ve token'lı web cron ucu bu sınıfı kullanır.
 */
final class CronService
{
    /**
     * @return array{expired_subscriptions:int,expired_intents:int,campaigns:int,reminders:array,push:array,microtime:float}
     */
    public static function runDaily(): array
    {
        $start = microtime(true);

        $expiredSubscriptions = SubscriptionService::expireOverdue();
        $expiredIntents = PaymentService::expireStale();
        // Zamanı gelen kampanyalar kuyruğa girer; aynı çalıştırmada gönderilir.
        $campaignsQueued = CampaignService::queueDue();
        $reminders = ReminderService::run();
        // Bekleyen cihaz bildirimlerini (Web Push) gönder.
        $push = PushService::process(60);

        $result = [
            'expired_subscriptions' => (int) $expiredSubscriptions,
            'expired_intents'       => (int) $expiredIntents,
            'campaigns'             => (int) $campaignsQueued,
            'reminders'             => $reminders,
            'push'                  => $push,
            'microtime'             => round(microtime(true) - $start, 3),
        ];

        Audit::log('system.cron', 'system', null, [
            'expired_subscriptions' => $result['expired_subscriptions'],
            'expired_intents'       => $result['expired_intents'],
            'campaigns_queued'      => $result['campaigns'],
            'created'               => (int) $reminders['created'],
            'sent'                  => (int) $reminders['sent'],
            'failed'                => (int) $reminders['failed'],
            'skipped'               => (int) $reminders['skipped'],
            'push_sent'             => (int) $push['sent'],
            'push_failed'           => (int) $push['failed'],
            'duration'              => $result['microtime'],
        ]);

        return $result;
    }

    /** İnsan okur özet (cron çıktısı ve panel mesajı). */
    public static function summary(array $result): string
    {
        $reminders = (array) ($result['reminders'] ?? []);
        $parts = [
            'Süresi geçen abonelik: ' . (int) ($result['expired_subscriptions'] ?? 0),
            'Süresi geçen ödeme talebi: ' . (int) ($result['expired_intents'] ?? 0),
            'Kampanya kuyruğa eklenen: ' . (int) ($result['campaigns'] ?? 0),
            'Taranan evrak: ' . (int) ($reminders['scanned'] ?? 0),
            'Kuyruğa eklenen: ' . (int) ($reminders['created'] ?? 0),
            'Tekrar (atlandı): ' . (int) ($reminders['duplicates'] ?? 0),
            'Gönderilen: ' . (int) ($reminders['sent'] ?? 0),
            'Hatalı: ' . (int) ($reminders['failed'] ?? 0),
            'Atlanan: ' . (int) ($reminders['skipped'] ?? 0),
            'Kuyrukta bekleyen: ' . (int) ($reminders['remaining'] ?? 0),
            'Cihaz bildirimi: ' . (int) ($result['push']['sent'] ?? 0) . ' gönderildi, '
                . (int) ($result['push']['failed'] ?? 0) . ' hatalı',
        ];

        if (($reminders['enabled'] ?? true) === false) {
            $parts[] = 'NOT: Hatırlatma motoru kapalı (Ayarlar → Hatırlatma).';
        }
        if (($reminders['locked'] ?? false) === true) {
            $parts[] = 'NOT: Başka bir cron çalışıyor, bu çalıştırma atlandı.';
        }
        foreach ((array) ($reminders['errors'] ?? []) as $error) {
            $parts[] = 'HATA: ' . $error;
        }

        return implode(' · ', $parts);
    }
}
