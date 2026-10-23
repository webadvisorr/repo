<?php
declare(strict_types=1);

/**
 * evrako — site on kontrolcusu (front controller).
 * Tüm genel istekler buradan geçer.
 */
require __DIR__ . '/app/bootstrap.php';

use App\Controllers\Site\AssistantController;
use App\Controllers\Site\AuthController;
use App\Controllers\Site\BillingController;
use App\Controllers\Site\CalendarController;
use App\Controllers\Site\CronController;
use App\Controllers\Site\DocumentController;
use App\Controllers\Site\HomeController;
use App\Controllers\Site\InboxController;
use App\Controllers\Site\MediaController;
use App\Controllers\Site\MessagesController;
use App\Controllers\Site\ProfileController;
use App\Controllers\Site\PushController;
use App\Controllers\Site\SupportController;
use App\Controllers\Site\TeamController;
use App\Controllers\Site\TrackingController;
use App\Controllers\Site\UnsubscribeController;
use App\Controllers\Site\VehicleController;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Security;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Services\CurrencyService;
use App\Services\PresenceService;

if (!Config::installed()) {
    Response::redirect('/install.php');
}

Session::start();
Security::sendHeaders();

$request = Request::capture();

// Canlı ziyaretçi sayacı (yönetim panelindeki "Şu an sitede" kutusu).
PresenceService::touch($request);

// ---- E-posta doğrulanmadan müşteri paneline erişim yok ----
if (\App\Core\Auth::check() && !\App\Core\Auth::isEmailVerified() && str_starts_with($request->path(), '/panel')) {
    \App\Core\Flash::info(t('auth.email_unverified'));
    Response::redirect('/dogrula');
}

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

// ---- Goruntuleme para birimi (?para=USD) ----
if ($currencyParam = $request->input('para')) {
    CurrencyService::select((string) $currencyParam);
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
        echo View::render('errors/419', ['title' => __('Oturum süresi doldu')], 'layouts/site');
        return false;
    }
    if (!$request->isSameOrigin()) {
        http_response_code(403);
        echo View::render('errors/403', ['title' => t('http.403')], 'layouts/site');
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
$router->get('/tesekkurler', [HomeController::class, 'thanks']);

// ---- Site asistanı (Gemini) ----
$router->post('/asistan', [AssistantController::class, 'message'], ['csrf']);
$router->post('/asistan/temizle', [AssistantController::class, 'clear'], ['csrf']);

// ---- Yasal sayfalar ----
$router->get('/gizlilik', [HomeController::class, 'privacy']);
$router->get('/kullanim-kosullari', [HomeController::class, 'terms']);

// ---- SEO / makine okunur dosyalar ----
$router->get('/robots.txt', [HomeController::class, 'robots']);
$router->get('/sitemap.xml', [HomeController::class, 'sitemap']);

// ---- Cron / e-posta takip / abonelikten çıkma (Faz 3) ----
$router->get('/cron-hatirlatma', [CronController::class, 'run']);
$router->post('/cron-hatirlatma', [CronController::class, 'run']);
$router->get('/mail-piksel/{id}/{token}', [TrackingController::class, 'pixel']);
$router->get('/abonelikten-cikar', [UnsubscribeController::class, 'show']);
$router->post('/abonelikten-cikar', [UnsubscribeController::class, 'confirm'], ['csrf']);
$router->post('/abonelikten-cikar/yeniden-ac', [UnsubscribeController::class, 'resubscribe'], ['csrf']);
// RFC 8058 tek tık: e-posta istemcisi CSRF tokenı gönderemez, bağlantı imzası yeterlidir.
$router->post('/abonelikten-cikar/tek-tik', [UnsubscribeController::class, 'oneClick']);

$router->get('/giris', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/giris', [AuthController::class, 'login'], ['csrf', 'guest']);
$router->get('/kayit', [AuthController::class, 'showRegister'], ['guest']);
$router->post('/kayit', [AuthController::class, 'register'], ['csrf', 'guest']);
$router->get('/cikis', [AuthController::class, 'logout']);
$router->get('/sifremi-unuttum', [AuthController::class, 'showForgot'], ['guest']);
$router->post('/sifremi-unuttum', [AuthController::class, 'sendReset'], ['csrf', 'guest']);
$router->get('/dogrula', [AuthController::class, 'showVerify']);
$router->post('/dogrula', [AuthController::class, 'verify'], ['csrf']);
$router->post('/dogrula/yeniden-gonder', [AuthController::class, 'resendVerification'], ['csrf']);
$router->get('/panel', [AuthController::class, 'dashboard'], ['auth']);

// ---- Müşteri hesap bilgileri (yönetici onaylı güncelleme) ----
$router->get('/panel/profil', [ProfileController::class, 'index'], ['auth']);
$router->post('/panel/profil', [ProfileController::class, 'submit'], ['auth', 'csrf']);
$router->post('/panel/profil/iptal', [ProfileController::class, 'cancel'], ['auth', 'csrf']);

// ---- Cihaz bildirimleri (Web Push) ----
$router->get('/sw.js', [PushController::class, 'serviceWorker']);
$router->get('/panel/bildirimler/cihaz', [PushController::class, 'status'], ['auth']);
$router->post('/panel/bildirimler/cihaz', [PushController::class, 'subscribe'], ['auth', 'csrf']);
$router->post('/panel/bildirimler/cihaz/kapat', [PushController::class, 'unsubscribe'], ['auth', 'csrf']);

// ---- Site içi bildirim merkezi (zil + bildirimler) ----
$router->get('/panel/bildirimler', [InboxController::class, 'index'], ['auth']);
$router->get('/panel/bildirimler/ozet', [InboxController::class, 'summary'], ['auth']);
$router->post('/panel/bildirimler/oku-tur', [InboxController::class, 'markKind'], ['auth', 'csrf']);
$router->post('/panel/bildirimler/oku', [InboxController::class, 'markRead'], ['auth', 'csrf']);
$router->post('/panel/bildirimler/tumu-oku', [InboxController::class, 'markAll'], ['auth', 'csrf']);

// ---- Müşteri paneli: iletişim mesajları ve destek talepleri ----
$router->get('/panel/mesajlar', [MessagesController::class, 'index'], ['auth']);
$router->post('/panel/mesajlar/yanit', [MessagesController::class, 'reply'], ['auth', 'csrf']);
$router->get('/panel/mesajlar/{id}', [MessagesController::class, 'show'], ['auth']);

$router->get('/panel/destek', [SupportController::class, 'index'], ['auth']);
$router->get('/panel/destek/yeni', [SupportController::class, 'create'], ['auth']);
$router->post('/panel/destek', [SupportController::class, 'store'], ['auth', 'csrf']);
$router->post('/panel/destek/yanit', [SupportController::class, 'reply'], ['auth', 'csrf']);
$router->post('/panel/destek/kapat', [SupportController::class, 'close'], ['auth', 'csrf']);
$router->get('/panel/destek/{id}', [SupportController::class, 'show'], ['auth']);

// ---- Ödeme merkezi (şimdilik havale/EFT) ----
$router->get('/panel/odeme', [BillingController::class, 'index'], ['auth']);
$router->post('/panel/odeme/kredi', [BillingController::class, 'startCredit'], ['auth', 'csrf']);
$router->post('/panel/odeme/abonelik', [BillingController::class, 'startSubscription'], ['auth', 'csrf']);
$router->post('/panel/odeme/deneme', [BillingController::class, 'startTrial'], ['auth', 'csrf']);
$router->post('/panel/odeme/abonelik/kredi', [BillingController::class, 'payWithCredit'], ['auth', 'csrf']);
$router->get('/panel/odeme/talep/{id}', [BillingController::class, 'show'], ['auth']);
$router->post('/panel/odeme/talep/{id}/bildir', [BillingController::class, 'declare'], ['auth', 'csrf']);
$router->post('/panel/odeme/talep/{id}/iptal', [BillingController::class, 'cancel'], ['auth', 'csrf']);
$router->get('/panel/odemelerim', [BillingController::class, 'history'], ['auth']);

// ---- Araç & evrak modülü (Faz 2) ----
$router->get('/panel/araclar', [VehicleController::class, 'index'], ['auth']);
$router->get('/panel/araclar/yeni', [VehicleController::class, 'create'], ['auth']);
$router->get('/panel/araclar/{id}/duzenle', [VehicleController::class, 'edit'], ['auth']);
$router->get('/panel/araclar/{id}', [VehicleController::class, 'show'], ['auth']);
$router->post('/panel/araclar', [VehicleController::class, 'store'], ['auth', 'csrf']);
$router->post('/panel/araclar/{id}', [VehicleController::class, 'update'], ['auth', 'csrf']);
$router->post('/panel/araclar/{id}/arsivle', [VehicleController::class, 'archive'], ['auth', 'csrf']);
$router->post('/panel/araclar/{vehicleId}/evrak', [DocumentController::class, 'store'], ['auth', 'csrf']);
$router->post('/panel/evrak/{id}', [DocumentController::class, 'update'], ['auth', 'csrf']);
$router->post('/panel/evrak/{id}/yenile', [DocumentController::class, 'renew'], ['auth', 'csrf']);
$router->post('/panel/evrak/{id}/sil', [DocumentController::class, 'destroy'], ['auth', 'csrf']);
$router->post('/panel/evrak/{id}/dosya/{fileId}/sil', [DocumentController::class, 'deleteFile'], ['auth', 'csrf']);

// ---- Takvim, rapor, .ics (Faz 2) ----
$router->get('/panel/takvim', [CalendarController::class, 'index'], ['auth']);
$router->get('/panel/araclar/{id}/rapor', [VehicleController::class, 'report'], ['auth']);
$router->get('/panel/evrak/{id}/takvim.ics', [DocumentController::class, 'ics'], ['auth']);

// ---- Filo / Ekip (Faz 2) ----
$router->get('/panel/ekip', [TeamController::class, 'index'], ['auth']);
$router->post('/panel/ekip/davet', [TeamController::class, 'invite'], ['auth', 'csrf']);
$router->post('/panel/ekip/{memberId}', [TeamController::class, 'update'], ['auth', 'csrf']);
$router->post('/panel/ekip/{memberId}/kaldir', [TeamController::class, 'remove'], ['auth', 'csrf']);
$router->get('/davet', [TeamController::class, 'accept']);
$router->post('/davet', [TeamController::class, 'acceptSubmit'], ['csrf']);

$router->fallback(static function (int $status): string {
    return View::render('errors/' . $status, ['title' => t('http.' . $status)], 'layouts/site');
});

$router->dispatch($request);
