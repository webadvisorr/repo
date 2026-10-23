<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Settings;
use App\Core\Translator;

final class HomeController extends Controller
{
    public function index(): string
    {
        $faqs = $this->faqs();

        return $this->view('site/home', [
            'title'           => (string) Settings::get('seo.meta_title', (string) Settings::get('brand.title', 'Evrako')),
            'metaDescription' => (string) Settings::get('seo.meta_description', ''),
            'slides'          => $this->slides(),
            'plans'           => $this->plans(),
            'faqs'            => $faqs,
            'jsonLd'          => $this->homeSchema($faqs),
        ]);
    }

    public function howItWorks(): string
    {
        return $this->view('site/how_it_works', [
            'title'           => t('nav.how') . ' — ' . (string) Settings::get('brand.title', 'Evrako'),
            'metaDescription' => 'Araç evraklarınızı bir kez kaydedin; muayene, sigorta ve seyrüsefer bitişini 7/3/1 gün önce otomatik hatırlatalım. Kurulum dört adımda.',
        ]);
    }

    public function pricing(): string
    {
        $plans = $this->plans();

        return $this->view('site/pricing', [
            'title'           => t('nav.pricing') . ' — ' . (string) Settings::get('brand.title', 'Evrako'),
            'metaDescription' => 'Araç sayınıza uygun üyelik planını seçin. Ücretsiz planla başlayın, istediğiniz an yükseltin; gizli ücret yok.',
            'plans'           => $plans,
            'jsonLd'          => $this->pricingSchema($plans),
        ]);
    }

    public function contact(): string
    {
        return $this->view('site/contact', [
            'title'           => t('nav.contact') . ' — ' . (string) Settings::get('brand.title', 'Evrako'),
            'metaDescription' => 'Sorularınız, kurumsal filo ve iş birliği talepleriniz için bize yazın. E-posta, telefon ve WhatsApp ile ulaşabilirsiniz.',
            'jsonLd'          => [$this->organizationSchema()],
        ]);
    }

    public function contactSubmit(): void
    {
        $data = $this->validate(
            $this->request->only(['name', 'email', 'phone', 'subject', 'message']),
            [
                'name'    => 'required|min:3|max:160',
                'email'   => 'required|email|max:190',
                'phone'   => 'nullable|phone|max:32',
                'message' => 'required|min:10|max:5000',
            ],
            ['name' => 'Ad Soyad', 'email' => 'E-posta', 'phone' => 'Telefon', 'message' => 'Mesaj'],
            '/iletisim'
        );

        Database::insert('contact_messages', [
            'user_id'    => \App\Core\Auth::id(),
            'name'       => (string) $data['name'],
            'email'      => (string) $data['email'],
            'phone'      => $data['phone'] ?: null,
            'subject'    => $data['subject'] ?: null,
            'message'    => (string) $data['message'],
            'ip'         => $this->request->ip(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        Session::put('_thanks', ['name' => (string) $data['name'], 'email' => (string) $data['email']]);
        Session::put('_thanks_type', 'contact');
        $this->flashSuccess(t('common.success'));
        $this->redirect('/tesekkurler');
    }

    /** Teşekkür sayfası — form gönderimi sonrası. */
    public function thanks(): string
    {
        $info = (array) Session::get('_thanks', []);
        if ($info === []) {
            // Doğrudan erişim: ana sayfaya dön.
            $this->redirect('/');
        }
        $type = (string) Session::get('_thanks_type', 'contact');
        Session::forget('_thanks');
        Session::forget('_thanks_type');

        return $this->view('site/thanks', [
            'title'           => 'Teşekkürler',
            'metaDescription' => 'Mesajınız bize ulaştı. En kısa sürede dönüş yapacağız.',
            'robots'          => 'noindex,follow',
            'name'            => (string) ($info['name'] ?? ''),
            'type'            => $type,
        ]);
    }

    public function privacy(): string
    {
        return $this->view('site/privacy', [
            'title'           => t('footer.privacy') . ' — ' . (string) Settings::get('brand.title', 'Evrako'),
            'metaDescription' => 'Kişisel verilerinizin nasıl toplandığı, işlendiği ve korunduğu hakkında gizlilik ve KVKK politikamız.',
            'jsonLd'          => [$this->organizationSchema()],
        ]);
    }

    public function terms(): string
    {
        return $this->view('site/terms', [
            'title'           => t('footer.terms') . ' — ' . (string) Settings::get('brand.title', 'Evrako'),
            'metaDescription' => 'evrako hizmetlerinin kullanım koşulları: üyelik, ücretlendirme, sorumluluklar ve fesih.',
        ]);
    }

    /** robots.txt — dinamik (site adresi panelden değişebilir). */
    public function robots(): string
    {
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
        }
        $robots = (string) Settings::get('seo.robots', 'index,follow');
        $lines = [];
        if (str_contains($robots, 'noindex')) {
            $lines[] = 'User-agent: *';
            $lines[] = 'Disallow: /';
        } else {
            $lines[] = 'User-agent: *';
            $lines[] = 'Allow: /';
            $lines[] = 'Disallow: /panel';
            $lines[] = 'Disallow: /yonetim';
            $lines[] = 'Disallow: /cikis';
            $lines[] = 'Disallow: /media/';
            $lines[] = '';
            $lines[] = 'Sitemap: ' . $this->baseUrl() . '/sitemap.xml';
        }
        return implode("\n", $lines) . "\n";
    }

    /** sitemap.xml — yayınlanan genel sayfalar. */
    public function sitemap(): string
    {
        if (!headers_sent()) {
            header('Content-Type: application/xml; charset=utf-8');
        }
        $base = $this->baseUrl();
        $pages = [
            ['/', '1.0', 'weekly'],
            ['/nasil-calisir', '0.8', 'monthly'],
            ['/fiyatlar', '0.9', 'weekly'],
            ['/iletisim', '0.6', 'monthly'],
            ['/gizlilik', '0.3', 'yearly'],
            ['/kullanim-kosullari', '0.3', 'yearly'],
        ];
        $today = date('Y-m-d');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($pages as [$path, $priority, $freq]) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($base . $path, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
            $xml .= '    <lastmod>' . $today . "</lastmod>\n";
            $xml .= '    <changefreq>' . $freq . "</changefreq>\n";
            $xml .= '    <priority>' . $priority . "</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>' . "\n";
        return $xml;
    }

    private function baseUrl(): string
    {
        $configured = rtrim((string) Config::get('app.url', ''), '/');
        return $configured !== '' ? $configured : rtrim((new Request())->fullUrl(), '/');
    }

    /** @return array<int,array> */
    private function slides(): array
    {
        try {
            $slides = Database::select(
                'SELECT * FROM slides WHERE is_active = 1 AND (locale IS NULL OR locale = ?) ORDER BY sort_order ASC',
                [Translator::locale()]
            );
            if ($slides !== []) {
                return $slides;
            }
        } catch (\Throwable) {
            // tablo yoksa varsayılan slaytlar kullanılır
        }
        return $this->defaultSlides();
    }

    /**
     * Veritabanında slayt tanımlı değilse gösterilen çevrilebilir varsayılanlar.
     * @return array<int,array>
     */
    private function defaultSlides(): array
    {
        return [
            [
                'image_path'      => '',
                'title'           => t('slider.1_title'),
                'subtitle'        => t('slider.1_sub'),
                'description'     => t('slider.1_text'),
                'button_text'     => t('slider.1_cta'),
                'button_url'      => url('/kayit'),
                'button_target'   => '_self',
                'text_position'   => 'left',
                'overlay_opacity' => 60,
            ],
            [
                'image_path'      => '',
                'title'           => t('slider.2_title'),
                'subtitle'        => t('slider.2_sub'),
                'description'     => t('slider.2_text'),
                'button_text'     => t('slider.2_cta'),
                'button_url'      => url('/nasil-calisir'),
                'button_target'   => '_self',
                'text_position'   => 'center',
                'overlay_opacity' => 60,
            ],
            [
                'image_path'      => '',
                'title'           => t('slider.3_title'),
                'subtitle'        => t('slider.3_sub'),
                'description'     => t('slider.3_text'),
                'button_text'     => t('slider.3_cta'),
                'button_url'      => url('/fiyatlar'),
                'button_target'   => '_self',
                'text_position'   => 'left',
                'overlay_opacity' => 60,
            ],
        ];
    }

    /** @return array<int,array> */
    private function plans(): array
    {
        try {
            return Database::select('SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order ASC, price ASC');
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int,array> */
    private function faqs(): array
    {
        try {
            return Database::select('SELECT * FROM faqs WHERE is_active = 1 AND locale = ? ORDER BY sort_order ASC', [Translator::locale()]);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int,array> */
    private function homeSchema(array $faqs): array
    {
        $schema = [$this->organizationSchema()];
        $schema[] = [
            '@context'        => 'https://schema.org',
            '@type'           => 'WebSite',
            'name'            => (string) Settings::get('brand.title', 'Evrako'),
            'url'             => $this->baseUrl(),
            'inLanguage'      => Translator::locale(),
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => $this->baseUrl() . '/?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];
        if ($faqs !== []) {
            $items = [];
            foreach ($faqs as $faq) {
                $items[] = [
                    '@type'          => 'Question',
                    'name'           => (string) $faq['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => (string) $faq['answer']],
                ];
            }
            $schema[] = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items];
        }
        return $schema;
    }

    /** @return array<int,array> */
    private function pricingSchema(array $plans): array
    {
        $offers = [];
        foreach ($plans as $plan) {
            // Aylık fiyat varsa o, yoksa (ömür boyu plan) tek fiyat kullanılır.
            $interval = \App\Services\SubscriptionService::intervalFor($plan, 'month');
            $price = \App\Services\SubscriptionService::priceFor($plan, $interval);
            $offers[] = [
                '@type'         => 'Offer',
                'name'          => (string) $plan['name'],
                'price'         => (string) $price,
                'priceCurrency' => (string) ($plan['currency'] ?? 'TRY'),
                'url'           => $this->baseUrl() . '/fiyatlar',
            ];
        }
        return [[
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => (string) Settings::get('brand.title', 'Evrako') . ' üyelik',
            'description' => (string) Settings::get('seo.meta_description', ''),
            'offers'      => $offers,
        ]];
    }

    /** @return array<string,mixed> */
    private function organizationSchema(): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => (string) Settings::get('brand.title', 'Evrako'),
            'url'      => $this->baseUrl(),
        ];
        $logo = (string) Settings::get('brand.logo_light', '');
        if ($logo !== '') {
            $schema['logo'] = media_url($logo);
        }
        $email = (string) Settings::get('brand.email', '');
        if ($email !== '') {
            $schema['email'] = $email;
        }
        $phone = (string) Settings::get('brand.phone', '');
        if ($phone !== '') {
            $schema['telephone'] = $phone;
        }
        $address = (string) Settings::get('brand.address', '');
        $city = (string) Settings::get('brand.city', '');
        if ($address !== '' || $city !== '') {
            $schema['address'] = array_filter([
                '@type'           => 'PostalAddress',
                'streetAddress'   => $address,
                'addressLocality' => $city,
                'addressCountry'  => (string) Settings::get('brand.country', ''),
            ]);
        }
        $sameAs = array_values(array_filter([
            (string) Settings::get('brand.facebook', ''),
            (string) Settings::get('brand.instagram', ''),
            (string) Settings::get('brand.youtube', ''),
        ]));
        if ($sameAs !== []) {
            $schema['sameAs'] = $sameAs;
        }
        return $schema;
    }
}
