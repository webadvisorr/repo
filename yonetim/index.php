<?php
declare(strict_types=1);

/**
 * evrako — yönetim paneli ön kontrolcüsü.
 * Adres: /yonetim/...
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\Admin\AuthController as AdminAuth;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\MembersController;
use App\Controllers\Admin\PaymentsController;
use App\Controllers\Admin\PlansController;
use App\Controllers\Admin\SettingsController;
use App\Controllers\Admin\SystemController;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Security;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;

if (!Config::installed()) {
    Response::redirect('/install.php');
}

Session::start();
Security::sendHeaders();

$request = Request::capture();
Translator::setLocale(Translator::detect());

$router = new Router();

$router->middleware('csrf', static function (Request $request, callable $next) {
    if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        return $next();
    }
    if (!\App\Core\Csrf::check((string) $request->input('_token', ''))) {
        \App\Core\Logger::warning('Panel CSRF dogrulamasi basarisiz', ['path' => $request->path(), 'ip' => $request->ip()]);
        http_response_code(419);
        echo View::render('errors/419', ['title' => 'Oturum süresi doldu'], 'layouts/admin');
        return false;
    }
    if (!$request->isSameOrigin()) {
        http_response_code(403);
        echo View::render('errors/403', ['title' => t('http.403')], 'layouts/admin');
        return false;
    }
    return $next();
});

$router->middleware('guest', static function (Request $request, callable $next) {
    if (Auth::check()) {
        Response::redirect('/yonetim/');
    }
    return $next();
});

$router->middleware('admin', static function (Request $request, callable $next) {
    if (!Auth::check()) {
        Session::put('_intended', $request->url());
        Response::redirect('/yonetim/giris');
    }
    if (!Auth::isAdmin()) {
        http_response_code(403);
        echo View::render('errors/403', ['title' => t('admin.no_access')], 'layouts/admin');
        return false;
    }
    return $next();
});

$router->middleware('superadmin', static function (Request $request, callable $next) {
    if (!Auth::isSuperAdmin()) {
        http_response_code(403);
        echo View::render('errors/403', ['title' => t('admin.no_access')], 'layouts/admin');
        return false;
    }
    return $next();
});

// ---- Giriş / çıkış ----
$router->get('/yonetim/giris', [AdminAuth::class, 'showLogin'], ['guest']);
$router->post('/yonetim/giris', [AdminAuth::class, 'login'], ['csrf', 'guest']);
$router->get('/yonetim/cikis', [AdminAuth::class, 'logout']);

// ---- Panel ----
$router->get('/yonetim', [DashboardController::class, 'index'], ['admin']);
$router->get('/yonetim/', [DashboardController::class, 'index'], ['admin']);
$router->get('/yonetim/panel', [DashboardController::class, 'index'], ['admin']);

// ---- Site ayarları ----
$router->get('/yonetim/ayarlar', [SettingsController::class, 'index'], ['admin']);
$router->get('/yonetim/ayarlar/{tab}', [SettingsController::class, 'index'], ['admin']);
$router->post('/yonetim/ayarlar/kaydet', [SettingsController::class, 'save'], ['admin', 'csrf']);
$router->post('/yonetim/ayarlar/medya-yukle', [SettingsController::class, 'uploadMedia'], ['admin', 'csrf']);
$router->post('/yonetim/ayarlar/medya-kaldir', [SettingsController::class, 'removeMedia'], ['admin', 'csrf']);
$router->post('/yonetim/ayarlar/test-mail', [SettingsController::class, 'testMail'], ['admin', 'csrf']);

// ---- Üyeler: kredi ve abonelik yönetimi ----
$router->get('/yonetim/uyeler', [MembersController::class, 'index'], ['admin']);
$router->get('/yonetim/uyeler/{id}', [MembersController::class, 'show'], ['admin']);
$router->post('/yonetim/uyeler/kredi-ekle', [MembersController::class, 'addCredit'], ['admin', 'csrf']);
$router->post('/yonetim/uyeler/kredi-dus', [MembersController::class, 'deductCredit'], ['admin', 'csrf']);
$router->post('/yonetim/uyeler/plan-ata', [MembersController::class, 'assignPlan'], ['admin', 'csrf']);
$router->post('/yonetim/uyeler/abonelik-uzat', [MembersController::class, 'extendSubscription'], ['admin', 'csrf']);
$router->post('/yonetim/uyeler/abonelik-iptal', [MembersController::class, 'cancelSubscription'], ['admin', 'csrf']);
$router->post('/yonetim/uyeler/abonelik-devam', [MembersController::class, 'resumeSubscription'], ['admin', 'csrf']);
$router->post('/yonetim/uyeler/kredi-ile-ode', [MembersController::class, 'payWithCredit'], ['admin', 'csrf']);
$router->post('/yonetim/uyeler/durum', [MembersController::class, 'updateStatus'], ['admin', 'csrf']);
$router->post('/yonetim/uyeler/profil', [MembersController::class, 'updateProfile'], ['admin', 'csrf']);
$router->post('/yonetim/uyeler/sifre-baglantisi', [MembersController::class, 'sendResetLink'], ['admin', 'csrf']);

// ---- Planlar ----
$router->get('/yonetim/planlar', [PlansController::class, 'index'], ['admin']);
$router->post('/yonetim/planlar/kaydet', [PlansController::class, 'update'], ['admin', 'csrf']);
$router->post('/yonetim/planlar/ekle', [PlansController::class, 'store'], ['admin', 'csrf']);
$router->post('/yonetim/planlar/sil', [PlansController::class, 'destroy'], ['admin', 'csrf']);

// ---- Ödemeler ----
$router->get('/yonetim/odemeler', [PaymentsController::class, 'index'], ['admin']);
$router->post('/yonetim/odemeler/kaydet', [PaymentsController::class, 'store'], ['admin', 'csrf']);
$router->post('/yonetim/odemeler/durum', [PaymentsController::class, 'updateStatus'], ['admin', 'csrf']);

// ---- Sistem ----
$router->get('/yonetim/sistem', [SystemController::class, 'index'], ['admin']);
$router->post('/yonetim/sistem/migrate', [SystemController::class, 'migrate'], ['admin', 'csrf']);
$router->post('/yonetim/sistem/cron', [SystemController::class, 'runCron'], ['admin', 'csrf']);

$router->fallback(static function (int $status): string {
    return View::render('errors/' . $status, ['title' => t('http.' . $status)], 'layouts/admin');
});

$router->dispatch($request);
