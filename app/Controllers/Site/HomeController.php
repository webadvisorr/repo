<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Settings;
use App\Core\Translator;

final class HomeController extends Controller
{
    public function index(): string
    {
        return $this->view('site/home', [
            'title'           => (string) Settings::get('seo.meta_title', (string) Settings::get('brand.title', 'evrako')),
            'metaDescription' => (string) Settings::get('seo.meta_description', ''),
            'slides'          => $this->slides(),
            'plans'           => $this->plans(),
            'faqs'            => $this->faqs(),
        ]);
    }

    public function howItWorks(): string
    {
        return $this->view('site/how_it_works', [
            'title' => t('nav.how') . ' — ' . (string) Settings::get('brand.title', 'evrako'),
        ]);
    }

    public function pricing(): string
    {
        return $this->view('site/pricing', [
            'title' => t('nav.pricing') . ' — ' . (string) Settings::get('brand.title', 'evrako'),
            'plans' => $this->plans(),
        ]);
    }

    public function contact(): string
    {
        return $this->view('site/contact', [
            'title' => t('nav.contact') . ' — ' . (string) Settings::get('brand.title', 'evrako'),
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
            'name'       => (string) $data['name'],
            'email'      => (string) $data['email'],
            'phone'      => $data['phone'] ?: null,
            'subject'    => $data['subject'] ?: null,
            'message'    => (string) $data['message'],
            'ip'         => $this->request->ip(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->flashSuccess(t('common.success'));
        $this->redirect('/iletisim');
    }

    /** @return array<int,array> */
    private function slides(): array
    {
        try {
            return Database::select(
                'SELECT * FROM slides WHERE is_active = 1 AND (locale IS NULL OR locale = ?) ORDER BY sort_order ASC',
                [Translator::locale()]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    private function plans(): array
    {
        try {
            return Database::select('SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order ASC, price ASC');
        } catch (\Throwable) {
            return [];
        }
    }

    private function faqs(): array
    {
        try {
            return Database::select('SELECT * FROM faqs WHERE is_active = 1 AND locale = ? ORDER BY sort_order ASC', [Translator::locale()]);
        } catch (\Throwable) {
            return [];
        }
    }
}
