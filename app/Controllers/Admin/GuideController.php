<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Logger;
use App\Core\Response;
use App\Core\Translator;
use App\Services\AdminGuide;

/**
 * Yönetim paneli kullanım rehberi uç noktası.
 * Yalnızca oturum açmış yöneticiler + CSRF korumalı POST kabul eder.
 */
final class GuideController extends Controller
{
    public function ask(): never
    {
        if (!AdminGuide::enabled()) {
            Response::json([
                'ok'    => false,
                'error' => 'Panel rehberi yönetici tarafından kapatıldı.',
            ], 403);
        }

        $question = $this->request->string('question');
        $length = mb_strlen($question);

        if ($length < 2) {
            Response::json([
                'ok'    => false,
                'error' => 'Lütfen en az iki karakterlik bir soru yazın.',
            ], 422);
        }
        if ($length > AdminGuide::MAX_QUESTION) {
            Response::json([
                'ok'    => false,
                'error' => 'Soru en fazla ' . AdminGuide::MAX_QUESTION . ' karakter olabilir.',
            ], 422);
        }

        if (!AdminGuide::allowRequest()) {
            Logger::warning('Panel rehberi hiz siniri asildi', ['admin_id' => \App\Core\Auth::id()]);
            Response::json([
                'ok'    => false,
                'error' => 'Çok sık soru sordunuz. Lütfen birkaç dakika sonra tekrar deneyin.',
            ], 429);
        }

        $result = AdminGuide::ask($question, Translator::locale());
        Response::json($result);
    }
}
