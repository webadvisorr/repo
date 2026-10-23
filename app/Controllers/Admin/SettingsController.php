<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Config;
use App\Core\Database;
use App\Core\Mailer;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Uploader;

/**
 * Site kimliği ve sistem ayarları: logo, favicon, başlık/slogan, meta & OpenGraph,
 * renkler, preloader, SMTP, bildirim kanalları, ödeme.
 * Her alan ekle / değiştir / kaldır destekler. Anahtar listesi beyaz listedir.
 */
final class SettingsController extends Controller
{
    protected string $layout = 'layouts/admin';

    private const SCHEMA = [
        'brand' => [
            'media' => [
                'brand.logo_light' => ['label' => 'logo_light', 'kind' => 'logo'],
                'brand.logo_dark'  => ['label' => 'logo_dark', 'kind' => 'logo'],
                'brand.favicon'    => ['label' => 'favicon', 'kind' => 'favicon'],
                'brand.apple_icon' => ['label' => 'apple_icon', 'kind' => 'favicon'],
                'brand.og_image'   => ['label' => 'og_image', 'kind' => 'og'],
            ],
            'text' => [
                'brand.title'  => ['label' => 'site_title', 'max' => 120],
                'brand.slogan' => ['label' => 'slogan', 'max' => 200],
                'brand.company' => ['label' => 'Şirket', 'max' => 120],
                'brand.website' => ['label' => 'Web sitesi', 'max' => 190],
                'brand.email'   => ['label' => 'Kurumsal e-posta', 'max' => 190],
                'brand.phone'   => ['label' => 'Telefon', 'max' => 40],
                'brand.whatsapp' => ['label' => 'WhatsApp', 'max' => 40],
                'brand.address' => ['label' => 'Adres', 'max' => 255],
                'brand.city'    => ['label' => 'Şehir', 'max' => 80],
                'brand.country' => ['label' => 'Ülke', 'max' => 80],
                'brand.facebook' => ['label' => 'Facebook', 'max' => 190],
                'brand.instagram' => ['label' => 'Instagram', 'max' => 190],
                'brand.youtube' => ['label' => 'YouTube', 'max' => 190],
            ],
        ],
        'seo' => [
            'text' => [
                'seo.meta_title'       => ['label' => 'meta_title', 'max' => 190],
                'seo.meta_description' => ['label' => 'meta_description', 'max' => 320, 'textarea' => true],
                'seo.meta_keywords'    => ['label' => 'meta_keywords', 'max' => 255],
                'seo.og_title'         => ['label' => 'og_title', 'max' => 190],
                'seo.og_description'   => ['label' => 'og_description', 'max' => 320, 'textarea' => true],
                'seo.canonical'        => ['label' => 'canonical', 'max' => 255],
            ],
            'select' => [
                'seo.og_type'      => ['label' => 'og_type', 'options' => ['website', 'article', 'product']],
                'seo.twitter_card' => ['label' => 'twitter_card', 'options' => ['summary', 'summary_large_image']],
                'seo.robots'       => ['label' => 'robots', 'options' => ['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow']],
            ],
        ],
        'theme' => [
            'colors' => true,
            'select' => [
                'theme.default_mode' => ['label' => 'default_mode', 'options' => ['system', 'light', 'dark']],
            ],
            'number' => [
                'theme.radius' => ['label' => 'radius', 'min' => 0, 'max' => 40],
            ],
        ],
        'preloader' => [
            'media' => [
                'preloader.media' => ['label' => 'preloader_media', 'kind' => 'preloader'],
            ],
            'bool' => [
                'preloader.enabled' => ['label' => 'preloader_enabled'],
            ],
            'select' => [
                'preloader.type' => ['label' => 'preloader_type', 'options' => ['image', 'spinner']],
            ],
            'color' => [
                'preloader.background'      => ['label' => 'preloader_bg'],
                'preloader.background_dark' => ['label' => 'preloader_bg_dark'],
                'preloader.spinner_color'   => ['label' => 'preloader_spinner'],
            ],
            'number' => [
                'preloader.min_seconds' => ['label' => 'preloader_min', 'min' => 0, 'max' => 30, 'step' => '0.1'],
                'preloader.max_seconds' => ['label' => 'preloader_max', 'min' => 1, 'max' => 60, 'step' => '0.1'],
            ],
        ],
        'site' => [
            'bool' => [
                'site.maintenance'       => ['label' => 'maintenance'],
                'site.registration_open' => ['label' => 'registration_open'],
            ],
            'text' => [
                'site.maintenance_text' => ['label' => 'maintenance_text', 'max' => 500, 'textarea' => true],
                'site.default_reminder_offsets' => ['label' => 'reminder_offsets', 'max' => 60],
            ],
            'number' => [
                'site.trial_days' => ['label' => 'trial_days', 'min' => 0, 'max' => 365],
            ],
        ],
        'mail' => [
            'text' => [
                'smtp.host'       => ['label' => 'smtp_host', 'max' => 190],
                'smtp.port'       => ['label' => 'smtp_port', 'max' => 6],
                'smtp.username'   => ['label' => 'smtp_username', 'max' => 190],
                'smtp.from_email' => ['label' => 'smtp_from_email', 'max' => 190],
                'smtp.from_name'  => ['label' => 'smtp_from_name', 'max' => 120],
            ],
            'select' => [
                'smtp.encryption' => ['label' => 'smtp_encryption', 'options' => ['tls', 'ssl', 'none']],
            ],
            'bool' => [
                'smtp.auth' => ['label' => 'smtp_auth'],
            ],
            'password' => [
                'smtp.password' => ['label' => 'smtp_password'],
            ],
            'test' => true,
        ],
        'reminders' => [
            'bool' => [
                'reminders.enabled' => ['label' => 'reminders_enabled'],
            ],
            'text' => [
                'reminders.offsets' => ['label' => 'reminders_offsets', 'max' => 60],
            ],
            'number' => [
                'reminders.hour'         => ['label' => 'reminders_hour', 'min' => 0, 'max' => 23, 'step' => '1'],
                'reminders.daily_limit'  => ['label' => 'reminders_daily_limit', 'min' => 1, 'max' => 100000, 'step' => '10'],
                'reminders.batch'        => ['label' => 'reminders_batch', 'min' => 1, 'max' => 500, 'step' => '5'],
                'reminders.max_attempts' => ['label' => 'reminders_max_attempts', 'min' => 1, 'max' => 10, 'step' => '1'],
            ],
        ],
        'channels' => [
            'bool' => [
                'channels.telegram_enabled' => ['label' => 'channels_telegram'],
                'channels.whatsapp_enabled' => ['label' => 'channels_whatsapp'],
                'channels.sms_enabled'      => ['label' => 'channels_sms'],
            ],
            'text' => [
                'channels.telegram_bot_token' => ['label' => 'Telegram bot token', 'max' => 190],
                'channels.whatsapp_provider'  => ['label' => 'WhatsApp sağlayıcı', 'max' => 120],
                'channels.sms_provider'       => ['label' => 'SMS sağlayıcı', 'max' => 120],
            ],
        ],
        'payment' => [
            'select' => [
                'payment.provider' => ['label' => 'payment_provider', 'options' => ['manual', 'iyzico', 'paytr', 'bank_transfer']],
                'payment.currency' => ['label' => 'payment_currency', 'options' => ['TRY', 'EUR', 'USD', 'GBP']],
            ],
            'bool' => [
                'payment.bank_enabled' => ['label' => 'bank_enabled'],
            ],
            'number' => [
                'payment.min_topup'        => ['label' => 'min_topup', 'min' => 1, 'max' => 100000, 'step' => '0.01'],
                'payment.intent_ttl_hours' => ['label' => 'intent_ttl', 'min' => 1, 'max' => 720],
                // Ziyaretçi başka bir para birimi seçtiğinde fiyatlar bunlarla çevrilir
                // (1 birim = kaç taban para). 0 = o birim seçicide gösterilmez.
                'payment.rate_usd'         => ['label' => 'rate_usd', 'min' => 0, 'max' => 100000, 'step' => '0.0001'],
                'payment.rate_eur'         => ['label' => 'rate_eur', 'min' => 0, 'max' => 100000, 'step' => '0.0001'],
                'payment.rate_gbp'         => ['label' => 'rate_gbp', 'min' => 0, 'max' => 100000, 'step' => '0.0001'],
            ],
            'text' => [
                'payment.bank_note'      => ['label' => 'bank_note', 'max' => 255, 'textarea' => true],
                'payment.topup_presets'  => ['label' => 'topup_presets', 'max' => 190],
                'payment.bank_iban'      => ['label' => 'bank_iban', 'max' => 64],
                'payment.bank_owner'     => ['label' => 'bank_owner', 'max' => 120],
                'payment.iyzico_api_key' => ['label' => 'iyzico API key', 'max' => 190],
                'payment.iyzico_base'    => ['label' => 'iyzico ortam adresi (ödeme sayfası için, sonraki sürüm)', 'max' => 190],
            ],
            'password' => [
                'payment.iyzico_secret' => ['label' => 'iyzico secret'],
            ],
            'bank_accounts' => true,
        ],
        'analytics' => [
            'bool' => [
                'analytics.enabled'          => ['label' => 'analytics_enabled'],
                'analytics.consent_required' => ['label' => 'analytics_consent'],
            ],
            'select' => [
                'analytics.provider' => ['label' => 'analytics_provider', 'options' => ['ga4', 'plausible', 'custom']],
            ],
            'text' => [
                'analytics.ga_id'            => ['label' => 'analytics_ga_id', 'max' => 40],
                'analytics.plausible_domain' => ['label' => 'analytics_plausible', 'max' => 190],
                'analytics.custom_script'    => ['label' => 'analytics_custom', 'max' => 4000, 'textarea' => true],
            ],
        ],
        'cookie' => [
            'bool' => [
                'cookie.enabled' => ['label' => 'cookie_enabled'],
            ],
            'text' => [
                'cookie.title'         => ['label' => 'cookie_title', 'max' => 80],
                'cookie.text'          => ['label' => 'cookie_text', 'max' => 600, 'textarea' => true],
                'cookie.accept_label'  => ['label' => 'cookie_accept', 'max' => 40],
                'cookie.decline_label' => ['label' => 'cookie_decline', 'max' => 40],
                'cookie.link'          => ['label' => 'cookie_link', 'max' => 190],
            ],
        ],
        'popup' => [
            'media' => [
                'popup.image' => ['label' => 'popup_image', 'kind' => 'og'],
            ],
            'bool' => [
                'popup.enabled' => ['label' => 'popup_enabled'],
            ],
            'text' => [
                'popup.title'       => ['label' => 'popup_title', 'max' => 120],
                'popup.text'        => ['label' => 'popup_text', 'max' => 600, 'textarea' => true],
                'popup.button_text' => ['label' => 'popup_button_text', 'max' => 60],
                'popup.button_url'  => ['label' => 'popup_button_url', 'max' => 190],
            ],
            'number' => [
                'popup.delay' => ['label' => 'popup_delay', 'min' => 0, 'max' => 120, 'step' => '1'],
            ],
            'select' => [
                'popup.frequency' => ['label' => 'popup_frequency', 'options' => ['session', 'always', 'week']],
            ],
        ],
        'slider' => [
            'number' => [
                'slider.autoplay' => ['label' => 'slider_autoplay', 'min' => 2000, 'max' => 20000, 'step' => '500'],
            ],
            'bool' => [
                'slider.kenburns' => ['label' => 'slider_kenburns'],
            ],
        ],
        'cta' => [
            'bool' => [
                'sticky_cta.enabled' => ['label' => 'sticky_enabled'],
            ],
            'text' => [
                'sticky_cta.text'        => ['label' => 'sticky_text', 'max' => 160],
                'sticky_cta.button_text' => ['label' => 'sticky_button_text', 'max' => 60],
                'sticky_cta.button_url'  => ['label' => 'sticky_button_url', 'max' => 190],
            ],
        ],
        // Panel kullanım rehberi: yerel mod + isteğe bağlı OpenAI uyumlu AI uç noktası.
        'assistant' => [
            'bool' => [
                'assistant.enabled' => ['label' => 'assistant_enabled'],
            ],
            'text' => [
                'assistant.endpoint' => ['label' => 'assistant_endpoint', 'max' => 190],
                'assistant.model'    => ['label' => 'assistant_model', 'max' => 80],
            ],
            'password' => [
                'assistant.api_key' => ['label' => 'assistant_api_key'],
            ],
        ],
        'site_assistant' => [
            'bool' => [
                'site_assistant.enabled' => ['label' => 'site_assistant_enabled'],
            ],
            'text' => [
                'site_assistant.model'    => ['label' => 'site_assistant_model', 'max' => 80],
                'site_assistant.greeting' => ['label' => 'site_assistant_greeting', 'max' => 400, 'textarea' => true],
                'site_assistant.persona'  => ['label' => 'site_assistant_persona', 'max' => 1200, 'textarea' => true],
            ],
            'password' => [
                'site_assistant.api_key' => ['label' => 'site_assistant_api_key'],
            ],
        ],
    ];

    /** PHP, form alan adlarindaki noktayi alt cizgiye cevirir (brand.title -> brand_title). */
    private static function fieldName(string $key): string
    {
        return str_replace('.', '_', $key);
    }

    public function index(string $tab = 'brand'): string
    {
        $tabs = array_values(array_filter(array_keys(self::SCHEMA), static fn (string $key): bool => $key !== 'assistant'));
        if (!in_array($tab, $tabs, true)) {
            $tab = 'brand';
        }

        return $this->view('admin/settings', [
            'title'  => t('settings.title'),
            'tab'    => $tab,
            'tabs'   => $tabs,
            'schema' => self::SCHEMA,
            'colors' => (array) Settings::get('theme.colors', []),
            'media'  => $this->mediaList(),
            'slides' => $tab === 'slider'
                ? Database::select('SELECT id, title, subtitle, button_text, button_url, image_path, is_active, sort_order FROM slides ORDER BY sort_order ASC, id ASC')
                : [],
        ]);
    }

    public function save(): void
    {
        $tab = (string) $this->request->input('tab', 'brand');
        if (!isset(self::SCHEMA[$tab])) {
            $this->flashError(t('common.error'));
            $this->redirect('/yonetim/ayarlar');
        }
        $definition = self::SCHEMA[$tab];
        $savedCount = 0;

        foreach (($definition['text'] ?? []) as $key => $meta) {
            $value = Str::limit($this->request->string(self::fieldName($key)), (int) ($meta['max'] ?? 255), '');
            Settings::set($key, $value, $tab);
            $savedCount++;
        }
        foreach (($definition['number'] ?? []) as $key => $meta) {
            $raw = $this->request->string(self::fieldName($key));
            $value = is_numeric($raw) ? (float) $raw : 0.0;
            $value = max((float) ($meta['min'] ?? 0), min((float) ($meta['max'] ?? 100), $value));
            $isDecimal = str_contains($raw, '.');
            Settings::set($key, $isDecimal ? (string) round($value, 2) : (string) (int) $value, $tab);
            $savedCount++;
        }
        foreach (($definition['select'] ?? []) as $key => $meta) {
            $value = $this->request->string(self::fieldName($key));
            if (in_array($value, (array) $meta['options'], true)) {
                Settings::set($key, $value, $tab);
                $savedCount++;
            }
        }
        foreach (($definition['bool'] ?? []) as $key => $meta) {
            Settings::set($key, $this->request->bool(self::fieldName($key)) ? '1' : '0', $tab);
            $savedCount++;
        }
        foreach (($definition['color'] ?? []) as $key => $meta) {
            $value = $this->request->string(self::fieldName($key));
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                Settings::set($key, strtoupper($value), $tab);
                $savedCount++;
            }
        }
        foreach (($definition['password'] ?? []) as $key => $meta) {
            $value = trim((string) $this->request->input(self::fieldName($key), ''));
            if ($value !== '') {
                Settings::set($key, $value, $tab);
                $savedCount++;
                Audit::log('settings.secret_updated', 'setting', $key);
            }
        }

        if (!empty($definition['colors'])) {
            $current = (array) Settings::get('theme.colors', []);
            foreach (['light', 'dark'] as $mode) {
                foreach ($this->request->array('colors_' . $mode) as $name => $value) {
                    if (!preg_match('/^[a-z_]+$/', (string) $name)) {
                        continue;
                    }
                    if (preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value)) {
                        $current[$mode][(string) $name] = strtoupper((string) $value);
                    }
                }
            }
            Settings::set('theme.colors', $current, 'theme');
            $savedCount++;
        }

        if (!empty($definition['bank_accounts'])) {
            $banks   = $this->request->array('ba_bank');
            $owners  = $this->request->array('ba_owner');
            $ibans   = $this->request->array('ba_iban');
            $branch  = $this->request->array('ba_branch');
            $acct    = $this->request->array('ba_account');
            $notes   = $this->request->array('ba_note');
            $rows = [];
            foreach ($ibans as $i => $iban) {
                $iban = preg_replace('/[^A-Za-z0-9]/', '', (string) $iban) ?? '';
                $accountNo = preg_replace('/\s+/', '', trim((string) ($acct[$i] ?? ''))) ?? '';
                if ($iban === '' && $accountNo === '') {
                    continue;
                }
                $rows[] = [
                    'bank'    => Str::limit(trim((string) ($banks[$i] ?? '')), 80, ''),
                    'owner'   => Str::limit(trim((string) ($owners[$i] ?? '')), 120, ''),
                    'iban'    => Str::limit($iban, 40, ''),
                    'account' => Str::limit($accountNo, 40, ''),
                    'branch'  => Str::limit(trim((string) ($branch[$i] ?? '')), 80, ''),
                    'note'    => Str::limit(trim((string) ($notes[$i] ?? '')), 160, ''),
                ];
            }
            Settings::set('payment.bank_accounts', $rows, 'payment');
            $savedCount++;
        }

        Audit::log('settings.update', 'setting', $tab, ['fields' => $savedCount]);
        $this->flashSuccess(t('settings.saved'));
        $this->redirect('/yonetim/ayarlar/' . $tab);
    }

    public function uploadMedia(): void
    {
        $settingKey = (string) $this->request->input('setting_key', '');
        $tab = (string) $this->request->input('tab', 'brand');

        $allowed = $this->mediaKeys();
        if (!isset($allowed[$settingKey])) {
            $this->flashError(t('common.error'));
            $this->redirect('/yonetim/ayarlar/' . $tab);
        }

        $file = $this->request->file('file');
        if ($file === null) {
            $this->flashError(t('upload.error_4'));
            $this->redirect('/yonetim/ayarlar/' . $tab);
        }

        $extensions = $allowed[$settingKey] === 'logo'
            ? ['svg', 'png', 'webp', 'jpg', 'jpeg', 'gif']
            : ['png', 'gif', 'svg', 'webp', 'apng', 'ico', 'jpg', 'jpeg', 'avif'];

        $result = Uploader::store($file, $settingKey, [
            'type'       => 'image',
            'extensions' => $extensions,
            'max_bytes'  => (int) Config::get('uploads.max_image_bytes', 8388608),
        ]);

        if (!$result['ok']) {
            $this->flashError((string) ($result['error'] ?? t('common.error')));
            $this->redirect('/yonetim/ayarlar/' . $tab);
        }

        Settings::set($settingKey, (string) $result['media']['path'], $tab);
        Settings::set($settingKey . '_media_id', (string) $result['media']['id'], $tab);

        Audit::log('settings.media_upload', 'setting', $settingKey);
        $this->flashSuccess(t('common.saved'));
        $this->redirect('/yonetim/ayarlar/' . $tab);
    }

    public function removeMedia(): void
    {
        $settingKey = (string) $this->request->input('setting_key', '');
        $tab = (string) $this->request->input('tab', 'brand');

        if (!isset($this->mediaKeys()[$settingKey])) {
            $this->flashError(t('common.error'));
            $this->redirect('/yonetim/ayarlar/' . $tab);
        }

        $mediaId = (int) Settings::get($settingKey . '_media_id', 0);
        Settings::set($settingKey, '', $tab);
        Settings::set($settingKey . '_media_id', '', $tab);
        if ($mediaId > 0) {
            Uploader::delete($mediaId);
        }

        Audit::log('settings.media_remove', 'setting', $settingKey);
        $this->flashSuccess(t('common.deleted'));
        $this->redirect('/yonetim/ayarlar/' . $tab);
    }

    public function testMail(): void
    {
        $to = $this->request->string('test_email');
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            $this->flashError(t('validation.email', ['field' => t('auth.email')]));
            $this->redirect('/yonetim/ayarlar/mail');
        }

        $mailer = new Mailer();
        if (!$mailer->isConfigured()) {
            $this->flashError(t('settings.smtp_test_failed', ['error' => 'SMTP ayarları eksik (sunucu / gönderen adresi)']));
            $this->redirect('/yonetim/ayarlar/mail');
        }

        $brand = (string) Settings::get('brand.title', 'Evrako');
        $html = '<div style="font-family:Arial,sans-serif;font-size:15px;color:#0F172A">'
            . '<h2 style="color:#0A1D3A">' . e($brand) . ' — SMTP testi</h2>'
            . '<p>Bu e-posta, panelde girdiğiniz SMTP ayarlarının çalıştığını doğrulamak için gönderildi.</p>'
            . '<p style="color:#6B7C93;font-size:13px">Gönderim zamanı: ' . date('d.m.Y H:i') . '</p></div>';

        $result = $mailer->send($to, 'SMTP testi — ' . $brand, $html);
        if ($result['ok']) {
            Audit::log('settings.smtp_test', 'setting', 'smtp');
            $this->flashSuccess(t('settings.smtp_test_sent'));
        } else {
            $this->flashError(t('settings.smtp_test_failed', ['error' => (string) $result['error']]));
        }
        $this->redirect('/yonetim/ayarlar/mail');
    }

    /** @return array<string,string> ayar anahtari => medya turu */
    private function mediaKeys(): array
    {
        $allowed = [];
        foreach (self::SCHEMA as $definition) {
            foreach (($definition['media'] ?? []) as $key => $meta) {
                $allowed[$key] = (string) $meta['kind'];
            }
        }
        return $allowed;
    }

    /** @return array<int,array> */
    private function mediaList(): array
    {
        try {
            return Database::select('SELECT * FROM media ORDER BY id DESC LIMIT 40');
        } catch (\Throwable) {
            return [];
        }
    }
}
