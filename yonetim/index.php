<?php
declare(strict_types=1);

/**
 * evrako — yönetim paneli ön kontrolcüsü.
 * Adres: /yonetim/...
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\Admin\AuthController as AdminAuth;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\SettingsController;
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

$router->fallback(static function (int $status): string {
    return View::render('errors/' . $status, ['title' => t('http.' . $status)], 'layouts/admin');
});

$router->dispatch($request);
