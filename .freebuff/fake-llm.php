<?php
declare(strict_types=1);

/**
 * e2e yardımcısı: OpenAI uyumlu sahte sohbet uç noktası.
 * Gelen isteği (sistem rolü dahil) diske yazar ve sabit bir yanıt döndürür.
 * Kullanım: FAKE_LLM_LOG=/tmp/fake.json php -S 127.0.0.1:3101 fake-llm.php
 */
$raw = (string) file_get_contents('php://input');
$log = (string) (getenv('FAKE_LLM_LOG') ?: '/tmp/fake_llm_request.json');
@file_put_contents($log, $raw);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'id'      => 'fake-1',
    'object'  => 'chat.completion',
    'choices' => [[
        'index'         => 0,
        'message'       => [
            'role'    => 'assistant',
            'content' => "Sahte AI yanıtı: kredi yüklemek için Üyeler > üye detayı > Kredi yönetimi bölümünü kullanın.\nSayfa: /yonetim/uyeler",
        ],
        'finish_reason' => 'stop',
    ]],
], JSON_UNESCAPED_UNICODE);
