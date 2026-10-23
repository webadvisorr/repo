<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Mailer;
use App\Core\Settings;
use App\Core\Translator;

/**
 * Müşteri profil değişiklik talepleri.
 *
 * Güvenlik akışı:
 *  1. Müşteri mevcut şifresini girer ve değişiklik talebi oluşturur.
 *  2. Hiçbir alan hemen değişmez; talep `pending` olarak saklanır.
 *  3. Yönetici onaylarsa alanlar uygulanır; e-posta değiştiyse adres
 *     yeniden doğrulanana kadar `email_verified_at` temizlenir ve yeni
 *     doğrulama kodu gönderilir.
 */
final class ProfileChangeService
{
    /** Düzenlenebilir alanlar ve panel etiketleri. */
    public const FIELDS = [
        'full_name' => 'Ad Soyad',
        'email'     => 'E-posta',
        'phone'     => 'Telefon',
        'whatsapp'  => 'WhatsApp',
        'locale'    => 'Dil',
    ];

    private static ?bool $available = null;

    public static function available(): bool
    {
        if (self::$available !== null) {
            return self::$available;
        }
        try {
            Database::scalar('SELECT id FROM profile_change_requests LIMIT 1');
            self::$available = true;
        } catch (\Throwable) {
            self::$available = false;
        }
        return self::$available;
    }

    /** Kullanıcının bekleyen talebi (varsa). */
    public static function pendingForUser(int $userId): ?array
    {
        if (!self::available() || $userId <= 0) {
            return null;
        }
        return Database::first(
            "SELECT * FROM profile_change_requests WHERE user_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1",
            [$userId]
        );
    }

    /** @return array<int,array> */
    public static function pendingList(): array
    {
        if (!self::available()) {
            return [];
        }
        return Database::select(
            "SELECT r.*, u.full_name AS current_name, u.email AS current_email, u.phone AS current_phone,
                    u.whatsapp AS current_whatsapp, u.locale AS current_locale
             FROM profile_change_requests r
             JOIN users u ON u.id = r.user_id
             WHERE r.status = 'pending'
             ORDER BY r.id ASC"
        );
    }

    /** @return array<int,array> */
    public static function history(int $limit = 60): array
    {
        if (!self::available()) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        return Database::select(
            "SELECT r.*, u.full_name AS current_name, u.email AS current_email, a.full_name AS reviewer
             FROM profile_change_requests r
             JOIN users u ON u.id = r.user_id
             LEFT JOIN users a ON a.id = r.reviewed_by
             WHERE r.status <> 'pending'
             ORDER BY r.id DESC LIMIT {$limit}"
        );
    }

    /** @return array<int,array> Kullanıcının son talepleri (durumlarıyla). */
    public static function historyForUser(int $userId, int $limit = 8): array
    {
        if (!self::available() || $userId <= 0) {
            return [];
        }
        $limit = max(1, min(50, $limit));
        return Database::select(
            "SELECT r.*, a.full_name AS reviewer FROM profile_change_requests r
             LEFT JOIN users a ON a.id = r.reviewed_by
             WHERE r.user_id = ? ORDER BY r.id DESC LIMIT {$limit}",
            [$userId]
        );
    }

    public static function pendingCount(): int
    {
        if (!self::available()) {
            return 0;
        }
        try {
            return (int) Database::scalar("SELECT COUNT(*) FROM profile_change_requests WHERE status = 'pending'");
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Yeni değişiklik talebi oluşturur.
     * @return array{ok:bool,error?:string,id?:int,changes?:array<string,string>}
     */
    public static function submit(int $userId, array $input, string $password): array
    {
        if (!self::available()) {
            return ['ok' => false, 'error' => 'Profil talepleri için sistem güncellemesi gerekiyor. Sistem → Güncellemeleri uygula adımını çalıştırın.'];
        }
        $user = Database::first('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1', [$userId]);
        if ($user === null) {
            return ['ok' => false, 'error' => 'Hesap bulunamadı.'];
        }
        if ($password === '' || !password_verify($password, (string) $user['password_hash'])) {
            Audit::log('profile.request_bad_password', 'user', $userId);
            return ['ok' => false, 'error' => 'Mevcut şifreniz hatalı. Güvenlik için değişiklik talebi oluşturulamadı.'];
        }

        $normalized = [
            'full_name' => trim((string) ($input['full_name'] ?? '')),
            'email'     => mb_strtolower(trim((string) ($input['email'] ?? ''))),
            'phone'     => trim((string) ($input['phone'] ?? '')),
            'whatsapp'  => trim((string) ($input['whatsapp'] ?? '')),
            'locale'    => (string) ($input['locale'] ?? 'tr'),
        ];
        if (!in_array($normalized['locale'], Translator::SUPPORTED, true)) {
            $normalized['locale'] = 'tr';
        }
        if (mb_strlen($normalized['full_name']) < 3) {
            return ['ok' => false, 'error' => 'Ad Soyad en az 3 karakter olmalıdır.'];
        }
        if (!filter_var($normalized['email'], FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Geçerli bir e-posta adresi girin.'];
        }
        foreach (['phone', 'whatsapp'] as $field) {
            if ($normalized[$field] !== '' && !preg_match('/^[0-9+()\s.\-]{7,32}$/', $normalized[$field])) {
                return ['ok' => false, 'error' => 'Telefon numarası geçerli görünmüyor.'];
            }
        }
        if ($normalized['email'] !== (string) $user['email']) {
            $taken = Database::scalar('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1', [$normalized['email'], $userId]);
            if ($taken) {
                return ['ok' => false, 'error' => 'Bu e-posta adresi başka bir hesapta kullanılıyor.'];
            }
        }

        $changes = [];
        foreach (self::FIELDS as $field => $label) {
            $current = (string) ($user[$field] ?? '');
            $value = $normalized[$field];
            if ($field === 'phone' || $field === 'whatsapp') {
                if ($value === '') {
                    $value = '';
                }
            }
            if ($value !== $current) {
                $changes[$field] = $value;
            }
        }
        if ($changes === []) {
            return ['ok' => false, 'error' => 'Değişiklik yok. Bilgileriniz güncel görünüyor.'];
        }

        // Aynı kullanıcının önceki bekleyen talebi iptal edilir (tek aktif talep).
        Database::run(
            "UPDATE profile_change_requests SET status = 'cancelled', reviewed_at = ? WHERE user_id = ? AND status = 'pending'",
            [date('Y-m-d H:i:s'), $userId]
        );

        $id = Database::insert('profile_change_requests', [
            'user_id'      => $userId,
            'changes_json' => (string) json_encode($changes, JSON_UNESCAPED_UNICODE),
            'status'       => 'pending',
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        Audit::log('profile.request', 'user', $userId, ['fields' => array_keys($changes)]);
        self::notifyAdmins($id, $user, $changes);

        return ['ok' => true, 'id' => $id, 'changes' => $changes];
    }

    /** Müşteri kendi bekleyen talebini geri çeker. */
    public static function cancel(int $userId, int $requestId): bool
    {
        if (!self::available()) {
            return false;
        }
        $row = Database::first(
            "SELECT * FROM profile_change_requests WHERE id = ? AND user_id = ? AND status = 'pending' LIMIT 1",
            [$requestId, $userId]
        );
        if ($row === null) {
            return false;
        }
        Database::run(
            "UPDATE profile_change_requests SET status = 'cancelled', reviewed_at = ? WHERE id = ?",
            [date('Y-m-d H:i:s'), $requestId]
        );
        Audit::log('profile.cancel', 'user', $userId, ['request' => $requestId]);
        return true;
    }

    /** @return array{ok:bool,error?:string,email_changed?:bool} */
    public static function approve(int $requestId, ?string $note = null): array
    {
        if (!self::available()) {
            return ['ok' => false, 'error' => 'Profil talepleri için sistem güncellemesi gerekiyor.'];
        }
        $row = Database::first(
            "SELECT r.*, u.email AS user_email FROM profile_change_requests r JOIN users u ON u.id = r.user_id
             WHERE r.id = ? AND r.status = 'pending' LIMIT 1",
            [$requestId]
        );
        if ($row === null) {
            return ['ok' => false, 'error' => 'Talep bulunamadı veya daha önce işlendi.'];
        }
        $changes = json_decode((string) $row['changes_json'], true);
        if (!is_array($changes) || $changes === []) {
            return ['ok' => false, 'error' => 'Talep içeriği okunamadı.'];
        }
        $userId = (int) $row['user_id'];

        $emailChanged = isset($changes['email']) && (string) $changes['email'] !== (string) $row['user_email'];
        if ($emailChanged) {
            $email = mb_strtolower(trim((string) $changes['email']));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'error' => 'Talepteki e-posta adresi geçersiz.'];
            }
            if (Database::scalar('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1', [$email, $userId])) {
                return ['ok' => false, 'error' => 'Bu e-posta adresi başka bir hesapta kullanılıyor. Talebi reddedin veya müşteriden yeni adres isteyin.'];
            }
            $changes['email'] = $email;
        }

        $update = ['updated_at' => date('Y-m-d H:i:s')];
        foreach (['full_name', 'email', 'phone', 'whatsapp', 'locale'] as $field) {
            if (!array_key_exists($field, $changes)) {
                continue;
            }
            $value = trim((string) $changes[$field]);
            $update[$field] = $value === '' ? null : $value;
        }
        if ($emailChanged) {
            $update['email_verified_at'] = null;
        }

        Database::update('users', $update, 'id = :id', ['id' => $userId]);
        Database::update('profile_change_requests', [
            'status'      => 'approved',
            'review_note' => $note !== null ? mb_substr($note, 0, 250) : null,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $requestId]);

        $fresh = Database::first('SELECT * FROM users WHERE id = ? LIMIT 1', [$userId]);
        $mailSent = false;
        if ($emailChanged && $fresh !== null) {
            $issued = EmailVerificationService::issue($fresh);
            $mailSent = (bool) ($issued['ok'] ?? false);
        }

        Audit::log('profile.approve', 'user', $userId, ['request' => $requestId, 'fields' => array_keys($changes), 'email_changed' => $emailChanged]);
        AccountNotifier::profileUpdated($userId, $changes, $emailChanged, $mailSent);

        return ['ok' => true, 'email_changed' => $emailChanged];
    }

    /** @return array{ok:bool,error?:string} */
    public static function reject(int $requestId, ?string $note = null): array
    {
        if (!self::available()) {
            return ['ok' => false, 'error' => 'Profil talepleri için sistem güncellemesi gerekiyor.'];
        }
        $row = Database::first("SELECT * FROM profile_change_requests WHERE id = ? AND status = 'pending' LIMIT 1", [$requestId]);
        if ($row === null) {
            return ['ok' => false, 'error' => 'Talep bulunamadı veya daha önce işlendi.'];
        }
        Database::update('profile_change_requests', [
            'status'      => 'rejected',
            'review_note' => $note !== null ? mb_substr($note, 0, 250) : null,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $requestId]);

        Audit::log('profile.reject', 'user', (int) $row['user_id'], ['request' => $requestId]);
        AccountNotifier::profileRejected((int) $row['user_id'], $note);
        return ['ok' => true];
    }

    /**
     * Görünüm için eski/yeni değer çiftleri.
     * @return array<int,array{field:string,label:string,old:string,new:string}>
     */
    public static function describe(array $request, ?array $user = null): array
    {
        $changes = json_decode((string) ($request['changes_json'] ?? ''), true);
        if (!is_array($changes)) {
            return [];
        }
        $out = [];
        foreach ($changes as $field => $new) {
            if (!array_key_exists($field, self::FIELDS)) {
                continue;
            }
            $oldKey = 'current_' . $field;
            $old = (string) ($user[$oldKey] ?? ($user[$field] ?? ''));
            if ($field === 'locale') {
                $old = Translator::NAMES[$old] ?? $old;
                $new = Translator::NAMES[(string) $new] ?? (string) $new;
            }
            $out[] = [
                'field' => (string) $field,
                'label' => self::FIELDS[$field],
                'old'   => $old === '' ? '—' : $old,
                'new'   => trim((string) $new) === '' ? '—' : (string) $new,
            ];
        }
        return $out;
    }

    /** Yöneticilere e-posta ile haber verir (SMTP yoksa sessizce geçer). */
    private static function notifyAdmins(int $requestId, array $user, array $changes): void
    {
        try {
            $admins = Database::select(
                "SELECT email, full_name FROM users WHERE role IN ('admin','editor') AND status = 'active' AND deleted_at IS NULL"
            );
            if ($admins === []) {
                return;
            }
            $brand = (string) Settings::get('brand.title', 'Evrako');
            $lines = [];
            foreach ($changes as $field => $value) {
                $lines[] = (self::FIELDS[$field] ?? $field) . ': ' . ($value === '' ? '—' : (string) $value);
            }
            $link = url('/yonetim/profil-talepleri');
            $html = '<p><strong>' . e((string) $user['full_name']) . '</strong> (' . e((string) $user['email']) . ') profil güncelleme talebi gönderdi.</p>'
                . '<ul><li>' . implode('</li><li>', array_map('e', $lines)) . '</li></ul>'
                . '<p><a href="' . e($link) . '">Talebi inceleyip onaylayın</a></p>';
            $mailer = new Mailer(['timeout' => 5]);
            if (!$mailer->isConfigured()) {
                return;
            }
            foreach ($admins as $admin) {
                if (!filter_var((string) $admin['email'], FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                $mailer->send(
                    (string) $admin['email'],
                    $brand . ' · Profil güncelleme talebi',
                    $html,
                    strip_tags(str_replace(['</li>', '</p>'], "\n", $html))
                );
            }
        } catch (\Throwable $e) {
            Logger::warning('Profil talebi yönetici bildirimi gönderilemedi: ' . $e->getMessage());
        }
    }
}
