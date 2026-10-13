<?php
declare(strict_types=1);

/**
 * Kurulumda yazilan varsayilan site ayarlari.
 * Panelden ekle / degistir / kaldir yapilabilir.
 */
return [
    // ---- Marka ----
    'brand.title'        => ['group' => 'brand', 'value' => 'evrako'],
    'brand.slogan'       => ['group' => 'brand', 'value' => 'Evrak bitişini asla kaçırma.'],
    'brand.logo_light'   => ['group' => 'brand', 'value' => ''],
    'brand.logo_dark'    => ['group' => 'brand', 'value' => ''],
    'brand.favicon'      => ['group' => 'brand', 'value' => ''],
    'brand.apple_icon'   => ['group' => 'brand', 'value' => ''],
    'brand.og_image'     => ['group' => 'brand', 'value' => ''],
    'brand.company'      => ['group' => 'brand', 'value' => 'webadvisor'],
    'brand.website'      => ['group' => 'brand', 'value' => 'https://webadvisor.com.tr'],
    'brand.email'        => ['group' => 'brand', 'value' => ''],
    'brand.phone'        => ['group' => 'brand', 'value' => ''],
    'brand.whatsapp'     => ['group' => 'brand', 'value' => ''],
    'brand.address'      => ['group' => 'brand', 'value' => ''],
    'brand.city'         => ['group' => 'brand', 'value' => ''],
    'brand.country'      => ['group' => 'brand', 'value' => 'KKTC'],
    'brand.facebook'     => ['group' => 'brand', 'value' => ''],
    'brand.instagram'    => ['group' => 'brand', 'value' => ''],
    'brand.youtube'      => ['group' => 'brand', 'value' => ''],

    // ---- SEO / meta ----
    'seo.meta_title'      => ['group' => 'seo', 'value' => 'evrako — Araç muayene, sigorta ve evrak takip sistemi'],
    'seo.meta_description' => ['group' => 'seo', 'value' => 'Kıbrıs’ta araçlarınızın muayene, sigorta ve yol vergisi tarihlerini takip edin. Süresi dolmadan otomatik e-posta, SMS ve WhatsApp hatırlatması alın.'],
    'seo.meta_keywords'   => ['group' => 'seo', 'value' => 'araç takip, muayene hatırlatma, sigorta bitiş tarihi, kıbrıs araç evrak, yol vergisi'],
    'seo.og_title'        => ['group' => 'seo', 'value' => ''],
    'seo.og_description'  => ['group' => 'seo', 'value' => ''],
    'seo.og_type'         => ['group' => 'seo', 'value' => 'website'],
    'seo.twitter_card'    => ['group' => 'seo', 'value' => 'summary_large_image'],
    'seo.robots'          => ['group' => 'seo', 'value' => 'index,follow'],
    'seo.canonical'       => ['group' => 'seo', 'value' => ''],

    // ---- Tema / renkler ----
    'theme.colors' => ['group' => 'theme', 'value' => [
        'light' => [
            'primary'   => '#0A1D3A',
            'accent'    => '#00C48C',
            'secondary' => '#3B82F6',
            'warning'   => '#F5A524',
            'danger'    => '#E5484D',
            'bg'        => '#F7F9FC',
            'card'      => '#FFFFFF',
            'text'      => '#0F172A',
            'muted'     => '#6B7C93',
            'border'    => '#E3E9F2',
        ],
        'dark' => [
            'primary'   => '#12325C',
            'accent'    => '#00C48C',
            'secondary' => '#60A5FA',
            'warning'   => '#F5A524',
            'danger'    => '#FF6369',
            'bg'        => '#081426',
            'card'      => '#0F2137',
            'text'      => '#E6EDF6',
            'muted'     => '#9DB4D0',
            'border'    => '#1E3450',
        ],
    ]],
    'theme.default_mode'   => ['group' => 'theme', 'value' => 'system'],
    'theme.radius'         => ['group' => 'theme', 'value' => '14'],
    'theme.font_heading'   => ['group' => 'theme', 'value' => 'Poppins'],
    'theme.font_body'      => ['group' => 'theme', 'value' => 'Inter'],

    // ---- Preloader ----
    'preloader.enabled'        => ['group' => 'preloader', 'value' => '1'],
    'preloader.type'           => ['group' => 'preloader', 'value' => 'image'],  // image | spinner
    'preloader.media'          => ['group' => 'preloader', 'value' => ''],
    'preloader.background'     => ['group' => 'preloader', 'value' => '#0A1D3A'],
    'preloader.background_dark'=> ['group' => 'preloader', 'value' => '#081426'],
    'preloader.min_seconds'    => ['group' => 'preloader', 'value' => '0.6'],
    'preloader.max_seconds'    => ['group' => 'preloader', 'value' => '6'],
    'preloader.spinner_color'  => ['group' => 'preloader', 'value' => '#00C48C'],

    // ---- Genel / davranis ----
    'site.maintenance'        => ['group' => 'site', 'value' => '0'],
    'site.maintenance_text'   => ['group' => 'site', 'value' => 'Kısa bir bakım çalışması yapıyoruz. Lütfen birkaç dakika sonra tekrar deneyin.'],
    'site.default_locale'     => ['group' => 'site', 'value' => 'tr'],
    'site.locales'            => ['group' => 'site', 'value' => ['tr', 'en', 'ru', 'ar', 'fa', 'el']],
    'site.registration_open'  => ['group' => 'site', 'value' => '1'],
    'site.trial_days'         => ['group' => 'site', 'value' => '14'],
    'site.default_reminder_offsets' => ['group' => 'site', 'value' => '7,3,1,0'],

    // ---- SMTP (kurumsal mail — panelden girilir) ----
    'smtp.host'       => ['group' => 'mail', 'value' => ''],
    'smtp.port'       => ['group' => 'mail', 'value' => '587'],
    'smtp.encryption' => ['group' => 'mail', 'value' => 'tls'],
    'smtp.auth'       => ['group' => 'mail', 'value' => '1'],
    'smtp.username'   => ['group' => 'mail', 'value' => ''],
    'smtp.password'   => ['group' => 'mail', 'value' => ''],
    'smtp.from_email' => ['group' => 'mail', 'value' => ''],
    'smtp.from_name'  => ['group' => 'mail', 'value' => 'evrako'],
    'smtp.reminder_hour' => ['group' => 'mail', 'value' => '08'],
    'smtp.daily_limit'   => ['group' => 'mail', 'value' => '500'],

    // ---- Bildirim kanallari ----
    'channels.telegram_enabled'  => ['group' => 'channels', 'value' => '0'],
    'channels.telegram_bot_token'=> ['group' => 'channels', 'value' => ''],
    'channels.whatsapp_enabled'  => ['group' => 'channels', 'value' => '0'],
    'channels.whatsapp_provider' => ['group' => 'channels', 'value' => ''],
    'channels.sms_enabled'       => ['group' => 'channels', 'value' => '0'],
    'channels.sms_provider'      => ['group' => 'channels', 'value' => ''],

    // ---- Odeme (odeme modulu kurulunca doldurulur) ----
    'payment.provider'      => ['group' => 'payment', 'value' => 'manual'],
    'payment.currency'      => ['group' => 'payment', 'value' => 'TRY'],
    'payment.iyzico_api_key'=> ['group' => 'payment', 'value' => ''],
    'payment.iyzico_secret' => ['group' => 'payment', 'value' => ''],
    'payment.iyzico_base'   => ['group' => 'payment', 'value' => 'https://sandbox-api.iyzipay.com'],
    'payment.bank_iban'     => ['group' => 'payment', 'value' => ''],
    'payment.bank_owner'    => ['group' => 'payment', 'value' => ''],
    'payment.bank_enabled'  => ['group' => 'payment', 'value' => '1'],
    'payment.bank_note'     => ['group' => 'payment', 'value' => 'Açıklama alanına ödeme kodunuzu yazmanız, ödemenizin otomatik eşleşmesini sağlar.'],
    'payment.bank_accounts' => ['group' => 'payment', 'value' => []],
    'payment.min_topup'     => ['group' => 'payment', 'value' => '100'],
    'payment.topup_presets' => ['group' => 'payment', 'value' => [250, 500, 1000, 2500]],
    'payment.intent_ttl_hours' => ['group' => 'payment', 'value' => '72'],
];
