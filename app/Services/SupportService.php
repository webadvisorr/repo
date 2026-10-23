<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Str;

/**
 * Destek talepleri: müşteri panelinden açılan talep ve yönetim yanıtları.
 * Her talep bir konuşma (support_ticket_messages) tutar.
 */
final class SupportService
{
    public const CATEGORIES = [
        'technical' => 'Teknik sorun',
        'billing'   => 'Ödeme / fatura',
        'documents' => 'Evrak işlemleri',
        'account'   => 'Hesap / giriş',
        'other'     => 'Diğer',
    ];

    public const PRIORITIES = [
        'low'    => 'Düşük',
        'normal' => 'Normal',
        'high'   => 'Yüksek',
    ];

    public const STATUSES = [
        'open'     => 'Açık',
        'answered' => 'Yanıtlandı',
        'closed'   => 'Kapalı',
    ];

    private static ?bool $available = null;

    public static function available(): bool
    {
        if (self::$available !== null) {
            return self::$available;
        }
        try {
            Database::scalar('SELECT id FROM support_tickets LIMIT 1');
            self::$available = true;
        } catch (\Throwable) {
            self::$available = false;
        }
        return self::$available;
    }

    /**
     * Müşterinin yanıt bekleyen (destek ekibine yazmış, henüz yanıt almamış) talebi.
     * Bu talep varken yeni talep açılamaz ve talebe yeni mesaj yazılamaz.
     */
    public static function pendingForUser(int $userId): ?array
    {
        if (!self::available() || $userId <= 0) {
            return null;
        }
        return Database::first(
            "SELECT * FROM support_tickets WHERE user_id = ? AND status = 'open' ORDER BY COALESCE(last_reply_at, created_at) DESC, id DESC LIMIT 1",
            [$userId]
        );
    }

    /** Müşteri yeni talep açabilir mi? Yanıt bekleyen talebi varsa hayır. */
    public static function canOpenNew(int $userId): bool
    {
        return self::pendingForUser($userId) === null;
    }

    /**
     * @param array{subject:string,category:string,priority:string,body:string,organization_id?:int} $data
     * @return array{ok:bool,id?:int,error?:string}
     */
    public static function create(int $userId, array $data): array
    {
        if (!self::available()) {
            return ['ok' => false, 'error' => 'Destek modülü hazır değil. Sistem → Güncellemeleri uygula adımını çalıştırın.'];
        }
        $pending = self::pendingForUser($userId);
        if ($pending !== null) {
            return [
                'ok'      => false,
                'pending' => (int) $pending['id'],
                'error'   => 'Yanıt bekleyen bir destek talebiniz var. Destek ekibimiz yanıtladıktan sonra yeni talep açabilirsiniz.',
            ];
        }
        $subject = trim((string) ($data['subject'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));
        if ($subject === '' || $body === '') {
            return ['ok' => false, 'error' => 'Konu ve mesaj zorunludur.'];
        }
        $category = array_key_exists((string) ($data['category'] ?? ''), self::CATEGORIES) ? (string) $data['category'] : 'other';
        $priority = array_key_exists((string) ($data['priority'] ?? ''), self::PRIORITIES) ? (string) $data['priority'] : 'normal';

        $now = date('Y-m-d H:i:s');
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $ticketId = Database::insert('support_tickets', [
                'ticket_no'       => 'DST-' . strtoupper(Str::random(8)),
                'user_id'         => $userId,
                'organization_id' => !empty($data['organization_id']) ? (int) $data['organization_id'] : null,
                'subject'         => mb_substr($subject, 0, 200),
                'category'        => $category,
                'priority'        => $priority,
                'status'          => 'open',
                'last_reply_at'   => $now,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
            Database::insert('support_ticket_messages', [
                'ticket_id'       => $ticketId,
                'sender'          => 'customer',
                'user_id'         => $userId,
                'body'            => mb_substr($body, 0, 5000),
                'read_by_admin_at' => null,
                'created_at'      => $now,
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Logger::error('Destek talebi oluşturulamadı: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Talep oluşturulamadı. Lütfen tekrar deneyin.'];
        }

        Audit::log('support.create', 'support_ticket', $ticketId, ['subject' => mb_substr($subject, 0, 200)]);
        return ['ok' => true, 'id' => $ticketId];
    }

    public static function find(int $id): ?array
    {
        if (!self::available() || $id <= 0) {
            return null;
        }
        return Database::first(
            'SELECT t.*, u.full_name, u.email, u.phone FROM support_tickets t
             JOIN users u ON u.id = t.user_id WHERE t.id = ? LIMIT 1',
            [$id]
        );
    }

    /** @return array<int,array> */
    public static function forUser(int $userId): array
    {
        if (!self::available() || $userId <= 0) {
            return [];
        }
        return Database::select(
            'SELECT t.*,
                    (SELECT COUNT(*) FROM support_ticket_messages m WHERE m.ticket_id = t.id) AS message_count,
                    (SELECT COUNT(*) FROM support_ticket_messages m WHERE m.ticket_id = t.id AND m.sender = \'admin\' AND m.read_by_customer_at IS NULL) AS unread_replies
             FROM support_tickets t WHERE t.user_id = ? ORDER BY COALESCE(t.last_reply_at, t.created_at) DESC LIMIT 100',
            [$userId]
        );
    }

    /** @return array<int,array> */
    public static function messages(int $ticketId): array
    {
        if (!self::available() || $ticketId <= 0) {
            return [];
        }
        return Database::select(
            'SELECT m.*, u.full_name FROM support_ticket_messages m
             LEFT JOIN users u ON u.id = m.user_id
             WHERE m.ticket_id = ? ORDER BY m.id ASC',
            [$ticketId]
        );
    }

    /** @return array{ok:bool,error?:string} */
    public static function reply(int $ticketId, string $sender, int $userId, string $body): array
    {
        $ticket = self::find($ticketId);
        if ($ticket === null) {
            return ['ok' => false, 'error' => 'Talep bulunamadı.'];
        }
        if ((string) $ticket['status'] === 'closed') {
            return ['ok' => false, 'error' => 'Kapatılmış talebe yanıt yazılamaz.'];
        }
        // Müşteri, destek ekibi yanıtlamadan yeni mesaj yazamaz (sıra destekte).
        if ($sender !== 'admin' && (string) $ticket['status'] === 'open') {
            return ['ok' => false, 'error' => 'Destek ekibimiz yanıt verene kadar yeni mesaj yazamazsınız.'];
        }
        $body = trim($body);
        if ($body === '') {
            return ['ok' => false, 'error' => 'Mesaj boş olamaz.'];
        }
        $isAdmin = $sender === 'admin';
        $now = date('Y-m-d H:i:s');

        $messageId = Database::insert('support_ticket_messages', [
            'ticket_id'        => $ticketId,
            'sender'           => $isAdmin ? 'admin' : 'customer',
            'user_id'          => $userId,
            'body'             => mb_substr($body, 0, 5000),
            'read_by_admin_at' => $isAdmin ? $now : null,
            'read_by_customer_at' => $isAdmin ? null : $now,
            'created_at'       => $now,
        ]);

        // Yönetim yanıtı müşterinin zilinde "Destek" sekmesine düşer.
        if ($isAdmin) {
            InboxService::create([
                'user_id'     => (int) $ticket['user_id'],
                'kind'        => 'support',
                'title'       => 'Destek yanıtı: ' . (string) $ticket['subject'],
                'body'        => mb_strimwidth($body, 0, 200, '…'),
                'link_url'    => '/panel/destek/' . $ticketId,
                'link_label'  => 'Talebi gör',
                'dedupe_key'  => 'support:reply:' . (int) $messageId,
            ]);
        }
        Database::update('support_tickets', [
            'status'        => $isAdmin ? 'answered' : 'open',
            'last_reply_at' => $now,
            'updated_at'    => $now,
        ], 'id = :id', ['id' => $ticketId]);

        Audit::log('support.reply', 'support_ticket', $ticketId, ['sender' => $isAdmin ? 'admin' : 'customer']);
        return ['ok' => true];
    }

    public static function setStatus(int $ticketId, string $status): bool
    {
        if (!self::available() || !array_key_exists($status, self::STATUSES)) {
            return false;
        }
        Database::update('support_tickets', [
            'status'     => $status,
            'closed_at'  => $status === 'closed' ? date('Y-m-d H:i:s') : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $ticketId]);
        Audit::log('support.status', 'support_ticket', $ticketId, ['status' => $status]);
        return true;
    }

    /** Yönetim listesi (filtreler: q, status, from, to). */
    public static function adminList(array $filters, int $page = 1, int $perPage = 25): array
    {
        $out = ['rows' => [], 'total' => 0, 'page' => max(1, $page), 'pages' => 1];
        if (!self::available()) {
            return $out;
        }
        [$where, $params] = self::adminWhere($filters);
        $whereSql = implode(' AND ', $where);
        $total = (int) Database::scalar("SELECT COUNT(*) FROM support_tickets t JOIN users u ON u.id = t.user_id WHERE {$whereSql}", $params);
        $out['total'] = $total;
        $out['pages'] = max(1, (int) ceil($total / $perPage));
        $out['page'] = min(max(1, $page), $out['pages']);
        $offset = ($out['page'] - 1) * $perPage;

        $out['rows'] = Database::select(
            "SELECT t.*, u.full_name, u.email,
                    (SELECT COUNT(*) FROM support_ticket_messages m WHERE m.ticket_id = t.id AND m.sender = 'customer' AND m.read_by_admin_at IS NULL) AS unread_count,
                    (SELECT COUNT(*) FROM support_ticket_messages m WHERE m.ticket_id = t.id) AS message_count
             FROM support_tickets t JOIN users u ON u.id = t.user_id
             WHERE {$whereSql}
             ORDER BY FIELD(t.status, 'open', 'answered', 'closed'), COALESCE(t.last_reply_at, t.created_at) DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return $out;
    }

    /** @return array{0:array<int,string>,1:array<int,mixed>} */
    private static function adminWhere(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];
        $q = trim((string) ($filters['q'] ?? ''));
        $status = (string) ($filters['status'] ?? '');

        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $where[] = '(t.subject LIKE ? OR t.ticket_no LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR EXISTS (SELECT 1 FROM support_ticket_messages m WHERE m.ticket_id = t.id AND m.body LIKE ?))';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if (array_key_exists($status, self::STATUSES)) {
            $where[] = 't.status = ?';
            $params[] = $status;
        }
        if (!empty($filters['from'])) {
            $where[] = 't.created_at >= ?';
            $params[] = (string) $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where[] = 't.created_at <= ?';
            $params[] = (string) $filters['to'] . ' 23:59:59';
        }
        return [$where, $params];
    }

    /** @return array{open:int,answered:int,closed:int,total:int,unread:int} */
    public static function adminStats(): array
    {
        $out = ['open' => 0, 'answered' => 0, 'closed' => 0, 'total' => 0, 'unread' => 0];
        if (!self::available()) {
            return $out;
        }
        try {
            foreach (Database::select('SELECT status, COUNT(*) AS total FROM support_tickets GROUP BY status') as $row) {
                $out[(string) $row['status']] = (int) $row['total'];
                $out['total'] += (int) $row['total'];
            }
            $out['unread'] = (int) Database::scalar(
                "SELECT COUNT(DISTINCT m.ticket_id) FROM support_ticket_messages m
                 JOIN support_tickets t ON t.id = m.ticket_id
                 WHERE m.sender = 'customer' AND m.read_by_admin_at IS NULL AND t.status <> 'closed'"
            );
        } catch (\Throwable) {
        }
        return $out;
    }

    /** Yönetim talep detayını açtığında müşteri mesajlarını okundu sayar. */
    public static function markReadByAdmin(int $ticketId): void
    {
        if (!self::available()) {
            return;
        }
        Database::run(
            "UPDATE support_ticket_messages SET read_by_admin_at = ? WHERE ticket_id = ? AND sender = 'customer' AND read_by_admin_at IS NULL",
            [date('Y-m-d H:i:s'), $ticketId]
        );
    }

    /** Müşteri talebi açtığında yönetim yanıtlarını okundu sayar. */
    public static function markReadByCustomer(int $ticketId): void
    {
        if (!self::available()) {
            return;
        }
        Database::run(
            "UPDATE support_ticket_messages SET read_by_customer_at = ? WHERE ticket_id = ? AND sender = 'admin' AND read_by_customer_at IS NULL",
            [date('Y-m-d H:i:s'), $ticketId]
        );
    }

    public static function userUnread(int $userId): int
    {
        if (!self::available() || $userId <= 0) {
            return 0;
        }
        try {
            return (int) Database::scalar(
                "SELECT COUNT(*) FROM support_ticket_messages m JOIN support_tickets t ON t.id = m.ticket_id
                 WHERE t.user_id = ? AND m.sender = 'admin' AND m.read_by_customer_at IS NULL AND t.status <> 'closed'",
                [$userId]
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status] ?? $status;
    }

    public static function categoryLabel(string $category): string
    {
        return self::CATEGORIES[$category] ?? $category;
    }

    public static function priorityLabel(string $priority): string
    {
        return self::PRIORITIES[$priority] ?? $priority;
    }
}
