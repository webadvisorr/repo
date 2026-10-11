<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Mailer;
use App\Core\RateLimiter;
use App\Core\Settings;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;

final class AuthController extends Controller
{
    public function showLogin(): string
    {
        return $this->view('site/auth_login', ['title' => t('auth.login')]);
    }

    public function login(): void
    {
        $email = $this->request->string('email');
        $password = (string) $this->request->input('password', '');
        $remember = $this->request->bool('remember');

        $this->validate(
            ['email' => $email, 'password' => $password],
            ['email' => 'required|email|max:190', 'password' => 'required|min:8'],
            ['email' => t('auth.email'), 'password' => t('auth.password')],
            '/giris'
        );

        $result = Auth::attempt($email, $password, $remember);
        if (!$result['ok']) {
            Flash::error(t('auth.' . $result['error']));
            Session::put('_old', ['email' => $email]);
            $this->redirect('/giris');
        }

        $user = $result['user'] ?? null;
        if ($user !== null && (string) $user['role'] !== 'customer') {
            $this->redirect('/yonetim/');
        }

        $intended = Session::get('_intended');
        Session::forget('_intended');
        if (is_string($intended) && $intended !== '' && !str_contains($intended, '//')) {
            $this->redirect($intended);
        }
        $this->redirect('/panel');
    }

    public function showRegister(): string
    {
        $open = (string) Settings::get('site.registration_open', '1') === '1';
        return $this->view('site/auth_register', ['title' => t('auth.register'), 'registrationOpen' => $open]);
    }

    public function register(): void
    {
        if ((string) Settings::get('site.registration_open', '1') !== '1') {
            Flash::error(t('common.error'));
            $this->redirect('/kayit');
        }

        $request = new \App\Core\Request();
        if (RateLimiter::tooManyAttempts('register:' . $request->ip(), 10, 3600)) {
            Flash::error(t('auth.too_many_requests'));
            $this->redirect('/kayit');
        }
        RateLimiter::hit('register:' . $request->ip(), 3600);

        $data = $this->validate(
            [
                'full_name' => $this->request->string('full_name'),
                'email'     => mb_strtolower($this->request->string('email')),
                'phone'     => $this->request->string('phone'),
                'password'  => (string) $this->request->input('password', ''),
                'password_confirmation' => (string) $this->request->input('password_confirmation', ''),
                'terms'     => $this->request->bool('terms'),
            ],
            [
                'full_name' => 'required|min:3|max:160',
                'email'     => 'required|email|max:190|unique:users,email',
                'phone'     => 'nullable|phone|max:32',
                'password'  => 'required|password|confirmed|max:100',
                'terms'     => 'accepted',
            ],
            [
                'full_name' => t('auth.name'), 'email' => t('auth.email'), 'phone' => t('auth.phone'),
                'password'  => t('auth.password'), 'terms' => t('auth.terms'),
            ],
            '/kayit'
        );

        $userId = Auth::register([
            'full_name'        => (string) $data['full_name'],
            'email'            => (string) $data['email'],
            'phone'            => $data['phone'] ?: null,
            'password'         => (string) $data['password'],
            'role'             => 'customer',
            'locale'           => Translator::locale(),
            'marketing_opt_in' => $this->request->bool('marketing_opt_in') ? 1 : 0,
        ]);

        // Kisisel/firma alani olustur (filo ve coklu kullanici altyapisi)
        $now = date('Y-m-d H:i:s');
        $orgId = Database::insert('organizations', [
            'owner_user_id' => $userId,
            'name'          => (string) $data['full_name'],
            'type'          => 'individual',
            'created_at'    => $now,
        ]);
        Database::insert('organization_members', [
            'organization_id' => $orgId,
            'user_id'         => $userId,
            'role'            => 'owner',
            'can_manage_vehicles'  => 1,
            'can_manage_documents' => 1,
            'can_manage_billing'   => 1,
            'status'          => 'active',
            'created_at'      => $now,
        ]);

        // Deneme/ücretsiz plan ataması
        $plan = Database::first("SELECT * FROM plans WHERE `key` = 'free' LIMIT 1")
            ?? Database::first('SELECT * FROM plans WHERE is_active = 1 ORDER BY price ASC LIMIT 1');
        if ($plan !== null) {
            $trialDays = (int) Settings::get('site.trial_days', 14);
            Database::insert('subscriptions', [
                'organization_id'      => $orgId,
                'plan_id'              => (int) $plan['id'],
                'status'               => $trialDays > 0 ? 'trialing' : 'active',
                'provider'             => 'manual',
                'current_period_start' => $now,
                'current_period_end'   => date('Y-m-d H:i:s', strtotime('+' . max($trialDays, 30) . ' days')),
                'trial_ends_at'        => $trialDays > 0 ? date('Y-m-d H:i:s', strtotime("+{$trialDays} days")) : null,
                'created_at'           => $now,
            ]);
        }

        Auth::login($userId);

        // E-posta doğrulama bağlantısı
        $token = Auth::createToken($userId, 'email_verify', 86400 * 3);
        $link = url('/dogrula?token=' . $token);
        $mailer = new Mailer();
        if ($mailer->isConfigured()) {
            $mailer->send(
                (string) $data['email'],
                t('auth.register') . ' — ' . (string) Settings::get('brand.title', 'evrako'),
                '<p>' . e((string) $data['full_name']) . ', hoş geldiniz!</p><p>Hesabınızı doğrulamak için: <a href="' . e($link) . '">' . e($link) . '</a></p>'
            );
        }

        Flash::success(t('common.success'));
        $this->redirect('/panel');
    }

    public function showForgot(): string
    {
        return $this->view('site/auth_forgot', ['title' => t('auth.reset_title')]);
    }

    public function sendReset(): void
    {
        $email = mb_strtolower($this->request->string('email'));
        $this->validate(['email' => $email], ['email' => 'required|email|max:190'], ['email' => t('auth.email')], '/sifremi-unuttum');

        $request = new \App\Core\Request();
        if (RateLimiter::tooManyAttempts('reset:' . $request->ip(), 5, 3600)) {
            Flash::error(t('auth.too_many_requests'));
            $this->redirect('/sifremi-unuttum');
        }
        RateLimiter::hit('reset:' . $request->ip(), 3600);

        $user = Database::first('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1', [$email]);
        if ($user !== null) {
            $token = Auth::createToken((int) $user['id'], 'password_reset', 3600);
            $link = url('/sifre-sifirla?token=' . $token);
            $mailer = new Mailer();
            $mailer->send(
                $email,
                t('auth.reset_title'),
                '<p>Şifre sıfırlama bağlantınız (1 saat geçerlidir):</p><p><a href="' . e($link) . '">' . e($link) . '</a></p>'
            );
        }

        Flash::success(t('auth.reset_sent'));
        $this->redirect('/sifremi-unuttum');
    }

    public function logout(): void
    {
        Auth::logout();
        Flash::success(t('auth.logout'));
        $this->redirect('/');
    }

    public function dashboard(): string
    {
        $user = Auth::user();
        $orgId = $user === null ? null : Database::scalar(
            'SELECT organization_id FROM organization_members WHERE user_id = ? AND status = "active" LIMIT 1',
            [(int) $user['id']]
        );

        $vehicleCount = $orgId ? (int) Database::scalar('SELECT COUNT(*) FROM vehicles WHERE organization_id = ? AND deleted_at IS NULL', [$orgId]) : 0;
        $documentCount = $orgId ? (int) Database::scalar(
            'SELECT COUNT(*) FROM vehicle_documents d JOIN vehicles v ON v.id = d.vehicle_id WHERE v.organization_id = ? AND d.status = "active" AND d.deleted_at IS NULL',
            [$orgId]
        ) : 0;
        $upcoming = $orgId ? Database::select(
            'SELECT d.*, dt.labels_json, v.plate, v.brand, v.model
             FROM vehicle_documents d
             JOIN vehicles v ON v.id = d.vehicle_id
             JOIN document_types dt ON dt.id = d.document_type_id
             WHERE v.organization_id = ? AND d.status = "active" AND d.deleted_at IS NULL AND d.end_date >= CURDATE()
             ORDER BY d.end_date ASC LIMIT 10',
            [$orgId]
        ) : [];

        return $this->view('site/dashboard', [
            'title'         => t('nav.dashboard'),
            'vehicleCount'  => $vehicleCount,
            'documentCount' => $documentCount,
            'upcoming'      => $upcoming,
        ]);
    }
}
