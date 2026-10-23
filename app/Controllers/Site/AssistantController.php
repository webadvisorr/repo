<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Response;
use App\Core\Session;
use App\Services\SiteAssistant;

/**
 * Site asistanı uç noktası.
 * Konuşma geçmişi oturumda tutulur; API anahtarı istemciye hiç gönderilmez.
 */
final class AssistantController extends Controller
{
    private const SESSION_KEY = '_site_assistant_history';

    /** POST /asistan — JSON yanıt döner. */
    public function message(): never
    {
        if (!SiteAssistant::enabled()) {
            Response::json(['ok' => false, 'error' => __('Asistan şu anda kapalı.')], 404);
        }

        if (SiteAssistant::tooMany()) {
            Response::json([
                'ok'    => false,
                'error' => __('Çok fazla mesaj gönderdiniz. Lütfen birkaç dakika sonra tekrar deneyin.'),
            ], 429);
        }

        $text = trim((string) $this->request->input('message', ''));
        if ($text === '') {
            Response::json(['ok' => false, 'error' => __('Lütfen bir mesaj yazın.')], 422);
        }

        $history = Session::get(self::SESSION_KEY, []);
        if (!is_array($history)) {
            $history = [];
        }

        SiteAssistant::hit();
        $result = SiteAssistant::reply($text, $history);

        if (!$result['ok']) {
            Response::json(['ok' => false, 'error' => __((string) ($result['error'] ?? 'Yanıt alınamadı.'))], 502);
        }

        $history[] = ['role' => 'user', 'text' => $text];
        $history[] = ['role' => 'model', 'text' => (string) $result['reply']];
        $history = array_slice($history, -SiteAssistant::MAX_HISTORY * 2);
        Session::put(self::SESSION_KEY, $history);

        Audit::log('site_assistant.message', 'site', null, ['chars' => mb_strlen($text)]);

        Response::json([
            'ok'    => true,
            'reply' => (string) $result['reply'],
            'left'  => max(0, SiteAssistant::RATE_MAX - 1),
        ]);
    }

    /** POST /asistan/temizle — geçmişi sıfırlar. */
    public function clear(): never
    {
        Session::forget(self::SESSION_KEY);
        Response::json(['ok' => true]);
    }
}
