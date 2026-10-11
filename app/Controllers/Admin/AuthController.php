<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Flash;
use App\Core\Session;
use App\Core\Translator;

final class AuthController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function showLogin(): string
    {
        return $this->view('admin/login', ['title' => t('admin.login_title')], 'layouts/auth');
    }

    public function login(): void
    {
        $email = $this->request->string('email');
        $password = (string) $this->request->input('password', '');

        $this->validate(
            ['email' => $email, 'password' => $password],
            ['email' => 'required|email|max:190', 'password' => 'required|min:8'],
            ['email' => t('auth.email'), 'password' => t('auth.password')],
            '/yonetim/giris'
        );

        $result = Auth::attempt($email, $password, $this->request->bool('remember'));
        if (!$result['ok']) {
            Flash::error(t('auth.' . $result['error']));
            Session::put('_old', ['email' => $email]);
            $this->redirect('/yonetim/giris');
        }

        if (!Auth::isAdmin()) {
            Auth::logout();
            Flash::error(t('admin.no_access'));
            $this->redirect('/yonetim/giris');
        }

        $intended = Session::get('_intended');
        Session::forget('_intended');
        if (is_string($intended) && $intended !== '' && str_starts_with($intended, '/yonetim')) {
            $this->redirect($intended);
        }
        $this->redirect('/yonetim/');
    }

    public function logout(): void
    {
        Auth::logout();
        Flash::success(t('auth.logout'));
        $this->redirect('/yonetim/giris');
    }
}
