<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Logger;
use App\Services\MailTemplateService;

/**
 * Abonelikten çıkma (KVKK / e-posta izni).
 *
 * Bağlantılar imzalıdır (`MailTemplateService::unsubscribeToken`), oturum gerekmez.
 * - GET  /abonelikten-cikar?e=..&t=..      → onay sayfası
 * - POST /abonelikten-cikar                → listeye ekle (CSRF korumalı)
 * - POST /abonelikten-cikar/tek-tik?e=..   → tek tık (RFC 8058, CSRF'siz; token imzası yeterli)
 * - POST /abonelikten-cikar/yeniden-ac     → yeniden aç (CSRF korumalı)
 */
final class UnsubscribeController extends Controller
{
    public function show(): string
    {
        $encoded = (string) $this->request->input('e', '');
        $token = (string) $this->request->input('t', '');
        $email = $this->email();
        if ($email === null) {
            http_response_code(404);
            return $this->view('site/unsubscribe', [
                'title'        => t('unsub.title'),
                'valid'        => false,
                'email'        => null,
                'encoded'      => '',
                'token'        => '',
                'unsubscribed' => false,
            ]);
        }

        return $this->view('site/unsubscribe', [
            'title'        => t('unsub.title'),
            'valid'        => true,
            'email'        => $email,
            'encoded'      => $encoded,
            'token'        => $token,
            'unsubscribed' => MailTemplateService::isUnsubscribed($email),
        ]);
    }

    public function confirm(): void
    {
        $email = $this->email();
        if ($email === null) {
            $this->flashError(t('unsub.invalid'));
            $this->redirect('/');
        }

        MailTemplateService::unsubscribe($email, 'all', 'email_link');
        Audit::log('mail.unsubscribe', 'unsubscribe', null, ['email' => $email]);
        Logger::info('E-posta listesinden çıkıldı', ['email' => $email]);
        $this->flashSuccess(t('unsub.done'));
        $this->redirect('/abonelikten-cikar?' . $this->query($email));
    }

    /** RFC 8058 tek tık (e-posta istemcileri POST eder; CSRF tokenı gönderilemez). */
    public function oneClick(): string
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');

        $email = $this->email();
        if ($email === null) {
            http_response_code(400);
            return "Geçersiz bağlantı.\n";
        }

        MailTemplateService::unsubscribe($email, 'all', 'one_click');
        Audit::log('mail.unsubscribe', 'unsubscribe', null, ['email' => $email, 'method' => 'one_click']);
        return "Bu e-posta adresi listemizden çıkarıldı.\n";
    }

    public function resubscribe(): void
    {
        $email = $this->email();
        if ($email === null) {
            $this->flashError(t('unsub.invalid'));
            $this->redirect('/');
        }

        MailTemplateService::resubscribe($email);
        Audit::log('mail.resubscribe', 'unsubscribe', null, ['email' => $email]);
        $this->flashSuccess(t('unsub.reopened'));
        $this->redirect('/abonelikten-cikar?' . $this->query($email));
    }

    /** İmzalı bağlantıdan e-posta adresi (geçersizse null). */
    private function email(): ?string
    {
        $encoded = (string) $this->request->input('e', '');
        $token = (string) $this->request->input('t', '');
        if ($encoded === '' || $token === '') {
            return null;
        }
        return MailTemplateService::emailFromToken($encoded, $token);
    }

    private function query(string $email): string
    {
        return http_build_query([
            'e' => rtrim(strtr(base64_encode(mb_strtolower(trim($email))), '+/', '-_'), '='),
            't' => MailTemplateService::unsubscribeToken($email),
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
