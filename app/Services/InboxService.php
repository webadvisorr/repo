<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Translator;

/**
 * Site içi bildirim merkezi.
 *
 * - Hatırlatma motoru (`ReminderService::scan`) e-postanın yanında buraya da
 *   kayıt bırakır; kullanıcı panelindeki zilden görür.
 * - Yönetim panelinden resim/metin/link içeren özel duyuru gönderilir
 *   (`inbox_broadcasts` + kullanıcı başına `user_notifications`).
 *
 * Kayıtlar `dedupe_key` ile tekilleştirilir; tablolar henüz yoksa (migration
 * uygulanmadan) servis sessizce devre dışı kalır, e-posta akışı bozulmaz.
 */
final class InboxService
{
    public const KINDS = ['reminder', 'announcement', 'support', 'message', 'system'];
    /** Zil sekmelerinin sabit sırası. */
    public const KIND_ORDER = ['reminder', 'announcement', 'support', 'message', 'system'];
    private const KIND_DEFAULT_LABELS = [
        'reminder'     => 'Hatırlatma',
        'announcement' => 'Duyuru',
        'support'      => 'Destek',
        'message'      => 'Mesajlar',
        'system'       => 'Sistem',
    ];

    private static ?bool $available = null;

    /** Tablolar hazır mı? (migration öncesi güvenli çalışma) */
    public static function available(): bool
    {
        if (self::$available !== null) {
            return self::$available;
        }
        try {
            Database::scalar('SELECT id FROM user_notifications LIMIT 1');
            Database::scalar('SELECT id FROM inbox_broadcasts LIMIT 1');
            self::$available = true;
        } catch (\Throwable) {
            self::$available = false;
        }
        return self::$available;
    }

    /* ---------------- Kullanıcı bildirimleri ---------------- */

    /**
     * @param array{user_id:int,title:string,body?:string,kind?:string,image_path?:?string,link_url?:?string,
     *              link_label?:?string,dedupe_key?:?string,broadcast_id?:?int,organization_id?:?int,
     *              vehicle_id?:?int,document_id?:?int} $data
     */
    public static function create(array $data): ?int
    {
        if (!self::available()) {
            return null;
        }
        $userId = (int) ($data['user_id'] ?? 0);
        $title = trim((string) ($data['title'] ?? ''));
        if ($userId <= 0 || $title === '') {
            return null;
        }
        $kind = in_array((string) ($data['kind'] ?? ''), self::KINDS, true) ? (string) $data['kind'] : 'announcement';

        try {
            $id = Database::insert('user_notifications', [
                'user_id'         => $userId,
                'organization_id' => isset($data['organization_id']) ? (int) $data['organization_id'] ?: null : null,
                'kind'            => $kind,
                'broadcast_id'    => isset($data['broadcast_id']) ? (int) $data['broadcast_id'] ?: null : null,
                'vehicle_id'      => isset($data['vehicle_id']) ? (int) $data['vehicle_id'] ?: null : null,
                'document_id'     => isset($data['document_id']) ? (int) $data['document_id'] ?: null : null,
                'title'           => mb_substr($title, 0, 160),
                'body'            => trim((string) ($data['body'] ?? '')) ?: null,
                'image_path'      => ($data['image_path'] ?? null) ? (string) $data['image_path'] : null,
                'link_url'        => self::safeLink($data['link_url'] ?? null),
                'link_label'      => mb_substr(trim((string) ($data['link_label'] ?? '')), 0, 80) ?: null,
                'dedupe_key'      => ($data['dedupe_key'] ?? null) ? mb_substr((string) $data['dedupe_key'], 0, 160) : null,
                'read_at'         => null,
                'created_at'      => date('Y-m-d H:i:s'),
            ]);

            // Aynı bildirim, kullanıcının bağlı cihazlarına da (Web Push) gider.
            if ($id > 0) {
                PushService::queueFromNotification(
                    $userId,
                    (string) $data['title'],
                    (string) ($data['body'] ?? ''),
                    (string) ($data['link_url'] ?? '') !== '' ? (string) $data['link_url'] : '/panel/bildirimler',
                    isset($data['image_path']) && $data['image_path'] !== null ? (string) $data['image_path'] : null,
                    $kind
                );
            }

            return $id;
        } catch (\Throwable $e) {
            if (!str_contains($e->getMessage(), 'Duplicate entry')) {
                Logger::error('Site içi bildirim yazılamadı: ' . $e->getMessage(), ['user' => $userId]);
            }
            return null;
        }
    }

    /**
     * Evrak hatırlatmasını kullanıcının bildirim merkezine bırakır.
     * @param array $document vehicle_documents satırı (+ type_labels)
     * @param array $vehicle  vehicles satırı
     */
    public static function reminder(int $userId, array $document, array $vehicle, int $days, ?int $organizationId = null): ?int
    {
        $locale = Translator::locale();
        $documentId = (int) ($document['id'] ?? 0);
        $endDate = (string) ($document['end_date'] ?? '');
        if ($documentId <= 0 || $endDate === '') {
            return null;
        }

        $label = label((string) ($document['type_labels'] ?? ''), $locale) ?: 'Evrak';
        $plate = (string) ($vehicle['plate'] ?? '');

        $title = $plate !== '' ? $label . ' · ' . $plate : $label;
        $body = $days > 0
            ? sprintf('%s evrakının süresi %d gün sonra doluyor. Son tarih %s.', $label, $days, format_date($endDate))
            : sprintf('%s evrakının süresi %s. Son tarih %s.', $label, $days === 0 ? 'bugün doluyor' : abs($days) . ' gün önce doldu', format_date($endDate));

        return self::create([
            'user_id'         => $userId,
            'organization_id' => $organizationId,
            'kind'            => 'reminder',
            'vehicle_id'      => (int) ($vehicle['id'] ?? $document['vehicle_id'] ?? 0),
            'document_id'     => $documentId,
            'title'           => $title,
            'body'            => $body,
            'link_url'        => '/panel/araclar/' . (int) ($document['vehicle_id'] ?? ($vehicle['id'] ?? 0)),
            'link_label'      => 'Aracı gör',
            'dedupe_key'      => sprintf('inbox:reminder:%d:%d:%d:%s', $userId, $documentId, $days, $endDate),
        ]);
    }

    public static function unreadCount(int $userId): int
    {
        if (!self::available() || $userId <= 0) {
            return 0;
        }
        try {
            return (int) Database::scalar('SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND read_at IS NULL', [$userId]);
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array<int,array> */
    /**
     * Tür bazında toplam ve okunmamış sayıları (zil sekmeleri).
     * @return array<string,array{total:int,unread:int}>
     */
    public static function categoryCounts(int $userId): array
    {
        $out = [];
        foreach (self::KIND_ORDER as $kind) {
            $out[$kind] = ['total' => 0, 'unread' => 0];
        }
        if (!self::available() || $userId <= 0) {
            return $out;
        }
        try {
            foreach (Database::select(
                'SELECT kind, COUNT(*) AS total, SUM(read_at IS NULL) AS unread FROM user_notifications WHERE user_id = ? GROUP BY kind',
                [$userId]
            ) as $row) {
                $kind = (string) $row['kind'];
                if (!isset($out[$kind])) {
                    $out[$kind] = ['total' => 0, 'unread' => 0];
                }
                $out[$kind]['total'] = (int) $row['total'];
                $out[$kind]['unread'] = (int) $row['unread'];
            }
        } catch (\Throwable) {
        }
        return $out;
    }

    public static function markKindRead(int $userId, string $kind): int
    {
        if (!self::available() || $userId <= 0 || !in_array($kind, self::KINDS, true)) {
            return 0;
        }
        return Database::run(
            'UPDATE user_notifications SET read_at = ? WHERE user_id = ? AND kind = ? AND read_at IS NULL',
            [date('Y-m-d H:i:s'), $userId, $kind]
        )->rowCount();
    }

    /** Son bildirimler (tür filtresiyle). @return array<int,array> */
    public static function latest(int $userId, int $limit = 8): array
    {
        if (!self::available() || $userId <= 0) {
            return [];
        }
        $limit = max(1, min(50, $limit));
        try {
            return Database::select(
                'SELECT * FROM user_notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . $limit,
                [$userId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array{rows:array<int,array>,total:int,page:int,pages:int,unread:int}
     */
    public static function paginate(int $userId, int $page = 1, int $perPage = 20, ?string $kindFilter = null): array
    {
        $page = max(1, $page);
        $out = ['rows' => [], 'total' => 0, 'page' => $page, 'pages' => 1, 'unread' => self::unreadCount($userId)];
        if (!self::available() || $userId <= 0) {
            return $out;
        }
        $perPage = max(1, min(100, $perPage));
        $kind = (string) ($kindFilter ?? '');
        $where = 'user_id = ?';
        $params = [$userId];
        if (in_array($kind, self::KINDS, true)) {
            $where .= ' AND kind = ?';
            $params[] = $kind;
        }
        try {
            $total = (int) Database::scalar('SELECT COUNT(*) FROM user_notifications WHERE ' . $where, $params);
            $out['total'] = $total;
            $out['pages'] = max(1, (int) ceil($total / $perPage));
            $out['page'] = min($page, $out['pages']);
            $offset = ($out['page'] - 1) * $perPage;
            $out['rows'] = Database::select(
                'SELECT * FROM user_notifications WHERE ' . $where . ' ORDER BY created_at DESC, id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
                $params
            );
        } catch (\Throwable) {
            return $out;
        }
        return $out;
    }

    public static function findForUser(int $userId, int $id): ?array
    {
        if (!self::available() || $userId <= 0 || $id <= 0) {
            return null;
        }
        return Database::first('SELECT * FROM user_notifications WHERE id = ? AND user_id = ? LIMIT 1', [$id, $userId]);
    }

    public static function markRead(int $userId, int $id): bool
    {
        if (!self::available() || $userId <= 0 || $id <= 0) {
            return false;
        }
        Database::run(
            'UPDATE user_notifications SET read_at = COALESCE(read_at, ?) WHERE id = ? AND user_id = ?',
            [date('Y-m-d H:i:s'), $id, $userId]
        );
        return self::findForUser($userId, $id) !== null;
    }

    public static function markAllRead(int $userId): int
    {
        if (!self::available() || $userId <= 0) {
            return 0;
        }
        return Database::run(
            'UPDATE user_notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL',
            [date('Y-m-d H:i:s'), $userId]
        )->rowCount();
    }

    /* ---------------- Özel duyurular (yönetim) ---------------- */

    /** @return array{plan_key:string,org_id:int,user_id:int} */
    public static function normalizeAudience(array $filters): array
    {
        return [
            'plan_key' => trim((string) ($filters['plan_key'] ?? '')),
            'org_id'   => max(0, (int) ($filters['org_id'] ?? 0)),
            'user_id'  => max(0, (int) ($filters['user_id'] ?? 0)),
        ];
    }

    /** @return array{plan_key:string,org_id:int,user_id:int} */
    public static function audienceOf(array $broadcast): array
    {
        $decoded = json_decode((string) ($broadcast['audience_json'] ?? ''), true);
        return self::normalizeAudience(is_array($decoded) ? $decoded : []);
    }

    /**
     * @param array{plan_key:string,org_id:int,user_id:int} $audience
     * @return array{0:string,1:array<int,mixed>}
     */
    private static function audienceSql(array $audience, bool $count): array
    {
        $where = ["u.status = 'active'", 'u.deleted_at IS NULL'];
        $params = [];

        if ($audience['user_id'] > 0) {
            $where[] = 'u.id = ?';
            $params[] = $audience['user_id'];
        }
        if ($audience['org_id'] > 0) {
            $where[] = 'm.organization_id = ?';
            $params[] = $audience['org_id'];
        }
        if ($audience['plan_key'] !== '') {
            $where[] = 'p.`key` = ?';
            $params[] = $audience['plan_key'];
        }

        $select = $count
            ? 'SELECT COUNT(DISTINCT u.id)'
            : 'SELECT u.id, MIN(u.full_name) AS full_name, MIN(u.email) AS email, MIN(m.organization_id) AS organization_id';

        $sql = $select . '
                FROM users u
                LEFT JOIN organization_members m ON m.user_id = u.id AND m.status = \'active\'
                LEFT JOIN subscriptions s ON s.organization_id = m.organization_id AND s.status IN (\'active\', \'trialing\')
                LEFT JOIN plans p ON p.id = s.plan_id
                WHERE ' . implode(' AND ', $where);

        if (!$count) {
            $sql .= ' GROUP BY u.id ORDER BY u.id ASC';
        }
        return [$sql, $params];
    }

    public static function audienceCount(array $audience): int
    {
        if (!self::available()) {
            return 0;
        }
        try {
            [$sql, $params] = self::audienceSql(self::normalizeAudience($audience), true);
            return (int) Database::scalar($sql, $params);
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array<int,array> */
    public static function audienceUsers(array $audience, int $limit = 0): array
    {
        if (!self::available()) {
            return [];
        }
        [$sql, $params] = self::audienceSql(self::normalizeAudience($audience), false);
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
        }
        return Database::select($sql, $params);
    }

    /**
     * Duyuruyu oluşturur ve hedef kitleye dağıtır.
     * @return array{ok:bool,id?:int,recipients?:int,error?:string}
     */
    public static function send(array $data): array
    {
        if (!self::available()) {
            return ['ok' => false, 'error' => 'Bildirim tabloları yok. Sistem → Güncellemeleri uygula adımını çalıştırın.'];
        }
        $title = trim((string) ($data['title'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));
        if ($title === '' || $body === '') {
            return ['ok' => false, 'error' => 'Başlık ve metin zorunludur.'];
        }

        $audience = self::normalizeAudience((array) ($data['audience'] ?? []));
        $recipients = self::audienceCount($audience);
        if ($recipients === 0) {
            return ['ok' => false, 'error' => 'Seçilen hedef kitlede aktif kullanıcı yok.'];
        }

        $now = date('Y-m-d H:i:s');
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $broadcastId = Database::insert('inbox_broadcasts', [
                'title'           => mb_substr($title, 0, 160),
                'body'            => $body,
                'image_media_id'  => isset($data['image_media_id']) ? (int) $data['image_media_id'] ?: null : null,
                'image_path'      => ($data['image_path'] ?? null) ? (string) $data['image_path'] : null,
                'link_url'        => self::safeLink($data['link_url'] ?? null),
                'link_label'      => mb_substr(trim((string) ($data['link_label'] ?? '')), 0, 80) ?: null,
                'audience_json'   => (string) json_encode($audience, JSON_UNESCAPED_UNICODE),
                'recipient_count' => 0,
                'created_by'      => Auth::id(),
                'created_at'      => $now,
                'sent_at'         => $now,
            ]);

            $delivered = 0;
            foreach (self::audienceUsers($audience) as $user) {
                $inserted = self::create([
                    'user_id'         => (int) $user['id'],
                    'organization_id' => (int) ($user['organization_id'] ?? 0),
                    'kind'            => 'announcement',
                    'broadcast_id'    => $broadcastId,
                    'title'           => $title,
                    'body'            => $body,
                    'image_path'      => $data['image_path'] ?? null,
                    'link_url'        => $data['link_url'] ?? null,
                    'link_label'      => $data['link_label'] ?? null,
                    'dedupe_key'      => 'announce:' . $broadcastId . ':' . (int) $user['id'],
                ]);
                if ($inserted !== null) {
                    $delivered++;
                }
            }

            Database::update('inbox_broadcasts', ['recipient_count' => $delivered], 'id = :id', ['id' => $broadcastId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Logger::error('Duyuru gönderilemedi: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Duyuru gönderilemedi. Lütfen tekrar deneyin.'];
        }

        // Cihaz bildirimlerini hemen göndermeye çalış; kalanı cron işler.
        $push = PushService::process(15);

        Audit::log('inbox.broadcast', 'inbox_broadcast', $broadcastId, [
            'title' => mb_substr($title, 0, 160), 'recipients' => $delivered,
            'push_sent' => $push['sent'], 'push_failed' => $push['failed'],
        ]);

        return ['ok' => true, 'id' => $broadcastId, 'recipients' => $delivered, 'push' => $push];
    }

    /** @return array<int,array> */
    public static function broadcasts(int $limit = 100): array
    {
        if (!self::available()) {
            return [];
        }
        return Database::select(
            'SELECT b.*, u.full_name AS author FROM inbox_broadcasts b
             LEFT JOIN users u ON u.id = b.created_by
             ORDER BY b.id DESC LIMIT ' . max(1, min(200, $limit))
        );
    }

    public static function broadcast(int $id): ?array
    {
        if (!self::available() || $id <= 0) {
            return null;
        }
        return Database::first(
            'SELECT b.*, u.full_name AS author FROM inbox_broadcasts b
             LEFT JOIN users u ON u.id = b.created_by WHERE b.id = ? LIMIT 1',
            [$id]
        );
    }

    /** @return array{recipients:int,read:int,unread:int} */
    public static function broadcastStats(int $id): array
    {
        $out = ['recipients' => 0, 'read' => 0, 'unread' => 0];
        if (!self::available() || $id <= 0) {
            return $out;
        }
        $row = Database::first(
            'SELECT COUNT(*) AS total, SUM(read_at IS NOT NULL) AS read_count
             FROM user_notifications WHERE broadcast_id = ?',
            [$id]
        );
        $out['recipients'] = (int) ($row['total'] ?? 0);
        $out['read'] = (int) ($row['read_count'] ?? 0);
        $out['unread'] = max(0, $out['recipients'] - $out['read']);
        return $out;
    }

    /** @return array<int,array> */
    public static function broadcastRecipients(int $id, int $limit = 100): array
    {
        if (!self::available() || $id <= 0) {
            return [];
        }
        return Database::select(
            'SELECT n.id, n.read_at, u.full_name, u.email
             FROM user_notifications n JOIN users u ON u.id = n.user_id
             WHERE n.broadcast_id = ? ORDER BY n.read_at IS NULL DESC, n.id ASC LIMIT ' . max(1, min(500, $limit)),
            [$id]
        );
    }

    /** Duyuruyu ve kullanıcı bildirimlerini siler. */
    public static function deleteBroadcast(int $id): bool
    {
        if (!self::available() || $id <= 0) {
            return false;
        }
        $broadcast = self::broadcast($id);
        if ($broadcast === null) {
            return false;
        }
        Database::delete('inbox_broadcasts', 'id = ?', [$id]); // user_notifications CASCADE
        Audit::log('inbox.broadcast_delete', 'inbox_broadcast', $id, ['title' => (string) $broadcast['title']]);
        return true;
    }

    /* ---------------- Yardımcılar ---------------- */

    /** Yalnızca site içi yol veya http(s) adresi kabul edilir. */
    public static function safeLink(mixed $value): ?string
    {
        $url = trim((string) ($value ?? ''));
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return mb_substr($url, 0, 500);
        }
        if (preg_match('~^https?://~i', $url) === 1) {
            return mb_substr($url, 0, 500);
        }
        return null;
    }

    public static function imageUrl(?string $path): ?string
    {
        return $path === null || $path === '' ? null : media_url($path);
    }

    public static function kindLabel(string $kind): string
    {
        $key = 'inbox.kind_' . $kind;
        $label = t($key);
        return $label === $key ? (self::KIND_DEFAULT_LABELS[$kind] ?? $kind) : $label;
    }

    public static function kindIcon(string $kind): string
    {
        return match ($kind) {
            'reminder'     => '⏰',
            'support'      => '🎧',
            'message'      => '✉️',
            'system'       => '⚙️',
            default        => '📣',
        };
    }
}
