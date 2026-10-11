<?php
declare(strict_types=1);

/**
 * evrako — site on kontrolcusu (front controller).
 * Tüm genel istekler buradan geçer.
 */
require __DIR__ . '/app/bootstrap.php';

use App\Controllers\Site\AuthController;
use App\Controllers\Site\HomeController;
use App\Controllers\Site\MediaController;
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

// ---- Dil secimi / degistirme ----
if ($localeParam = $request->input('dil')) {
    $localeParam = (string) $localeParam;
    if (in_array($localeParam, Translator::SUPPORTED, true)) {
        setcookie('evrako_locale', $localeParam, [
            'expires' => time() + 31536000, 'path' => '/',
            'secure' => $request->isSecure(), 'httponly' => true, 'samesite' => 'Lax',
        ]);
        Translator::setLocale($localeParam);
    }
} else {
    Translator::setLocale(Translator::detect());
}

// ---- Guvenli medya servisi: /media/{diskPath} ----
if (str_starts_with($request->path(), '/media/')) {
    (new MediaController())->show();
    exit;
}

// ---- Bakim modu ----
$homeController = new HomeController();

$router = new Router();

// Middleware: CSRF (POST isteklerinde token dogrulama)
$router->middleware('csrf', static function (Request $request, callable $next) {
    if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        return $next();
    }
    $token = (string) $request->input('_token', '');
    if (!\App\Core\Csrf::check($token)) {
        \App\Core\Logger::warning('CSRF dogrulamasi basarisiz', ['path' => $request->path(), 'ip' => $request->ip()]);
        http_response_code(419);
        echo View::render('errors/419', ['title' => 'Oturum süresi doldu'], null);
        return false;
    }
    if (!$request->isSameOrigin()) {
        http_response_code(403);
        echo View::render('errors/403', ['title' => t('http.403')], null);
        return false;
    }
    return $next();
});

// Middleware: misafir (giris yapmis kullaniciyi panele gonder)
$router->middleware('guest', static function (Request $request, callable $next) {
    if (\App\Core\Auth::check()) {
        Response::redirect('/panel');
    }
    return $next();
});

// Middleware: giris zorunlu
$router->middleware('auth', static function (Request $request, callable $next) {
    if (!\App\Core\Auth::check()) {
        Session::put('_intended', $request->url());
        \App\Core\Flash::info(t('auth.session_expired'));
        Response::redirect('/giris');
    }
    return $next();
});

// ---- Genel rotalar ----
$router->get('/', [HomeController::class, 'index']);
$router->get('/nasil-calisir', [HomeController::class, 'howItWorks']);
$router->get('/fiyatlar', [HomeController::class, 'pricing']);
$router->get('/iletisim', [HomeController::class, 'contact']);
$router->post('/iletisim', [HomeController::class, 'contactSubmit'], ['csrf']);

$router->get('/giris', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/giris', [AuthController::class, 'login'], ['csrf', 'guest']);
$router->get('/kayit', [AuthController::class, 'showRegister'], ['guest']);
$router->post('/kayit', [AuthController::class, 'register'], ['csrf', 'guest']);
$router->get('/cikis', [AuthController::class, 'logout']);
$router->get('/sifremi-unuttum', [AuthController::class, 'showForgot'], ['guest']);
$router->post('/sifremi-unuttum', [AuthController::class, 'sendReset'], ['csrf', 'guest']);
$router->get('/panel', [AuthController::class, 'dashboard'], ['auth']);

$router->fallback(static function (int $status): string {
    return View::render('errors/' . $status, ['title' => t('http.' . $status)], null);
});

$router->dispatch($request);
