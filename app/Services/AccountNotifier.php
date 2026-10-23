<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Mailer;
use App\Core\Settings;

/**
 * Hesap hareketleri bildirimleri.
 *
 * Panel zil kaydı (InboxService) + markalı e-posta tek yerden gönderilir:
 *  - yeni üye karşılaması
 *  - kredi yükleme / ödeme onayı
 *  - abonelik başlatma ve süre uzatma
 *
 * E-posta gönderimi başarısız olsa bile panel bildirimi kalır; tüm gönderimler
 * `notifications` tablosuna (kanal: email, şablon: account) loglanır.
 */
final class AccountNotifier
{
    /**
     * Yeni üye karşılaması.
     *
     * @param array $user users satırı (id, full_name, email)
     */
    public static function welcome(array $user, ?array $plan = null): void
    {
        $userId = (int) ($user['id'] ?? 0);
        $name = trim((string) ($user['full_name'] ?? ''));
        if ($name === '') {
            $name = 'Merhaba';
        }
        $brand = (string) Settings::get('brand.title', 'Evrako');
        $planLine = $plan !== null && (string) ($plan['name'] ?? '') !== ''
            ? (string) $plan['name'] . ' paketiniz hesabınıza tanımlandı.'
            : 'Paketiniz hesabınıza tanımlandı.';

        self::deliver($userId, [
            'kind'    => 'system',
            'title'   => 'Hoş geldiniz!',
            'body'    => 'Merhaba ' . $name . ', aramıza hoş geldiniz. ' . $planLine
                . ' Araçlarınızı ve evraklarınızı panelden takip edebilirsiniz; süreler yaklaştığında sizi e-posta ve panelden uyaracağız.',
            'link'    => '/panel',
            'link_label' => 'Panele git',
            'subject' => 'Hoş geldiniz · ' . $brand,
            'heading' => 'Hoş geldiniz, ' . $name . '!',
            'intro'   => $brand . ' ailesine katıldığınız için teşekkür ederiz. Artık araç evraklarınızı tek panelden takip edebilirsiniz.',
            'lines'   => [
                $planLine,
                'Muayene, sigorta, kasko ve diğer evrakların bitiş tarihlerini girin; süre yaklaşınca otomatik hatırlatma alın.',
                'Evrakların eklenmesi, takibi ve hatırlatmaları ücretsizdir.',
            ],
            'cta'     => 'Panele git',
            'cta_url' => '/panel',
            'event'   => 'welcome',
            'log_key' => 'account:welcome:' . $userId . ':' . bin2hex(random_bytes(5)),
        ]);
    }

    /** Kredi yüklemesi (manuel ekleme veya havale onayı). */
    public static function creditAdded(int $organizationId, float $amount, float $balance, string $reason = 'manual', ?string $note = null): void
    {
        $ownerId = self::ownerOf($organizationId);
        if ($ownerId <= 0) {
            return;
        }
        $currency = (string) Settings::get('payment.currency', 'TRY');
        $money = static fn (float $value): string => number_format($value, 2, ',', '.') . ' ' . $currency;
        $brand = (string) Settings::get('brand.title', 'Evrako');
        $isBank = $reason === 'bank_transfer';

        self::deliver($ownerId, [
            'kind'    => 'system',
            'title'   => $isBank ? 'Ödemeniz onaylandı' : 'Kredi yüklendi',
            'body'    => ($isBank
                    ? $money($amount) . ' tutarındaki havaleniz onaylandı ve hesabınıza aktarıldı. '
                    : $money($amount) . ' kredi hesabınıza eklendi. ')
                . 'Güncel kredi bakiyeniz: ' . $money($balance) . '.',
            'link'    => '/panel/odemelerim',
            'link_label' => 'Ödemelerim',
            'subject' => $brand . ' · ' . ($isBank ? 'Ödemeniz onaylandı' : 'Kredi yüklendi') . ' (' . $money($amount) . ')',
            'heading' => $isBank ? 'Ödemeniz onaylandı' : 'Kredi yüklendi',
            'intro'   => $isBank
                ? 'Havale/EFT bildiriminiz ekibimiz tarafından onaylandı.'
                : 'Hesabınıza yeni kredi eklendi.',
            'lines'   => array_values(array_filter([
                'Eklenen tutar: ' . $money($amount),
                'Güncel bakiye: ' . $money($balance),
                $note !== null && trim($note) !== '' ? 'Açıklama: ' . trim($note) : null,
            ])),
            'cta'     => 'Ödemelerimi gör',
            'cta_url' => '/panel/odemelerim',
            'event'   => 'credit',
            'log_key' => 'account:credit:' . $ownerId . ':' . bin2hex(random_bytes(5)),
        ]);
    }

    /** Abonelik başladı / uzatıldı. */
    public static function subscriptionChanged(int $organizationId, string $planName, string $periodEnd, string $action = 'assigned'): void
    {
        $ownerId = self::ownerOf($organizationId);
        if ($ownerId <= 0) {
            return;
        }
        $brand = (string) Settings::get('brand.title', 'Evrako');
        $extended = $action === 'extended';
        $end = $periodEnd !== '' ? format_date($periodEnd) : '';

        self::deliver($ownerId, [
            'kind'    => 'system',
            'title'   => $extended ? 'Aboneliğiniz uzatıldı' : 'Aboneliğiniz başladı',
            'body'    => ($extended ? 'Aboneliğinizin süresi uzatıldı. ' : 'Aboneliğiniz aktif edildi. ')
                . ($planName !== '' ? 'Paket: ' . $planName . '. ' : '')
                . ($end !== '' ? 'Yeni bitiş tarihi: ' . $end . '.' : ''),
            'link'    => '/panel/odemelerim',
            'link_label' => 'Aboneliğim',
            'subject' => $brand . ' · ' . ($extended ? 'Aboneliğiniz uzatıldı' : 'Aboneliğiniz başladı'),
            'heading' => $extended ? 'Aboneliğiniz uzatıldı' : 'Aboneliğiniz başladı',
            'intro'   => $extended
                ? 'Aboneliğinizin süresi başarıyla uzatıldı.'
                : 'Aboneliğiniz başarıyla aktif edildi.',
            'lines'   => array_values(array_filter([
                $planName !== '' ? 'Paket: ' . $planName : null,
                $end !== '' ? 'Bitiş tarihi: ' . $end : null,
                'Paket detaylarını ve ödeme geçmişinizi panelden görebilirsiniz.',
            ])),
            'cta'     => 'Aboneliğimi gör',
            'cta_url' => '/panel/odemelerim',
            'event'   => 'subscription',
            'log_key' => 'account:subscription:' . $ownerId . ':' . bin2hex(random_bytes(5)),
        ]);
    }

    /**
     * Panel bildirimi + e-posta.
     *
     * @param array{kind?:string,title:string,body:string,link?:string,link_label?:string,subject:string,
     *              heading:string,intro:string,lines:array<int,string>,cta:string,cta_url:string,
     *              event:string,log_key:string} $message
     */
    /** Profil güncelleme talebi onaylandı. */
    public static function profileUpdated(int $userId, array $changes, bool $emailChanged, bool $verificationSent = false): void
    {
        $brand = (string) Settings::get('brand.title', 'Evrako');
        $labels = ['full_name' => 'Ad Soyad', 'email' => 'E-posta', 'phone' => 'Telefon', 'whatsapp' => 'WhatsApp', 'locale' => 'Dil'];
        $lines = [];
        foreach ($changes as $field => $value) {
            if (!isset($labels[$field])) {
                continue;
            }
            $lines[] = $labels[$field] . ': ' . ($value === '' ? '—' : (string) $value);
        }

        self::deliver($userId, [
            'kind'    => 'system',
            'title'   => 'Profil bilgileriniz güncellendi',
            'body'    => 'Yönetici onayıyla profil bilgileriniz güncellendi. '
                . ($emailChanged
                    ? ($verificationSent
                        ? 'Yeni e-posta adresinize doğrulama kodu gönderildi; kodu girmeden panele erişemezsiniz.'
                        : 'Yeni e-posta adresiniz için doğrulama kodu gönderilemedi. Panelden yeniden gönderebilirsiniz.')
                    : 'Değişiklikler hesabınıza uygulandı.'),
            'link'    => '/panel/profil',
            'link_label' => 'Profilim',
            'subject' => $brand . ' · Profil bilgileriniz güncellendi',
            'heading' => 'Profiliniz güncellendi',
            'intro'   => 'Profil güncelleme talebiniz yönetici tarafından onaylandı.',
            'lines'   => $lines,
            'cta'     => 'Profilimi gör',
            'cta_url' => '/panel/profil',
            'event'   => 'profile_updated',
            'log_key' => 'account:profile-updated:' . $userId . ':' . bin2hex(random_bytes(4)),
        ]);
    }

    /** Profil güncelleme talebi reddedildi. */
    public static function profileRejected(int $userId, ?string $note = null): void
    {
        $brand = (string) Settings::get('brand.title', 'Evrako');
        self::deliver($userId, [
            'kind'    => 'system',
            'title'   => 'Profil güncelleme talebiniz reddedildi',
            'body'    => 'Profil bilgilerinizdeki değişiklik talebi yönetici tarafından reddedildi.'
                . ($note !== null && trim($note) !== '' ? ' Neden: ' . trim($note) : '')
                . ' Bilgilerinizi kontrol edip yeni bir talep oluşturabilirsiniz.',
            'link'    => '/panel/profil',
            'link_label' => 'Profilim',
            'subject' => $brand . ' · Profil talebiniz reddedildi',
            'heading' => 'Profil talebiniz reddedildi',
            'intro'   => 'Yönetici profil güncelleme talebinizi onaylamadı.',
            'lines'   => array_values(array_filter([
                $note !== null && trim($note) !== '' ? 'Açıklama: ' . trim($note) : null,
                'Bilgilerinizi kontrol ederek yeni bir talep oluşturabilirsiniz.',
            ])),
            'cta'     => 'Profilime git',
            'cta_url' => '/panel/profil',
            'event'   => 'profile_rejected',
            'log_key' => 'account:profile-rejected:' . $userId . ':' . bin2hex(random_bytes(4)),
        ]);
    }

    private static function deliver(int $userId, array $message): void
    {
        if ($userId <= 0) {
            return;
        }
        $user = Database::first('SELECT id, full_name, email, notify_email, status FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1', [$userId]);
        if ($user === null) {
            return;
        }

        InboxService::create([
            'user_id'    => $userId,
            'kind'       => in_array((string) ($message['kind'] ?? ''), InboxService::KINDS, true) ? (string) $message['kind'] : 'system',
            'title'      => (string) $message['title'],
            'body'       => (string) $message['body'],
            'link_url'   => $message['link'] ?? null,
            'link_label' => $message['link_label'] ?? null,
            'dedupe_key' => (string) $message['log_key'],
        ]);

        if ((int) ($user['notify_email'] ?? 1) !== 1) {
            return;
        }
        if (!filter_var((string) $user['email'], FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $html = self::html($message);
        $text = self::plain($message);

        // Hesap e-postaları kritik değildir: SMTP yavaşsa isteği uzun süre bekletme.
        $mailer = new Mailer(['timeout' => 6]);
        $sendable = $mailer->isConfigured() || (bool) Config::get('mail.dev_log', false);
        $result = $sendable
            ? $mailer->send((string) $user['email'], (string) $message['subject'], $html, $text)
            : ['ok' => false, 'error' => 'SMTP ayarları eksik'];

        self::logEmail($userId, (string) $user['email'], $message, $html, $result);
    }

    /** E-posta günlüğü (panel → Bildirimler). */
    private static function logEmail(int $userId, string $email, array $message, string $html, array $result): void
    {
        try {
            Database::insert('notifications', [
                'user_id'          => $userId,
                'channel'          => 'email',
                'template_key'     => 'account',
                'recipient'        => $email,
                'subject'          => mb_substr((string) $message['subject'], 0, 255),
                'subject_rendered' => mb_substr((string) $message['subject'], 0, 255),
                'body_html'        => $html,
                'status'           => ($result['ok'] ?? false) ? 'sent' : 'failed',
                'dedupe_key'       => (string) $message['log_key'] . ':email',
                'attempts'         => 1,
                'error'            => ($result['ok'] ?? false) ? null : mb_substr((string) ($result['error'] ?? ''), 0, 500),
                'sent_at'          => ($result['ok'] ?? false) ? date('Y-m-d H:i:s') : null,
                'created_at'       => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Log yazılamazsa akışı bozma (ör. migration öncesi).
            Logger::warning('Hesap e-postası loglanamadı: ' . $e->getMessage(), ['user' => $userId]);
        }
    }

    private static function ownerOf(int $organizationId): int
    {
        if ($organizationId <= 0) {
            return 0;
        }
        return (int) Database::scalar('SELECT owner_user_id FROM organizations WHERE id = ?', [$organizationId]);
    }

    /** @param array $message */
    private static function html(array $message): string
    {
        $brand = (string) Settings::get('brand.title', 'Evrako');
        $logo = MailTemplateService::logoUrl();
        $site = url('/');
        $accent = self::accent(true);
        $lines = '';
        foreach ((array) ($message['lines'] ?? []) as $line) {
            $lines .= '<p style="margin:0 0 10px;color:#334155;font-size:15px;line-height:1.65">' . e((string) $line) . '</p>';
        }
        $logoHtml = $logo !== ''
            ? '<img src="' . e($logo) . '" alt="' . e($brand) . '" style="max-height:42px;max-width:220px;display:block">'
            : '<span style="font-size:22px;font-weight:700;color:#0F172A">' . e($brand) . '</span>';

        return '<!doctype html><html lang="tr"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background:#F1F5F9;font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F1F5F9;padding:24px 12px">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #E2E8F0">'
            . '<tr><td style="padding:24px 28px;border-bottom:1px solid #E2E8F0">' . $logoHtml . '</td></tr>'
            . '<tr><td style="padding:28px">'
            . '<h1 style="margin:0 0 12px;font-size:22px;line-height:1.3;color:#0F172A">' . e((string) $message['heading']) . '</h1>'
            . '<p style="margin:0 0 16px;color:#475569;font-size:15px;line-height:1.65">' . e((string) $message['intro']) . '</p>'
            . $lines
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px 0 8px"><tr>'
            . '<td style="border-radius:10px;background:' . e($accent) . '">'
            . '<a href="' . e(url((string) $message['cta_url'])) . '" style="display:inline-block;padding:13px 22px;color:#fff;font-size:15px;font-weight:600;text-decoration:none">'
            . e((string) $message['cta']) . '</a></td></tr></table>'
            . '<p style="margin:16px 0 0;color:#94A3B8;font-size:12.5px;line-height:1.6">Bu e-postayı ' . e($brand) . ' üyesi olduğunuz için aldınız. '
            . 'Bildirim tercihlerinizi panelden değiştirebilirsiniz.</p>'
            . '</td></tr>'
            . '<tr><td style="padding:18px 28px;background:#F8FAFC;border-top:1px solid #E2E8F0;color:#94A3B8;font-size:12px">'
            . '<a href="' . e($site) . '" style="color:#64748B;text-decoration:none">' . e($brand) . '</a>'
            . ' · ' . e(date('Y')) . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    private static function plain(array $message): string
    {
        $parts = [(string) $message['heading'], '', (string) $message['intro'], ''];
        foreach ((array) ($message['lines'] ?? []) as $line) {
            $parts[] = '- ' . (string) $line;
        }
        $parts[] = '';
        $parts[] = (string) $message['cta'] . ': ' . url((string) $message['cta_url']);
        return implode("\n", $parts);
    }

    /**
     * Marka yeşili. Buton zemininde beyaz yazı okunabilsin diye
     * `$darken` true iken renk koyulaştırılır (kontrast ~4.5:1).
     */
    private static function accent(bool $darken = false): string
    {
        $accent = '#00A878';
        $colors = Settings::get('theme.colors', []);
        if (is_array($colors)) {
            $candidate = (string) ($colors['light']['accent'] ?? '');
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $candidate) === 1) {
                $accent = $candidate;
            }
        }
        if (!$darken) {
            return $accent;
        }
        [$r, $g, $b] = [hexdec(substr($accent, 1, 2)), hexdec(substr($accent, 3, 2)), hexdec(substr($accent, 5, 2))];
        $mix = static fn (int $channel, int $target): string => str_pad(dechex((int) round($channel * 0.55 + $target * 0.45)), 2, '0', STR_PAD_LEFT);
        return '#' . $mix($r, 6) . $mix($g, 58) . $mix($b, 45);
    }
}
