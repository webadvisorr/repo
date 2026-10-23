<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Settings;
use App\Core\Translator;

/**
 * Premium (responsive) e-posta şablonları.
 *
 * Şablonlar `mail_templates` tablosunda (key + locale) tutulur ve yönetim panelinden
 * düzenlenir. Kodda yalnızca varsayılanlar bulunur; panelde yapılan değişiklikler
 * korunur (`ensureDefaults` yalnızca eksik olanı ekler).
 *
 * Değişken motoru: {ad}, {plaka}, {evrak}, {bitis}, {kalan_gun}, {link} ...
 */
final class MailTemplateService
{
    public const LOCALES = ['tr', 'en', 'ru', 'ar', 'fa', 'el'];

    /** Desteklenen şablon anahtarları. */
    public const KEYS = ['reminder', 'verification'];

    /** Editörde gösterilen değişken sözlüğü. */
    public const VARIABLES = [
        'ad'              => 'Alıcının adı',
        'eposta'          => 'Alıcının e-posta adresi',
        'kod'             => '6 haneli doğrulama kodu',
        'gecerlilik'      => 'Kod geçerlilik süresi (ör. "2 dakika")',
        'dogrulama_link'  => 'Doğrulama sayfası bağlantısı',
        'plaka'           => 'Araç plakası',
        'arac'            => 'Araç marka / model',
        'evrak'           => 'Evrak türü',
        'bitis'           => 'Bitiş tarihi',
        'kalan_gun'       => 'Kalan süre (kısa: "3 gün", "bugün")',
        'kalan_gun_metin' => 'Kalan süre cümlesi (ör. "3 gün kaldı")',
        'durum_etiket'    => 'Trafik ışığı etiketi',
        'durum_renk'      => 'Durum rengi (hex)',
        'durum_arka'      => 'Durum şeridi arka plan rengi',
        'durum_metin'     => 'Durum şeridi yazı rengi',
        'link'            => 'Panelde evrak bağlantısı',
        'marka_adi'       => 'Marka adı',
        'logo_url'        => 'Logo adresi',
        'site_url'        => 'Site adresi',
        'sirket'          => 'Şirket / ünvan',
        'adres'           => 'Şirket adresi',
        'yil'             => 'İçinde bulunulan yıl',
        'abonelikten_cik' => 'Abonelikten çıkma bağlantısı',
    ];

    /** Durum şeridi renkleri (trafik ışığı). */
    private const STATUS_COLORS = [
        'green'  => ['bar' => '#16A34A', 'bg' => '#E8F7EF', 'text' => '#0F5132'],
        'yellow' => ['bar' => '#F5A524', 'bg' => '#FFF6E5', 'text' => '#7A4A00'],
        'orange' => ['bar' => '#F97316', 'bg' => '#FFF0E6', 'text' => '#7A2E00'],
        'red'    => ['bar' => '#E5484D', 'bg' => '#FDECEC', 'text' => '#7A1216'],
        'none'   => ['bar' => '#94A3B8', 'bg' => '#F1F5F9', 'text' => '#334155'],
    ];

    /**
     * Varsayılan şablonlar: [key][locale] => {name, subject, body_html, body_text}
     * @return array<string,array<string,array<string,string>>>
     */
    public static function defaults(): array
    {
        $out = [];
        foreach (self::KEYS as $key) {
            foreach (self::LOCALES as $locale) {
                $ui = $key === 'verification' ? self::verificationUi($locale) : self::ui($locale);
                $out[$key][$locale] = [
                    'name'      => $ui['name'],
                    'subject'   => $ui['subject'],
                    'body_html' => $key === 'verification'
                        ? self::verificationShell($locale, $ui)
                        : self::shell($locale, $ui),
                    'body_text' => $key === 'verification'
                        ? self::verificationPlain($ui)
                        : self::plain($locale, $ui),
                ];
            }
        }
        return $out;
    }

    /** Eksik şablonları varsayılanlarla doldurur (var olanları korur). */
    public static function ensureDefaults(): int
    {
        $created = 0;
        $now = date('Y-m-d H:i:s');
        foreach (self::defaults() as $key => $locales) {
            foreach ($locales as $locale => $template) {
                $exists = Database::scalar('SELECT id FROM mail_templates WHERE `key` = ? AND locale = ? LIMIT 1', [$key, $locale]);
                if ($exists) {
                    continue;
                }
                try {
                    Database::insert('mail_templates', [
                        'key'            => $key,
                        'locale'         => $locale,
                        'name'           => $template['name'],
                        'subject'        => $template['subject'],
                        'body_html'      => $template['body_html'],
                        'body_text'      => $template['body_text'],
                        'variables_json' => json_encode(array_keys(self::VARIABLES), JSON_UNESCAPED_UNICODE),
                        'is_active'      => 1,
                        'created_at'     => $now,
                    ]);
                    $created++;
                } catch (\Throwable $e) {
                    Logger::warning('Mail şablonu eklenemedi: ' . $e->getMessage(), ['key' => $key, 'locale' => $locale]);
                }
            }
        }
        return $created;
    }

    /** @return array<int,array> Tüm şablon satırları. */
    public static function all(): array
    {
        try {
            return Database::select('SELECT * FROM mail_templates ORDER BY `key` ASC, FIELD(locale, \'tr\',\'en\',\'ru\',\'ar\',\'fa\',\'el\'), id ASC');
        } catch (\Throwable) {
            return [];
        }
    }

    /** Şablonu `key + locale` ile getirir; yoksa Türkçe'ye düşer. */
    public static function find(string $key, string $locale): ?array
    {
        $locale = in_array($locale, self::LOCALES, true) ? $locale : 'tr';
        $row = Database::first('SELECT * FROM mail_templates WHERE `key` = ? AND locale = ? LIMIT 1', [$key, $locale]);
        if ($row === null && $locale !== 'tr') {
            $row = Database::first('SELECT * FROM mail_templates WHERE `key` = ? AND locale = \'tr\' LIMIT 1', [$key]);
        }
        if ($row === null) {
            $defaults = self::defaults();
            if (isset($defaults[$key][$locale])) {
                return [
                    'id'         => 0,
                    'key'        => $key,
                    'locale'     => $locale,
                    'is_active'  => 1,
                    'name'       => $defaults[$key][$locale]['name'],
                    'subject'    => $defaults[$key][$locale]['subject'],
                    'body_html'  => $defaults[$key][$locale]['body_html'],
                    'body_text'  => $defaults[$key][$locale]['body_text'],
                    'updated_at' => null,
                ];
            }
        }
        return $row;
    }

    /**
     * Şablonu değişkenlerle işler.
     * @param array<string,string|int|null> $vars
     * @return array{ok:bool,subject:string,html:string,text:string,error?:string}
     */
    public static function render(string $key, string $locale, array $vars): array
    {
        $template = self::find($key, $locale);
        if ($template === null) {
            return ['ok' => false, 'subject' => '', 'html' => '', 'text' => '', 'error' => 'Şablon bulunamadı: ' . $key];
        }

        $replace = [];
        foreach (self::VARIABLES as $name => $label) {
            $value = $vars[$name] ?? '';
            $replace['{' . $name . '}'] = htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $subject = strtr((string) $template['subject'], $replace);
        // Konu başlığında HTML kaçışı istemiyoruz.
        $subject = html_entity_decode($subject, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $subject = trim(preg_replace('/\s+/', ' ', $subject) ?? $subject);

        $html = strtr((string) $template['body_html'], $replace);
        // Logo tanımlı değilse kırık görsel göstermeyelim (başlıkta marka adı zaten yazıyor).
        if (trim((string) ($vars['logo_url'] ?? '')) === '') {
            $html = (string) preg_replace('/<img[^>]*src=""[^>]*>/i', '', $html);
        }

        return [
            'ok'      => true,
            'subject' => $subject,
            'html'    => $html,
            'text'    => strtr((string) ($template['body_text'] ?? ''), $replace),
        ];
    }

    /** Önizleme/test için örnek değerler. */
    public static function sampleVars(?array $user = null, ?array $document = null, ?array $vehicle = null, string $locale = 'tr'): array
    {
        $brand = (string) Settings::get('brand.title', 'Evrako');
        $plate = (string) ($vehicle['plate'] ?? 'KZ 123 A');
        $typeLabel = label((string) ($document['type_labels'] ?? ''), $locale);
        if ($typeLabel === '') {
            $typeLabel = t('documents.title');
        }

        return [
            'ad'              => (string) ($user['full_name'] ?? 'Ahmet Yılmaz'),
            'eposta'          => (string) ($user['email'] ?? 'ornek@example.com'),
            'kod'             => '482913',
            'gecerlilik'      => '2 dakika',
            'dogrulama_link'  => url('/dogrula'),
            'plaka'           => $plate,
            'arac'            => trim((string) ($vehicle['brand'] ?? 'Toyota') . ' ' . (string) ($vehicle['model'] ?? 'Corolla')),
            'evrak'           => $typeLabel,
            'bitis'           => format_date((string) ($document['end_date'] ?? date('Y-m-d', strtotime('+3 days')))),
            'kalan_gun'       => self::durationPhrase(3, $locale),
            'kalan_gun_metin' => t('documents.days_left', ['days' => 3]),
            'durum_etiket'    => t('vehicles.light_orange'),
            'durum_renk'      => self::STATUS_COLORS['orange']['bar'],
            'durum_arka'      => self::STATUS_COLORS['orange']['bg'],
            'durum_metin'     => self::STATUS_COLORS['orange']['text'],
            'link'            => url('/panel/takvim'),
            'marka_adi'       => $brand,
            'logo_url'        => self::logoUrl(),
            'site_url'        => url('/'),
            'sirket'          => (string) Settings::get('brand.company', $brand),
            'adres'           => trim((string) Settings::get('brand.address', '') . ' ' . (string) Settings::get('brand.city', '') . ' ' . (string) Settings::get('brand.country', '')),
            'yil'             => date('Y'),
            'abonelikten_cik' => self::unsubscribeUrl('ornek@example.com'),
        ];
    }

    /**
     * Kalan günü kısa bir süre ifadesine çevirir (dile göre): "3 gün", "today", "2 дня".
     */
    public static function durationPhrase(int $days, string $locale): string
    {
        $patterns = [
            'tr' => ['today' => 'bugün', 'one' => '1 gün', 'many' => '{n} gün', 'over' => '{n} gün gecikme'],
            'en' => ['today' => 'today', 'one' => '1 day', 'many' => '{n} days', 'over' => '{n} days overdue'],
            'ru' => ['today' => 'сегодня', 'one' => '1 день', 'many' => '{n} дней', 'over' => 'просрочено на {n} дней'],
            'ar' => ['today' => 'اليوم', 'one' => 'يوم واحد', 'many' => '{n} أيام', 'over' => 'متأخر {n} أيام'],
            'fa' => ['today' => 'امروز', 'one' => '۱ روز', 'many' => '{n} روز', 'over' => '{n} روز تأخیر'],
            'el' => ['today' => 'σήμερα', 'one' => '1 ημέρα', 'many' => '{n} ημέρες', 'over' => '{n} ημέρες καθυστέρηση'],
        ];
        $set = $patterns[$locale] ?? $patterns['tr'];
        if ($days === 0) {
            return $set['today'];
        }
        if ($days < 0) {
            return str_replace('{n}', (string) abs($days), $set['over']);
        }
        return $days === 1 ? $set['one'] : str_replace('{n}', (string) $days, $set['many']);
    }

    /** Durum şeridi renkleri. */
    public static function statusColors(string $light): array
    {
        return self::STATUS_COLORS[$light] ?? self::STATUS_COLORS['none'];
    }

    /** Marka logosunun mutlak adresi (yoksa boş). */
    public static function logoUrl(): string
    {
        $logo = (string) Settings::get('brand.logo_light', '');
        if ($logo === '') {
            $logo = (string) Settings::get('brand.logo_dark', '');
        }
        return $logo === '' ? '' : media_url($logo);
    }

    /* ---------------- Abonelikten çıkma (stateless imzalı bağlantı) ---------------- */

    public static function unsubscribeToken(string $email): string
    {
        return hash_hmac('sha256', 'unsub:' . mb_strtolower(trim($email)), (string) Config::get('app.key', 'evrako'));
    }

    public static function unsubscribeUrl(string $email): string
    {
        return url('/abonelikten-cikar?' . http_build_query([
            'e' => rtrim(strtr(base64_encode(mb_strtolower(trim($email))), '+/', '-_'), '='),
            't' => self::unsubscribeToken($email),
        ], '', '&', PHP_QUERY_RFC3986));
    }

    /** Tek tık (List-Unsubscribe-Post) için imzasız gövde bağlantısı. */
    public static function oneClickUrl(string $email): string
    {
        return url('/abonelikten-cikar/tek-tik?' . http_build_query([
            'e' => rtrim(strtr(base64_encode(mb_strtolower(trim($email))), '+/', '-_'), '='),
            't' => self::unsubscribeToken($email),
        ], '', '&', PHP_QUERY_RFC3986));
    }

    /** İmzalı bağlantıdan e-posta adresini çözer (geçersizse null). */
    public static function emailFromToken(string $encodedEmail, string $token): ?string
    {
        $padded = strtr($encodedEmail, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
        $decoded = base64_decode($padded, true);
        if (!is_string($decoded) || $decoded === '' || filter_var($decoded, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }
        return hash_equals(self::unsubscribeToken($decoded), $token) ? $decoded : null;
    }

    public static function isUnsubscribed(string $email): bool
    {
        try {
            return (bool) Database::scalar('SELECT id FROM unsubscribes WHERE email = ? LIMIT 1', [mb_strtolower(trim($email))]);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function unsubscribe(string $email, string $scope = 'all', string $reason = 'email_link'): void
    {
        $email = mb_strtolower(trim($email));
        $exists = Database::first('SELECT id, scope FROM unsubscribes WHERE email = ? LIMIT 1', [$email]);
        if ($exists !== null) {
            Database::update('unsubscribes', [
                'scope'  => $scope === 'all' ? 'all' : (string) $exists['scope'],
                'reason' => $reason,
            ], 'id = :id', ['id' => (int) $exists['id']]);
            return;
        }
        Database::insert('unsubscribes', [
            'email'      => $email,
            'reason'     => mb_substr($reason, 0, 190),
            'scope'      => $scope === 'all' ? 'all' : 'marketing',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function resubscribe(string $email): void
    {
        Database::delete('unsubscribes', 'email = ?', [mb_strtolower(trim($email))]);
    }

    /* ---------------- E-posta doğrulama şablonu ---------------- */

    /** @return array<string,string> */
    private static function verificationUi(string $locale): array
    {
        $ui = [
            'tr' => [
                'name'        => 'E-posta doğrulama',
                'subject'     => '{marka_adi} · Doğrulama kodunuz: {kod}',
                'preheader'   => 'Doğrulama kodunuz {gecerlilik} geçerlidir.',
                'heading'     => 'E-postanızı doğrulayın',
                'greeting'    => 'Merhaba {ad},',
                'intro'       => 'Hesabınızı güvenle etkinleştirmek için aşağıdaki 6 haneli kodu girin.',
                'code_label'  => 'Doğrulama kodunuz',
                'validity'    => 'Kod {gecerlilik} geçerlidir.',
                'cta'         => 'Doğrulama sayfasına git',
                'security'    => 'Bu kodu kimseyle paylaşmayın. Kodu siz istemediyseniz bu e-postayı yok sayabilirsiniz.',
                'footer'      => 'Bu e-posta, {site_url} adresindeki hesabınızı doğrulamak için gönderilmiştir.',
                'rights'      => 'Tüm hakları saklıdır.',
                'text_intro'  => 'Hesabınızı etkinleştirmek için doğrulama kodunuz',
            ],
            'en' => [
                'name'        => 'E-mail verification',
                'subject'     => '{marka_adi} · Your verification code: {kod}',
                'preheader'   => 'Your verification code is valid for {gecerlilik}.',
                'heading'     => 'Verify your e-mail',
                'greeting'    => 'Hello {ad},',
                'intro'       => 'Enter the 6-digit code below to activate your account securely.',
                'code_label'  => 'Your verification code',
                'validity'    => 'The code is valid for {gecerlilik}.',
                'cta'         => 'Go to the verification page',
                'security'    => 'Never share this code. If you did not request it, you can ignore this e-mail.',
                'footer'      => 'This e-mail was sent to verify your account at {site_url}.',
                'rights'      => 'All rights reserved.',
                'text_intro'  => 'Your verification code to activate your account is',
            ],
            'ru' => [
                'name'        => 'Подтверждение e-mail',
                'subject'     => '{marka_adi} · Ваш код подтверждения: {kod}',
                'preheader'   => 'Код подтверждения действителен {gecerlilik}.',
                'heading'     => 'Подтвердите e-mail',
                'greeting'    => 'Здравствуйте, {ad}!',
                'intro'       => 'Введите 6-значный код ниже, чтобы безопасно активировать аккаунт.',
                'code_label'  => 'Ваш код подтверждения',
                'validity'    => 'Код действителен {gecerlilik}.',
                'cta'         => 'Перейти на страницу подтверждения',
                'security'    => 'Никому не сообщайте этот код. Если вы не запрашивали его, просто проигнорируйте письмо.',
                'footer'      => 'Это письмо отправлено для подтверждения аккаунта на {site_url}.',
                'rights'      => 'Все права защищены.',
                'text_intro'  => 'Код для активации аккаунта',
            ],
            'ar' => [
                'name'        => 'تأكيد البريد الإلكتروني',
                'subject'     => '{marka_adi} · رمز التحقق الخاص بك: {kod}',
                'preheader'   => 'رمز التحقق صالح لمدة {gecerlilik}.',
                'heading'     => 'أكد بريدك الإلكتروني',
                'greeting'    => 'مرحبًا {ad}،',
                'intro'       => 'أدخل الرمز المكون من 6 أرقام أدناه لتنشيط حسابك بأمان.',
                'code_label'  => 'رمز التحقق الخاص بك',
                'validity'    => 'الرمز صالح لمدة {gecerlilik}.',
                'cta'         => 'انتقل إلى صفحة التحقق',
                'security'    => 'لا تشارك هذا الرمز مع أي شخص. إذا لم تطلبه، يمكنك تجاهل هذه الرسالة.',
                'footer'      => 'أُرسلت هذه الرسالة لتأكيد حسابك على {site_url}.',
                'rights'      => 'جميع الحقوق محفوظة.',
                'text_intro'  => 'رمز تفعيل حسابك هو',
            ],
            'fa' => [
                'name'        => 'تأیید ایمیل',
                'subject'     => '{marka_adi} · کد تأیید شما: {kod}',
                'preheader'   => 'کد تأیید شما {gecerlilik} اعتبار دارد.',
                'heading'     => 'ایمیل خود را تأیید کنید',
                'greeting'    => 'سلام {ad}،',
                'intro'       => 'برای فعال‌سازی امن حساب، کد ۶ رقمی زیر را وارد کنید.',
                'code_label'  => 'کد تأیید شما',
                'validity'    => 'کد {gecerlilik} اعتبار دارد.',
                'cta'         => 'رفتن به صفحه تأیید',
                'security'    => 'این کد را با کسی به اشتراک نگذارید. اگر آن را درخواست نکرده‌اید، این ایمیل را نادیده بگیرید.',
                'footer'      => 'این ایمیل برای تأیید حساب شما در {site_url} ارسال شده است.',
                'rights'      => 'تمام حقوق محفوظ است.',
                'text_intro'  => 'کد فعال‌سازی حساب شما',
            ],
            'el' => [
                'name'        => 'Επαλήθευση e-mail',
                'subject'     => '{marka_adi} · Ο κωδικός επαλήθευσής σας: {kod}',
                'preheader'   => 'Ο κωδικός επαλήθευσης ισχύει για {gecerlilik}.',
                'heading'     => 'Επαληθεύστε το e-mail σας',
                'greeting'    => 'Γεια σας, {ad}!',
                'intro'       => 'Εισαγάγετε τον 6ψήφιο κωδικό παρακάτω για να ενεργοποιήσετε με ασφάλεια τον λογαριασμό σας.',
                'code_label'  => 'Ο κωδικός επαλήθευσής σας',
                'validity'    => 'Ο κωδικός ισχύει για {gecerlilik}.',
                'cta'         => 'Μετάβαση στη σελίδα επαλήθευσης',
                'security'    => 'Μην κοινοποιήσετε ποτέ αυτόν τον κωδικό. Αν δεν τον ζητήσατε, αγνοήστε αυτό το e-mail.',
                'footer'      => 'Αυτό το e-mail στάλθηκε για την επαλήθευση του λογαριασμού σας στο {site_url}.',
                'rights'      => 'Με την επιφύλαξη παντός δικαιώματος.',
                'text_intro'  => 'Ο κωδικός ενεργοποίησης του λογαριασμού σας είναι',
            ],
        ];

        return $ui[$locale] ?? $ui['tr'];
    }

    /** Doğrulama e-postası: kod odaklı, responsive, marka renkleriyle uyumlu. */
    private static function verificationShell(string $locale, array $t): string
    {
        $dir = Translator::isRtl($locale) ? 'rtl' : 'ltr';
        $align = $dir === 'rtl' ? 'right' : 'left';
        $colors = (array) Settings::get('theme.colors', []);
        $light = is_array($colors['light'] ?? null) ? $colors['light'] : [];
        $primary = self::themeColor((string) ($light['primary'] ?? ''), '#0A1D3A');
        $accent = self::themeColor((string) ($light['accent'] ?? ''), '#00C48C');
        $bg = self::themeColor((string) ($light['bg'] ?? ''), '#EEF2F7');
        $card = self::themeColor((string) ($light['card'] ?? ''), '#FFFFFF');
        $text = self::themeColor((string) ($light['text'] ?? ''), '#0F172A');
        $muted = self::themeColor((string) ($light['muted'] ?? ''), '#64748B');
        $border = self::themeColor((string) ($light['border'] ?? ''), '#E3E9F2');

        $html = <<<'HTML'
<!doctype html>
<html lang="[[locale]]" dir="[[dir]]">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>[[heading]]</title>
</head>
<body style="margin:0;padding:0;background:[[bg]];">
<div style="display:none;font-size:1px;color:[[bg]];line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">[[preheader]]</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:[[bg]];padding:28px 12px;">
<tr><td align="center">
  <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:[[card]];border-radius:18px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;color:[[text]];">
    <tr>
      <td style="padding:22px 30px;background:[[primary]];text-align:[[align]];">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
          <td style="color:#ffffff;font-size:20px;font-weight:bold;letter-spacing:-0.3px;text-align:[[align]];">[[brand]]</td>
          <td style="text-align:[[align]];">[[logo]]</td>
        </tr></table>
      </td>
    </tr>
    <tr><td style="height:5px;background:[[accent]];font-size:0;line-height:0;">&nbsp;</td></tr>
    <tr>
      <td style="padding:32px 30px 8px;text-align:[[align]];">
        <div style="width:48px;height:48px;line-height:48px;border-radius:14px;background:[[accent]];color:[[primary]];font-size:24px;text-align:center;margin-bottom:18px;">&#9993;</div>
        <h1 style="margin:0 0 14px;font-size:23px;line-height:1.3;color:[[text]];">[[heading]]</h1>
        <p style="margin:0 0 10px;font-size:15px;line-height:1.6;color:[[text]];">[[greeting]]</p>
        <p style="margin:0;font-size:15px;line-height:1.65;color:[[muted]];">[[intro]]</p>
      </td>
    </tr>
    <tr>
      <td style="padding:24px 30px 8px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:[[bg]];border:1px dashed [[border]];border-radius:14px;">
          <tr><td align="center" style="padding:18px 16px 6px;font-size:11px;letter-spacing:1.2px;text-transform:uppercase;color:[[muted]];">[[code_label]]</td></tr>
          <tr><td align="center" style="padding:0 16px 12px;font-family:'Courier New',Courier,monospace;font-size:34px;line-height:1.2;font-weight:bold;letter-spacing:9px;color:[[primary]];">{kod}</td></tr>
          <tr><td align="center" style="padding:0 16px 16px;font-size:12.5px;color:[[muted]];">[[validity]]</td></tr>
        </table>
      </td>
    </tr>
    <tr>
      <td align="center" style="padding:22px 30px 8px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
          <td style="border-radius:999px;background:[[primary]];">
            <a href="{dogrulama_link}" style="display:inline-block;padding:14px 30px;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:999px;">[[cta]]</a>
          </td>
        </tr></table>
        <p style="margin:12px 0 0;font-size:12px;line-height:1.6;color:[[muted]];word-break:break-all;">{dogrulama_link}</p>
      </td>
    </tr>
    <tr>
      <td style="padding:18px 30px 0;text-align:[[align]];">
        <p style="margin:0;font-size:12.5px;line-height:1.7;color:[[muted]];">&#128274; [[security]]</p>
      </td>
    </tr>
    <tr>
      <td style="padding:20px 30px 28px;">
        <hr style="border:none;border-top:1px solid [[border]];margin:0 0 14px;">
        <p style="margin:0 0 6px;font-size:12px;line-height:1.6;color:[[muted]];text-align:[[align]];">{sirket} &middot; {adres}</p>
        <p style="margin:0;font-size:12px;line-height:1.6;color:[[muted]];text-align:[[align]];">[[footer]]</p>
        <p style="margin:10px 0 0;font-size:11px;color:[[muted]];text-align:[[align]];">&copy; {yil} {marka_adi} &mdash; [[rights]]</p>
      </td>
    </tr>
  </table>
</td></tr>
</table>
</body>
</html>
HTML;

        return strtr($html, [
            '[[locale]]'    => $locale,
            '[[dir]]'       => $dir,
            '[[align]]'     => $align,
            '[[bg]]'        => $bg,
            '[[card]]'      => $card,
            '[[primary]]'   => $primary,
            '[[accent]]'    => $accent,
            '[[text]]'      => $text,
            '[[muted]]'     => $muted,
            '[[border]]'    => $border,
            '[[brand]]'     => '{marka_adi}',
            '[[logo]]'      => self::logoTag(),
            '[[heading]]'   => htmlspecialchars($t['heading'], ENT_QUOTES, 'UTF-8'),
            '[[preheader]]' => htmlspecialchars($t['preheader'], ENT_QUOTES, 'UTF-8'),
            '[[greeting]]'  => htmlspecialchars($t['greeting'], ENT_QUOTES, 'UTF-8'),
            '[[intro]]'     => htmlspecialchars($t['intro'], ENT_QUOTES, 'UTF-8'),
            '[[code_label]]' => htmlspecialchars($t['code_label'], ENT_QUOTES, 'UTF-8'),
            '[[validity]]'  => htmlspecialchars($t['validity'], ENT_QUOTES, 'UTF-8'),
            '[[cta]]'       => htmlspecialchars($t['cta'], ENT_QUOTES, 'UTF-8'),
            '[[security]]'  => htmlspecialchars($t['security'], ENT_QUOTES, 'UTF-8'),
            '[[footer]]'    => htmlspecialchars($t['footer'], ENT_QUOTES, 'UTF-8'),
            '[[rights]]'    => htmlspecialchars($t['rights'], ENT_QUOTES, 'UTF-8'),
        ]);
    }

    private static function verificationPlain(array $t): string
    {
        return implode("\n", [
            '{marka_adi}',
            '',
            $t['heading'],
            '',
            $t['greeting'],
            $t['text_intro'] . ': {kod}',
            $t['validity'],
            '',
            $t['cta'] . ': {dogrulama_link}',
            '',
            $t['security'],
            '{sirket} · {adres}',
            $t['footer'],
        ]);
    }

    /** Geçerli bir hex renk değilse güvenli varsayılana döner. */
    private static function themeColor(string $value, string $fallback): string
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1 ? strtoupper($value) : $fallback;
    }

    /* ---------------- Varsayılan gövde iskeleti ---------------- */

    /**
     * Responsive (tablo tabanlı, satır içi stilli) e-posta gövdesi.
     * Metinler dil bazlı olarak gömülür; dinamik kısımlar {değişken} olarak kalır.
     */
    private static function shell(string $locale, array $t): string
    {
        $dir = Translator::isRtl($locale) ? 'rtl' : 'ltr';
        $align = $dir === 'rtl' ? 'right' : 'left';

        $html = <<<'HTML'
<!doctype html>
<html lang="[[locale]]" dir="[[dir]]">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>[[heading]]</title>
</head>
<body style="margin:0;padding:0;background:#eef2f7;">
<div style="display:none;font-size:1px;color:#eef2f7;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">[[preheader]]</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef2f7;padding:24px 12px;">
<tr><td align="center">
  <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <tr>
      <td style="padding:20px 28px;background:#0a1d3a;text-align:[[align]];">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
          <td style="color:#ffffff;font-size:20px;font-weight:bold;letter-spacing:-0.3px;text-align:[[align]];">[[brand]]</td>
          <td style="text-align:[[align]];">[[logo]]</td>
        </tr></table>
      </td>
    </tr>
    <tr><td style="height:6px;background:{durum_renk};font-size:0;line-height:0;">&nbsp;</td></tr>
    <tr>
      <td style="padding:10px 28px;background:{durum_arka};color:{durum_metin};font-size:13px;font-weight:bold;text-align:[[align]];">
        &#9679; {durum_etiket}
      </td>
    </tr>
    <tr>
      <td style="padding:26px 28px 4px;text-align:[[align]];">
        <h1 style="margin:0 0 14px;font-size:20px;line-height:1.35;color:#0f172a;">[[heading]]</h1>
        <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#334155;">[[greeting]]</p>
        <p style="margin:0 0 6px;font-size:15px;line-height:1.65;color:#334155;">[[intro]]</p>
      </td>
    </tr>
    <tr>
      <td style="padding:14px 28px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e3e9f2;border-radius:12px;background:#ffffff;">
          <tr><td style="padding:14px 16px;background:#f7f9fc;border-bottom:1px solid #e3e9f2;font-size:15px;font-weight:bold;color:#0f172a;text-align:[[align]];">&#128663; {plaka}</td></tr>
          <tr><td style="padding:12px 16px;font-size:14px;color:#334155;text-align:[[align]];">[[lbl_arac]]: <strong>{arac}</strong></td></tr>
          <tr><td style="padding:0 16px 12px;font-size:14px;color:#334155;text-align:[[align]];">[[lbl_evrak]]: <strong>{evrak}</strong></td></tr>
          <tr><td style="padding:0 16px 12px;font-size:14px;color:#334155;text-align:[[align]];">[[lbl_bitis]]: <strong>{bitis}</strong></td></tr>
          <tr><td style="padding:0 16px 14px;font-size:14px;color:{durum_metin};text-align:[[align]];">[[lbl_kalan]]: <strong>{kalan_gun_metin}</strong></td></tr>
        </table>
      </td>
    </tr>
    <tr>
      <td align="center" style="padding:24px 28px 6px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
          <td style="border-radius:999px;background:#00c48c;">
            <a href="{link}" style="display:inline-block;padding:14px 30px;font-size:15px;font-weight:bold;color:#04231a;text-decoration:none;border-radius:999px;">[[cta]]</a>
          </td>
        </tr></table>
        <p style="margin:12px 0 0;font-size:12px;line-height:1.6;color:#6b7c93;word-break:break-all;">[[cta_alt]]<br>{link}</p>
      </td>
    </tr>
    <tr>
      <td style="padding:18px 28px 0;">
        <p style="margin:0;font-size:12px;line-height:1.7;color:#6b7c93;text-align:[[align]];">[[kvkk]]</p>
      </td>
    </tr>
    <tr>
      <td style="padding:18px 28px 26px;">
        <hr style="border:none;border-top:1px solid #e3e9f2;margin:0 0 14px;">
        <p style="margin:0 0 6px;font-size:12px;line-height:1.6;color:#6b7c93;text-align:[[align]];">{sirket} &middot; {adres}</p>
        <p style="margin:0;font-size:12px;line-height:1.6;color:#6b7c93;text-align:[[align]];">[[unsub_text]] <a href="{abonelikten_cik}" style="color:#3b82f6;text-decoration:underline;">[[unsub]]</a></p>
        <p style="margin:10px 0 0;font-size:11px;color:#9db4d0;text-align:[[align]];">&copy; {yil} {marka_adi} &mdash; [[rights]]</p>
      </td>
    </tr>
  </table>
</td></tr>
</table>
</body>
</html>
HTML;

        return strtr($html, [
            '[[locale]]'    => $locale,
            '[[dir]]'       => $dir,
            '[[align]]'     => $align,
            '[[heading]]'   => htmlspecialchars($t['heading'], ENT_QUOTES, 'UTF-8'),
            '[[preheader]]' => htmlspecialchars($t['preheader'], ENT_QUOTES, 'UTF-8'),
            '[[brand]]'     => '{marka_adi}',
            '[[logo]]'      => self::logoTag(),
            '[[greeting]]'  => htmlspecialchars($t['greeting'], ENT_QUOTES, 'UTF-8'),
            '[[intro]]'     => $t['intro'],
            '[[lbl_arac]]'  => htmlspecialchars($t['lbl_arac'], ENT_QUOTES, 'UTF-8'),
            '[[lbl_evrak]]' => htmlspecialchars($t['lbl_evrak'], ENT_QUOTES, 'UTF-8'),
            '[[lbl_bitis]]' => htmlspecialchars($t['lbl_bitis'], ENT_QUOTES, 'UTF-8'),
            '[[lbl_kalan]]' => htmlspecialchars($t['lbl_kalan'], ENT_QUOTES, 'UTF-8'),
            '[[cta]]'       => htmlspecialchars($t['cta'], ENT_QUOTES, 'UTF-8'),
            '[[cta_alt]]'   => htmlspecialchars($t['cta_alt'], ENT_QUOTES, 'UTF-8'),
            '[[kvkk]]'      => htmlspecialchars($t['kvkk'], ENT_QUOTES, 'UTF-8'),
            '[[unsub_text]]' => htmlspecialchars($t['unsub_text'], ENT_QUOTES, 'UTF-8'),
            '[[unsub]]'     => htmlspecialchars($t['unsub'], ENT_QUOTES, 'UTF-8'),
            '[[rights]]'    => htmlspecialchars($t['rights'], ENT_QUOTES, 'UTF-8'),
        ]);
    }

    /** Logo varsa <img>, yoksa marka adı yazısı döner (e-posta istemcileri için). */
    private static function logoTag(): string
    {
        return '<img src="{logo_url}" alt="{marka_adi}" height="30" style="height:30px;max-height:30px;display:inline-block;border:0;outline:none;text-decoration:none;">';
    }

    /** Düz metin alternatifi (spam filtreleri ve metin istemcileri için). */
    private static function plain(string $locale, array $t): string
    {
        return implode("\n", [
            '{marka_adi}',
            '',
            $t['heading'],
            '',
            $t['greeting'],
            $t['intro_plain'],
            '',
            $t['lbl_arac'] . ': {arac}',
            $t['lbl_evrak'] . ': {evrak}',
            $t['lbl_bitis'] . ': {bitis}',
            $t['lbl_kalan'] . ': {kalan_gun_metin}',
            '{durum_etiket}',
            '',
            $t['cta'] . ': {link}',
            '',
            $t['kvkk'],
            $t['unsub_text'] . ' {abonelikten_cik}',
            '{sirket} · {adres}',
        ]);
    }

    /**
     * Dil bazlı arayüz metinleri.
     * @return array<string,string>
     */
    private static function ui(string $locale): array
    {
        $ui = [
            'tr' => [
                'name'        => 'Evrak bitiş hatırlatması',
                'subject'     => '{plaka} · {evrak} evrakınızın süresi {kalan_gun} içinde doluyor',
                'preheader'   => '{plaka} plakalı aracınızın {evrak} evrakı için hatırlatma',
                'heading'     => 'Evrak bitiş hatırlatması',
                'greeting'    => 'Merhaba {ad},',
                'intro'       => '<strong>{plaka}</strong> plakalı aracınızın <strong>{evrak}</strong> evrakının bitiş tarihi yaklaşıyor. Süresi dolmadan yenilemeyi unutmayın.',
                'intro_plain' => '{plaka} plakalı aracınızın {evrak} evrakının bitiş tarihi yaklaşıyor.',
                'lbl_arac'    => 'Araç',
                'lbl_evrak'   => 'Evrak',
                'lbl_bitis'   => 'Bitiş tarihi',
                'lbl_kalan'   => 'Kalan süre',
                'cta'         => 'Evrağı panelde görüntüle',
                'cta_alt'     => 'Buton çalışmıyorsa bu bağlantıyı tarayıcınıza kopyalayın:',
                'kvkk'        => 'Bu e-posta, evrako üzerinden kayıtlı araç evrağınız için otomatik olarak gönderilmiştir. Kişisel verileriniz KVKK kapsamında yalnızca hatırlatma amacıyla işlenir ve üçüncü taraflarla paylaşılmaz.',
                'unsub_text'  => 'Bu e-postaları almak istemiyorsanız',
                'unsub'       => 'abonelikten çıkabilirsiniz',
                'rights'      => 'Tüm hakları saklıdır.',
            ],
            'en' => [
                'name'        => 'Document expiry reminder',
                'subject'     => '{plaka} · your {evrak} expires in {kalan_gun}',
                'preheader'   => 'Reminder for the {evrak} of vehicle {plaka}',
                'heading'     => 'Document expiry reminder',
                'greeting'    => 'Hello {ad},',
                'intro'       => 'The <strong>{evrak}</strong> document of your vehicle <strong>{plaka}</strong> is about to expire. Please renew it in time.',
                'intro_plain' => 'The {evrak} document of your vehicle {plaka} is about to expire.',
                'lbl_arac'    => 'Vehicle',
                'lbl_evrak'   => 'Document',
                'lbl_bitis'   => 'Expiry date',
                'lbl_kalan'   => 'Time left',
                'cta'         => 'View the document in the panel',
                'cta_alt'     => 'If the button does not work, copy this link into your browser:',
                'kvkk'        => 'This e-mail was sent automatically for the vehicle document registered in evrako. Your personal data is processed solely for reminder purposes and is never shared with third parties.',
                'unsub_text'  => 'If you no longer want these e-mails,',
                'unsub'       => 'unsubscribe here',
                'rights'      => 'All rights reserved.',
            ],
            'ru' => [
                'name'        => 'Напоминание об окончании документа',
                'subject'     => '{plaka} · срок действия {evrak} истекает через {kalan_gun}',
                'preheader'   => 'Напоминание о документе {evrak} для автомобиля {plaka}',
                'heading'     => 'Напоминание об окончании документа',
                'greeting'    => 'Здравствуйте, {ad}!',
                'intro'       => 'Срок действия документа <strong>{evrak}</strong> вашего автомобиля <strong>{plaka}</strong> подходит к концу. Пожалуйста, продлите его вовремя.',
                'intro_plain' => 'Срок действия документа {evrak} вашего автомобиля {plaka} подходит к концу.',
                'lbl_arac'    => 'Автомобиль',
                'lbl_evrak'   => 'Документ',
                'lbl_bitis'   => 'Дата окончания',
                'lbl_kalan'   => 'Осталось',
                'cta'         => 'Открыть документ в панели',
                'cta_alt'     => 'Если кнопка не работает, скопируйте ссылку в браузер:',
                'kvkk'        => 'Это письмо отправлено автоматически для документа, зарегистрированного в evrako. Ваши персональные данные обрабатываются только для напоминаний и не передаются третьим лицам.',
                'unsub_text'  => 'Если вы больше не хотите получать эти письма,',
                'unsub'       => 'отпишитесь здесь',
                'rights'      => 'Все права защищены.',
            ],
            'ar' => [
                'name'        => 'تذكير بانتهاء المستند',
                'subject'     => '{plaka} · ينتهي {evrak} خلال {kalan_gun}',
                'preheader'   => 'تذكير بخصوص {evrak} للمركبة {plaka}',
                'heading'     => 'تذكير بانتهاء المستند',
                'greeting'    => 'مرحباً {ad}،',
                'intro'       => 'يقترب تاريخ انتهاء <strong>{evrak}</strong> للمركبة <strong>{plaka}</strong>. يرجى التجديد قبل انتهاء المدة.',
                'intro_plain' => 'يقترب تاريخ انتهاء {evrak} للمركبة {plaka}.',
                'lbl_arac'    => 'المركبة',
                'lbl_evrak'   => 'المستند',
                'lbl_bitis'   => 'تاريخ الانتهاء',
                'lbl_kalan'   => 'الوقت المتبقي',
                'cta'         => 'عرض المستند في اللوحة',
                'cta_alt'     => 'إذا لم يعمل الزر، انسخ هذا الرابط في المتصفح:',
                'kvkk'        => 'تم إرسال هذا البريد تلقائياً لمستند مركبتك المسجّل في evrako. تُعالج بياناتك الشخصية لغرض التذكير فقط ولا تُشارك مع أي طرف ثالث.',
                'unsub_text'  => 'إذا لم ترغب في تلقي هذه الرسائل،',
                'unsub'       => 'يمكنك إلغاء الاشتراك',
                'rights'      => 'جميع الحقوق محفوظة.',
            ],
            'fa' => [
                'name'        => 'یادآوری پایان اعتبار سند',
                'subject'     => '{plaka} · تا {kalan_gun} دیگر {evrak} منقضی می‌شود',
                'preheader'   => 'یادآوری برای {evrak} خودروی {plaka}',
                'heading'     => 'یادآوری پایان اعتبار سند',
                'greeting'    => 'سلام {ad}،',
                'intro'       => 'تاریخ انقضای <strong>{evrak}</strong> خودروی <strong>{plaka}</strong> نزدیک است. لطفاً پیش از پایان مهلت آن را تمدید کنید.',
                'intro_plain' => 'تاریخ انقضای {evrak} خودروی {plaka} نزدیک است.',
                'lbl_arac'    => 'خودرو',
                'lbl_evrak'   => 'سند',
                'lbl_bitis'   => 'تاریخ انقضا',
                'lbl_kalan'   => 'زمان باقی‌مانده',
                'cta'         => 'مشاهده سند در پنل',
                'cta_alt'     => 'اگر دکمه کار نمی‌کند، این پیوند را در مرورگر باز کنید:',
                'kvkk'        => 'این ایمیل به‌صورت خودکار برای سند خودروی ثبت‌شده در evrako ارسال شده است. اطلاعات شخصی شما تنها برای یادآوری پردازش می‌شود و با اشخاص ثالث به اشتراک گذاشته نمی‌شود.',
                'unsub_text'  => 'اگر دیگر این ایمیل‌ها را نمی‌خواهید،',
                'unsub'       => 'لغو اشتراک کنید',
                'rights'      => 'تمامی حقوق محفوظ است.',
            ],
            'el' => [
                'name'        => 'Υπενθύμιση λήξης εγγράφου',
                'subject'     => '{plaka} · το {evrak} λήγει σε {kalan_gun}',
                'preheader'   => 'Υπενθύμιση για το {evrak} του οχήματος {plaka}',
                'heading'     => 'Υπενθύμιση λήξης εγγράφου',
                'greeting'    => 'Γεια σας {ad},',
                'intro'       => 'Το έγγραφο <strong>{evrak}</strong> του οχήματός σας <strong>{plaka}</strong> πλησιάζει στη λήξη του. Παρακαλούμε ανανεώστε το εγκαίρως.',
                'intro_plain' => 'Το έγγραφο {evrak} του οχήματός σας {plaka} πλησιάζει στη λήξη του.',
                'lbl_arac'    => 'Όχημα',
                'lbl_evrak'   => 'Έγγραφο',
                'lbl_bitis'   => 'Ημερομηνία λήξης',
                'lbl_kalan'   => 'Υπόλοιπο χρόνου',
                'cta'         => 'Προβολή εγγράφου στο πάνελ',
                'cta_alt'     => 'Αν το κουμπί δεν λειτουργεί, αντιγράψτε αυτόν τον σύνδεσμο:',
                'kvkk'        => 'Αυτό το μήνυμα στάλθηκε αυτόματα για το έγγραφο οχήματος που είναι καταχωρισμένο στο evrako. Τα προσωπικά σας δεδομένα επεξεργάζονται μόνο για υπενθύμιση και δεν κοινοποιούνται σε τρίτους.',
                'unsub_text'  => 'Αν δεν επιθυμείτε αυτά τα μηνύματα,',
                'unsub'       => 'διαγραφείτε εδώ',
                'rights'      => 'Με επιφύλαξη όλων των δικαιωμάτων.',
            ],
        ];

        return $ui[$locale] ?? $ui['tr'];
    }
}
