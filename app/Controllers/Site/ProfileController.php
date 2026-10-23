<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\RateLimiter;
use App\Services\ProfileChangeService;

/**
 * Müşteri paneli — hesap bilgilerim.
 * Değişiklikler doğrudan uygulanmaz: şifre doğrulamasıyla talep oluşturulur,
 * yönetici onayından sonra hesaba işlenir.
 */
final class ProfileController extends Controller
{
    public function index(): string
    {
        $userId = (int) (Auth::id() ?? 0);
        $user = Auth::find($userId) ?? Auth::user() ?? [];
        $pending = ProfileChangeService::pendingForUser($userId);

        return $this->view('site/profile', [
            'title'        => 'Hesap bilgilerim',
            'profileUser'  => $user,
            'pending'      => $pending,
            'pendingRows'  => $pending !== null ? ProfileChangeService::describe($pending, $user) : [],
            'history'      => ProfileChangeService::historyForUser($userId, 6),
            'locales'      => \App\Core\Translator::NAMES,
        ]);
    }

    public function submit(): void
    {
        $userId = (int) (Auth::id() ?? 0);
        $key = 'profile-request:' . $userId;
        if (RateLimiter::tooManyAttempts($key, 5, 3600)) {
            $this->flashError('Çok fazla talep gönderdiniz. Lütfen bir saat sonra tekrar deneyin.');
            $this->redirect('/panel/profil');
        }
        RateLimiter::hit($key, 3600);

        $result = ProfileChangeService::submit($userId, [
            'full_name' => (string) $this->request->input('full_name', ''),
            'email'     => (string) $this->request->input('email', ''),
            'phone'     => (string) $this->request->input('phone', ''),
            'whatsapp'  => (string) $this->request->input('whatsapp', ''),
            'locale'    => (string) $this->request->input('locale', 'tr'),
        ], (string) $this->request->input('current_password', ''));

        if (!$result['ok']) {
            $this->flashError((string) $result['error']);
            $this->redirect('/panel/profil');
        }

        $this->flashSuccess('Değişiklik talebiniz alındı. Yönetici onayından sonra bilgileriniz güncellenecek.');
        $this->redirect('/panel/profil');
    }

    public function cancel(): void
    {
        $userId = (int) (Auth::id() ?? 0);
        $id = $this->request->int('id');
        if (ProfileChangeService::cancel($userId, $id)) {
            $this->flashSuccess('Profil güncelleme talebiniz geri çekildi.');
        } else {
            $this->flashError('Talep bulunamadı veya daha önce işlendi.');
        }
        $this->redirect('/panel/profil');
    }
}
