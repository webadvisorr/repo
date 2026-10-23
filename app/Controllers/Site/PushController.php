<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Logger;
use App\Core\Response;
use App\Services\PushService;

/**
 * Cihaz bildirimleri (Web Push) uç noktaları.
 *
 * - /sw.js : service worker (kök kapsam, tarayıcı bildirimi için zorunlu)
 * - /panel/bildirimler/cihaz : abonelik durumu / kayıt / bırakma
 */
final class PushController extends Controller
{
    /** Service worker dosyasını kök adresten servis eder. */
    public function serviceWorker(): void
    {
        $path = BASE_PATH . '/assets/js/sw.js';
        $body = is_file($path) ? (string) file_get_contents($path) : '';

        if (!headers_sent()) {
            header('Content-Type: application/javascript; charset=utf-8');
            header('Service-Worker-Allowed: /');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('X-Content-Type-Options: nosniff');
            header('X-Robots-Tag: noindex');
        }
        echo $body;
    }

    public function status(): never
    {
        $userId = (int) (Auth::id() ?? 0);
        Response::json([
            'ok'        => true,
            'enabled'   => PushService::enabled(),
            'supported' => PushService::publicKey() !== null,
            'publicKey' => PushService::publicKey(),
            'devices'   => PushService::deviceCount($userId),
        ]);
    }

    /** Tarayıcı aboneliğini kaydeder. */
    public function subscribe(): void
    {
        $userId = (int) (Auth::id() ?? 0);
        $result = PushService::subscribe(
            $userId,
            (string) $this->request->input('endpoint', ''),
            (string) $this->request->input('p256dh', ''),
            (string) $this->request->input('auth', ''),
            (string) $this->request->input('user_agent', '')
        );

        if (!$result['ok']) {
            if ($this->request->wantsJson()) {
                Response::json(['ok' => false, 'error' => (string) ($result['error'] ?? 'Cihaz kaydedilemedi.')], 422);
            }
            $this->flashError((string) ($result['error'] ?? 'Cihaz kaydedilemedi.'));
            $this->redirect('/panel/bildirimler');
        }

        Logger::info('Web Push cihazı eklendi', ['user' => $userId, 'devices' => $result['devices'] ?? 0]);

        if ($this->request->wantsJson()) {
            Response::json(['ok' => true, 'devices' => (int) ($result['devices'] ?? 0)]);
        }
        $this->flashSuccess('Bu cihazda bildirimler açıldı.');
        $this->redirect('/panel/bildirimler');
    }

    public function unsubscribe(): void
    {
        $userId = (int) (Auth::id() ?? 0);
        $endpoint = (string) $this->request->input('endpoint', '');
        PushService::unsubscribe($userId, $endpoint);

        if ($this->request->wantsJson()) {
            Response::json([
                'ok'      => true,
                'devices' => PushService::deviceCount($userId),
                'message' => 'Bu cihazda bildirimler kapatıldı.',
            ]);
        }
        $this->flashSuccess('Bu cihazda bildirimler kapatıldı.');
        $this->redirect('/panel/bildirimler');
    }
}
