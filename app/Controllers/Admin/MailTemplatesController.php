<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Mailer;
use App\Core\Translator;
use App\Services\MailTemplateService;

/**
 * Mail şablon editörü (6 dil).
 *
 * Şablonlar `mail_templates` tablosunda (key + locale) tutulur. Değişkenler:
 * {ad} {plaka} {evrak} {bitis} {kalan_gun} {link} ...
 */
final class MailTemplatesController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function index(): string
    {
        // Panel ilk açıldığında eksik şablonlar varsayılanla dolar.
        $created = MailTemplateService::ensureDefaults();

        $rows = MailTemplateService::all();
        $matrix = [];
        foreach (MailTemplateService::KEYS as $key) {
            foreach (MailTemplateService::LOCALES as $locale) {
                $matrix[$key][$locale] = null;
            }
        }
        foreach ($rows as $row) {
            $matrix[(string) $row['key']][(string) $row['locale']] = $row;
        }

        return $this->view('admin/mail_templates', [
            'title'    => t('admin.mail_templates'),
            'keys'     => MailTemplateService::KEYS,
            'locales'  => MailTemplateService::LOCALES,
            'names'    => Translator::NAMES,
            'matrix'   => $matrix,
            'variables' => MailTemplateService::VARIABLES,
            'created'  => $created,
            'layoutVars' => [
                'marka_adi' => (string) \App\Core\Settings::get('brand.title', 'Evrako'),
                'logo_url'  => MailTemplateService::logoUrl(),
            ],
        ]);
    }

    public function edit(string $key, string $locale = 'tr'): string
    {
        if (!in_array($key, MailTemplateService::KEYS, true)) {
            $this->flashError(t('mail_templates.not_found'));
            $this->redirect('/yonetim/sablonlar');
        }
        if (!in_array($locale, MailTemplateService::LOCALES, true)) {
            $locale = 'tr';
        }

        $template = MailTemplateService::find($key, $locale);

        return $this->view('admin/mail_template_edit', [
            'title'       => t('admin.mail_templates') . ' — ' . $key . ' / ' . $locale,
            'key'         => $key,
            'locale'      => $locale,
            'locales'     => MailTemplateService::LOCALES,
            'names'       => Translator::NAMES,
            'template'    => $template,
            'isDefault'   => $template !== null && (int) ($template['id'] ?? 0) === 0,
            'variables'   => MailTemplateService::VARIABLES,
            'smtpReady'   => (new Mailer())->isConfigured(),
            'sample'      => MailTemplateService::sampleVars(null, null, null, $locale),
        ]);
    }

    public function save(): void
    {
        $key = (string) $this->request->input('key', '');
        $locale = (string) $this->request->input('locale', 'tr');
        $backTo = '/yonetim/sablonlar/' . urlencode($key) . '/' . urlencode($locale);

        if (!in_array($key, MailTemplateService::KEYS, true) || !in_array($locale, MailTemplateService::LOCALES, true)) {
            $this->flashError(t('mail_templates.not_found'));
            $this->redirect('/yonetim/sablonlar');
        }

        $name = trim($this->request->string('name'));
        $subject = trim($this->request->string('subject'));
        $bodyHtml = (string) $this->request->input('body_html', '');
        $bodyText = (string) $this->request->input('body_text', '');

        if ($subject === '' || trim($bodyHtml) === '') {
            $this->flashError(t('mail_templates.required'));
            $this->redirect($backTo);
        }

        $data = [
            'name'           => $name === '' ? $key : mb_substr($name, 0, 120),
            'subject'        => mb_substr($subject, 0, 255),
            'body_html'      => $bodyHtml,
            'body_text'      => $bodyText === '' ? null : $bodyText,
            'variables_json' => json_encode(array_keys(MailTemplateService::VARIABLES), JSON_UNESCAPED_UNICODE),
            'is_active'      => $this->request->bool('is_active') ? 1 : 0,
            'updated_by'     => \App\Core\Auth::id(),
            'updated_at'     => date('Y-m-d H:i:s'),
        ];

        $existing = Database::first('SELECT id FROM mail_templates WHERE `key` = ? AND locale = ? LIMIT 1', [$key, $locale]);
        if ($existing === null) {
            $data['key'] = $key;
            $data['locale'] = $locale;
            $data['created_at'] = date('Y-m-d H:i:s');
            Database::insert('mail_templates', $data);
        } else {
            Database::update('mail_templates', $data, 'id = :id', ['id' => (int) $existing['id']]);
        }

        Audit::log('mail_template.update', 'mail_template', $key . ':' . $locale);
        $this->flashSuccess(t('mail_templates.saved'));
        $this->redirect($backTo);
    }

    /** Şablonu varsayılan (kod içindeki) hâline döndürür. */
    public function reset(): void
    {
        $key = (string) $this->request->input('key', '');
        $locale = (string) $this->request->input('locale', 'tr');
        $defaults = MailTemplateService::defaults();

        if (!isset($defaults[$key][$locale])) {
            $this->flashError(t('mail_templates.not_found'));
            $this->redirect('/yonetim/sablonlar');
        }
        $default = $defaults[$key][$locale];

        $existing = Database::first('SELECT id FROM mail_templates WHERE `key` = ? AND locale = ? LIMIT 1', [$key, $locale]);
        $data = [
            'name'       => $default['name'],
            'subject'    => $default['subject'],
            'body_html'  => $default['body_html'],
            'body_text'  => $default['body_text'],
            'is_active'  => 1,
            'updated_by' => \App\Core\Auth::id(),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($existing === null) {
            $data['key'] = $key;
            $data['locale'] = $locale;
            $data['created_at'] = date('Y-m-d H:i:s');
            Database::insert('mail_templates', $data);
        } else {
            Database::update('mail_templates', $data, 'id = :id', ['id' => (int) $existing['id']]);
        }

        Audit::log('mail_template.reset', 'mail_template', $key . ':' . $locale);
        $this->flashSuccess(t('mail_templates.reset_done'));
        $this->redirect('/yonetim/sablonlar/' . urlencode($key) . '/' . urlencode($locale));
    }

    /** Örnek verilerle önizleme (tam HTML çıktı). */
    public function preview(): string
    {
        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex, nofollow');

        $key = (string) $this->request->input('key', '');
        $locale = (string) $this->request->input('locale', 'tr');
        if (!in_array($key, MailTemplateService::KEYS, true) || !in_array($locale, MailTemplateService::LOCALES, true)) {
            http_response_code(404);
            return '<p>Şablon bulunamadı.</p>';
        }

        $previous = Translator::locale();
        Translator::setLocale($locale);
        $rendered = MailTemplateService::render($key, $locale, MailTemplateService::sampleVars(null, null, null, $locale));
        Translator::setLocale($previous);

        if (!$rendered['ok']) {
            http_response_code(500);
            return '<p>Şablon işlenemedi.</p>';
        }

        return $rendered['html'];
    }

    /** Şablonu gerçek SMTP ile test gönderimi. */
    public function testSend(): void
    {
        $key = (string) $this->request->input('key', '');
        $locale = (string) $this->request->input('locale', 'tr');
        $to = trim($this->request->string('test_email'));
        $backTo = '/yonetim/sablonlar/' . urlencode($key) . '/' . urlencode($locale);

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            $this->flashError(t('validation.email', ['field' => t('auth.email')]));
            $this->redirect($backTo);
        }
        if (!in_array($key, MailTemplateService::KEYS, true) || !in_array($locale, MailTemplateService::LOCALES, true)) {
            $this->flashError(t('mail_templates.not_found'));
            $this->redirect('/yonetim/sablonlar');
        }

        $mailer = new Mailer();
        if (!$mailer->isConfigured()) {
            $this->flashError(t('settings.smtp_test_failed', ['error' => 'SMTP ayarları eksik']));
            $this->redirect($backTo);
        }

        $previous = Translator::locale();
        Translator::setLocale($locale);
        $rendered = MailTemplateService::render($key, $locale, MailTemplateService::sampleVars(null, null, null, $locale));
        Translator::setLocale($previous);

        if (!$rendered['ok']) {
            $this->flashError(t('mail_templates.preview_failed'));
            $this->redirect($backTo);
        }

        $send = $mailer->send($to, $rendered['subject'], $rendered['html'], $rendered['text']);
        if ($send['ok']) {
            Audit::log('mail_template.test', 'mail_template', $key . ':' . $locale, ['to' => $to]);
            $this->flashSuccess(t('mail_templates.test_sent'));
        } else {
            $this->flashError(t('settings.smtp_test_failed', ['error' => (string) ($send['error'] ?? '')]));
        }
        $this->redirect($backTo);
    }
}
