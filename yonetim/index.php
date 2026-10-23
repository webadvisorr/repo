<?php
declare(strict_types=1);

/**
 * evrako — yönetim paneli ön kontrolcüsü.
 * Adres: /yonetim/...
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\Admin\AnnouncementsController;
use App\Controllers\Admin\AuditController;
use App\Controllers\Admin\AuthController as AdminAuth;
use App\Controllers\Admin\CampaignsController;
use App\Controllers\Admin\DocumentsController;
use App\Controllers\Admin\MessagesController;
use App\Controllers\Admin\SlidesController;
use App\Controllers\Admin\VehiclesController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\GuideController;
use App\Controllers\Admin\MailTemplatesController;
use App\Controllers\Admin\MembersController;
use App\Controllers\Admin\NotificationsController;
use App\Controllers\Admin\PaymentsController;
use App\Controllers\Admin\ProfileRequestsController;
use App\Controllers\Admin\PlansController;
use App\Controllers\Admin\SearchController;
use App\Controllers\Admin\SettingsController;
use App\Controllers\Admin\SupportController;
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

// Dil secimi (?dil=xx): secim cereze yazilir, boylece panelde de kalici olur.
$localeParam = (string) $request->input('dil', '');
if ($localeParam !== '' && in_array($localeParam, Translator::SUPPORTED, true)) {
    setcookie('evrako_locale', $localeParam, [
        'expires' => time() + 31536000, 'path' => '/',
        'secure' => $request->isSecure(), 'httponly' => true, 'samesite' => 'Lax',
    ]);
    Translator::setLocale($localeParam);
} else {
    Translator::setLocale(Translator::detect());
}

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
$router->post('/yonetim/panel/kutucuklar', [DashboardController::class, 'saveWidgets'], ['admin', 'csrf']);

// ---- Panel kullanım rehberi (yalnızca yönetim paneli kapsamı) ----
$router->post('/yonetim/rehber/sor', [GuideController::class, 'ask'], ['admin', 'csrf']);

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

// ---- Profil güncelleme talepleri (müşteri → yönetici onayı) ----
$router->get('/yonetim/profil-talepleri', [ProfileRequestsController::class, 'index'], ['admin']);
$router->post('/yonetim/profil-talepleri/onayla', [ProfileRequestsController::class, 'approve'], ['admin', 'csrf']);
$router->post('/yonetim/profil-talepleri/reddet', [ProfileRequestsController::class, 'reject'], ['admin', 'csrf']);
$router->post('/yonetim/uyeler/sifre-baglantisi', [MembersController::class, 'sendResetLink'], ['admin', 'csrf']);

// ---- Araçlar / evraklar (yönetici gözetimi) ----
$router->get('/yonetim/araclar', [VehiclesController::class, 'index'], ['admin']);
$router->post('/yonetim/araclar/durum', [VehiclesController::class, 'updateStatus'], ['admin', 'csrf']);
$router->get('/yonetim/araclar/{id}', [VehiclesController::class, 'show'], ['admin']);

$router->get('/yonetim/evraklar', [DocumentsController::class, 'index'], ['admin']);
$router->get('/yonetim/evraklar/{id}', [DocumentsController::class, 'show'], ['admin']);

// ---- Planlar ----
$router->get('/yonetim/planlar', [PlansController::class, 'index'], ['admin']);
$router->post('/yonetim/planlar/kaydet', [PlansController::class, 'update'], ['admin', 'csrf']);
$router->post('/yonetim/planlar/ekle', [PlansController::class, 'store'], ['admin', 'csrf']);
$router->post('/yonetim/planlar/sil', [PlansController::class, 'destroy'], ['admin', 'csrf']);

// ---- Ödemeler ----
$router->get('/yonetim/odemeler', [PaymentsController::class, 'index'], ['admin']);
$router->post('/yonetim/odemeler/kaydet', [PaymentsController::class, 'store'], ['admin', 'csrf']);
$router->post('/yonetim/odemeler/durum', [PaymentsController::class, 'updateStatus'], ['admin', 'csrf']);
$router->post('/yonetim/odemeler/havale-onayla', [PaymentsController::class, 'approveDeclared'], ['admin', 'csrf']);
$router->post('/yonetim/odemeler/havale-reddet', [PaymentsController::class, 'rejectDeclared'], ['admin', 'csrf']);

// ---- İletişim mesajları ----
$router->get('/yonetim/mesajlar', [MessagesController::class, 'index'], ['admin']);
$router->post('/yonetim/mesajlar/durum', [MessagesController::class, 'setRead'], ['admin', 'csrf']);
$router->post('/yonetim/mesajlar/tumu-okundu', [MessagesController::class, 'markAllRead'], ['admin', 'csrf']);
$router->post('/yonetim/mesajlar/sil', [MessagesController::class, 'destroy'], ['admin', 'csrf']);
$router->post('/yonetim/mesajlar/yanit', [MessagesController::class, 'reply'], ['admin', 'csrf']);
$router->get('/yonetim/mesajlar/{id}', [MessagesController::class, 'show'], ['admin']);

// ---- Slider yönetimi ----
$router->get('/yonetim/slider', [SlidesController::class, 'index'], ['admin']);
$router->get('/yonetim/slider/yeni', [SlidesController::class, 'create'], ['admin']);
$router->post('/yonetim/slider/kaydet', [SlidesController::class, 'save'], ['admin', 'csrf']);
$router->post('/yonetim/slider/durum', [SlidesController::class, 'toggle'], ['admin', 'csrf']);
$router->post('/yonetim/slider/sil', [SlidesController::class, 'destroy'], ['admin', 'csrf']);
$router->get('/yonetim/slider/{id}', [SlidesController::class, 'edit'], ['admin']);

// ---- Mail şablonları (Faz 3) ----
$router->get('/yonetim/sablonlar', [MailTemplatesController::class, 'index'], ['admin']);
$router->get('/yonetim/sablonlar/{key}/{locale}', [MailTemplatesController::class, 'edit'], ['admin']);
$router->post('/yonetim/sablonlar/kaydet', [MailTemplatesController::class, 'save'], ['admin', 'csrf']);
$router->post('/yonetim/sablonlar/sifirla', [MailTemplatesController::class, 'reset'], ['admin', 'csrf']);
$router->post('/yonetim/sablonlar/onizleme', [MailTemplatesController::class, 'preview'], ['admin', 'csrf']);
$router->post('/yonetim/sablonlar/test', [MailTemplatesController::class, 'testSend'], ['admin', 'csrf']);

// ---- Kampanya / reklam maili (Faz 4) ----
$router->get('/yonetim/kampanya', [CampaignsController::class, 'index'], ['admin']);
$router->get('/yonetim/kampanya/yeni', [CampaignsController::class, 'create'], ['admin']);
$router->get('/yonetim/kampanya/{id}/duzenle', [CampaignsController::class, 'edit'], ['admin']);
$router->get('/yonetim/kampanya/{id}', [CampaignsController::class, 'show'], ['admin']);
$router->post('/yonetim/kampanya/kaydet', [CampaignsController::class, 'save'], ['admin', 'csrf']);
$router->post('/yonetim/kampanya/kuyruk', [CampaignsController::class, 'queueOnly'], ['admin', 'csrf']);
$router->post('/yonetim/kampanya/gonder', [CampaignsController::class, 'send'], ['admin', 'csrf']);
$router->post('/yonetim/kampanya/test', [CampaignsController::class, 'test'], ['admin', 'csrf']);
$router->post('/yonetim/kampanya/onizleme', [CampaignsController::class, 'preview'], ['admin', 'csrf']);
$router->post('/yonetim/kampanya/iptal', [CampaignsController::class, 'cancel'], ['admin', 'csrf']);
$router->post('/yonetim/kampanya/sil', [CampaignsController::class, 'destroy'], ['admin', 'csrf']);

// ---- Site içi duyurular (özel bildirim: resim + metin + link) ----
$router->get('/yonetim/duyurular', [AnnouncementsController::class, 'index'], ['admin']);
$router->get('/yonetim/duyurular/yeni', [AnnouncementsController::class, 'create'], ['admin']);
$router->post('/yonetim/duyurular', [AnnouncementsController::class, 'store'], ['admin', 'csrf']);
$router->post('/yonetim/duyurular/sil', [AnnouncementsController::class, 'destroy'], ['admin', 'csrf']);
$router->post('/yonetim/duyurular/push-gonder', [AnnouncementsController::class, 'sendPushQueue'], ['admin', 'csrf']);
$router->get('/yonetim/duyurular/{id}', [AnnouncementsController::class, 'show'], ['admin']);

// ---- Bildirim günlüğü (Faz 3) ----
$router->get('/yonetim/bildirimler', [NotificationsController::class, 'index'], ['admin']);
$router->post('/yonetim/bildirimler/yeniden-dene', [NotificationsController::class, 'retry'], ['admin', 'csrf']);
$router->post('/yonetim/bildirimler/basarisizlari-dene', [NotificationsController::class, 'retryFailed'], ['admin', 'csrf']);
$router->post('/yonetim/bildirimler/iptal', [NotificationsController::class, 'cancel'], ['admin', 'csrf']);
$router->post('/yonetim/bildirimler/kuyrugu-isle', [NotificationsController::class, 'runNow'], ['admin', 'csrf']);
$router->post('/yonetim/bildirimler/tara', [NotificationsController::class, 'runFull'], ['admin', 'csrf']);
$router->get('/yonetim/bildirimler/{id}', [NotificationsController::class, 'show'], ['admin']);

// ---- Destek talepleri ----
$router->get('/yonetim/destek', [SupportController::class, 'index'], ['admin']);
$router->post('/yonetim/destek/yanit', [SupportController::class, 'reply'], ['admin', 'csrf']);
$router->post('/yonetim/destek/durum', [SupportController::class, 'status'], ['admin', 'csrf']);
$router->get('/yonetim/destek/{id}', [SupportController::class, 'show'], ['admin']);

// ---- Hızlı müşteri arama ----
$router->get('/yonetim/arama', [SearchController::class, 'index'], ['admin']);
$router->get('/yonetim/canli', [DashboardController::class, 'presence'], ['admin']);

// ---- Denetim kaydı ----
$router->get('/yonetim/denetim', [AuditController::class, 'index'], ['admin']);

// ---- Sistem ----
$router->get('/yonetim/sistem', [SystemController::class, 'index'], ['admin']);
$router->post('/yonetim/sistem/migrate', [SystemController::class, 'migrate'], ['admin', 'csrf']);
$router->post('/yonetim/sistem/cron', [SystemController::class, 'runCron'], ['admin', 'csrf']);

$router->fallback(static function (int $status): string {
    return View::render('errors/' . $status, ['title' => t('http.' . $status)], 'layouts/admin');
});

$router->dispatch($request);
