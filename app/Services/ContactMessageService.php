<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;

/**
 * İletişim formu mesajları: müşteri panelinden takip ve iki yönlü yanıt.
 * Eski kayıtlar `user_id` taşımadığı için e-posta eşleşmesi de kullanılır.
 */
final class ContactMessageService
{
    private static ?bool $available = null;

    public static function available(): bool
    {
        if (self::$available !== null) {
            return self::$available;
        }
        try {
            Database::scalar('SELECT id FROM contact_replies LIMIT 1');
            self::$available = true;
        } catch (\Throwable) {
            self::$available = false;
        }
        return self::$available;
    }

    /** @return array<int,array> Kullanıcının kendi mesajları (yanıt sayısı ve son yanıtla). */
    public static function forUser(int $userId, string $email): array
    {
        if ($userId <= 0) {
            return [];
        }
        $where = 'm.user_id = ?';
        $params = [$userId];
        if ($email !== '') {
            $where .= ' OR (m.user_id IS NULL AND m.email = ?)';
            $params[] = $email;
        }

        if (!self::available()) {
            // Yanıt tablosu henüz yoksa (migration uygulanmadıysa) temel liste döner.
            return Database::select(
                "SELECT m.*, 0 AS reply_count, 0 AS admin_reply_count, NULL AS last_reply_at, NULL AS last_reply
                 FROM contact_messages m WHERE {$where} ORDER BY m.created_at DESC LIMIT 100",
                $params
            );
        }

        return Database::select(
            "SELECT m.*,
                    (SELECT COUNT(*) FROM contact_replies r WHERE r.message_id = m.id) AS reply_count,
                    (SELECT COUNT(*) FROM contact_replies r WHERE r.message_id = m.id AND r.sender = 'admin') AS admin_reply_count,
                    (SELECT MAX(r.created_at) FROM contact_replies r WHERE r.message_id = m.id) AS last_reply_at,
                    (SELECT LEFT(r.body, 160) FROM contact_replies r WHERE r.message_id = m.id ORDER BY r.id DESC LIMIT 1) AS last_reply
             FROM contact_messages m
             WHERE {$where}
             ORDER BY COALESCE((SELECT MAX(r.created_at) FROM contact_replies r WHERE r.message_id = m.id), m.created_at) DESC
             LIMIT 100",
            $params
        );
    }

    public static function findForUser(int $id, int $userId, string $email): ?array
    {
        if ($id <= 0 || $userId <= 0) {
            return null;
        }
        return Database::first(
            'SELECT * FROM contact_messages WHERE id = ? AND (user_id = ? OR (user_id IS NULL AND email = ?)) LIMIT 1',
            [$id, $userId, $email]
        );
    }

    public static function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return Database::first('SELECT * FROM contact_messages WHERE id = ? LIMIT 1', [$id]);
    }

    /** @return array<int,array> */
    public static function replies(int $messageId): array
    {
        if (!self::available() || $messageId <= 0) {
            return [];
        }
        return Database::select(
            'SELECT r.*, u.full_name FROM contact_replies r
             LEFT JOIN users u ON u.id = r.user_id
             WHERE r.message_id = ? ORDER BY r.id ASC',
            [$messageId]
        );
    }

    /** @return array{ok:bool,error?:string} */
    public static function reply(int $messageId, string $sender, ?int $userId, string $body): array
    {
        $message = self::find($messageId);
        if ($message === null) {
            return ['ok' => false, 'error' => 'Mesaj bulunamadı.'];
        }
        if (!self::available()) {
            return ['ok' => false, 'error' => 'Mesaj yanıtları için sistem güncellemesi gerekiyor.'];
        }
        $body = trim($body);
        if ($body === '') {
            return ['ok' => false, 'error' => 'Yanıt metni boş olamaz.'];
        }

        $replyId = Database::insert('contact_replies', [
            'message_id' => $messageId,
            'sender'     => $sender === 'admin' ? 'admin' : 'customer',
            'user_id'    => $userId,
            'body'       => mb_substr($body, 0, 5000),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Yönetim yanıtı, mesajı gönderen müşterinin zilinde "Mesajlar" sekmesine düşer.
        if ($sender === 'admin' && (int) ($message['user_id'] ?? 0) > 0) {
            InboxService::create([
                'user_id'    => (int) $message['user_id'],
                'kind'       => 'message',
                'title'      => 'Mesajınıza yanıt: ' . (string) ($message['subject'] ?: 'İletişim mesajı'),
                'body'       => mb_strimwidth($body, 0, 200, '…'),
                'link_url'   => '/panel/mesajlar/' . $messageId,
                'link_label' => 'Mesajı gör',
                'dedupe_key' => 'contact:reply:' . (int) $replyId,
            ]);
        }

        // Müşteri yanıtı mesajı yönetim için yeniden okunmamış yapar.
        Database::run(
            'UPDATE contact_messages SET is_read = ? WHERE id = ?',
            [$sender === 'admin' ? 1 : 0, $messageId]
        );

        Audit::log('contact.reply', 'contact_message', $messageId, ['sender' => $sender === 'admin' ? 'admin' : 'customer']);
        return ['ok' => true];
    }
}
