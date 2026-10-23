<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Settings;

/**
 * Ziyaretçi ve müşterilerle ilgilenen yapay zekâ asistanı (Google Gemini).
 *
 * Rol: evrako'nun halkla ilişkiler / müşteri danışmanı. Siteyi tanıtır,
 * paketleri ve fiyatları anlatır, kullanım adımlarında yol gösterir, satış
 * öncesi soruları yanıtlar. Fiyat/indirim uydurmaz; kesin olmayan konularda
 * iletişim kanallarına yönlendirir. API anahtarı yalnızca sunucuda tutulur.
 */
final class SiteAssistant
{
    public const MAX_MESSAGE = 600;
    public const MAX_HISTORY = 8;
    public const RATE_WINDOW = 600;
    public const RATE_MAX = 15;

    public static function enabled(): bool
    {
        return (string) Settings::get('site_assistant.enabled', '0') === '1';
    }

    public static function configured(): bool
    {
        return self::enabled() && self::apiKey() !== '';
    }

    public static function apiKey(): string
    {
        return trim((string) Settings::get('site_assistant.api_key', ''));
    }

    public static function model(): string
    {
        $model = trim((string) Settings::get('site_assistant.model', 'gemini-3.6-flash'));
        $model = preg_replace('/[^a-zA-Z0-9._-]/', '', $model) ?? '';
        return $model !== '' ? $model : 'gemini-3.6-flash';
    }

    public static function greeting(): string
    {
        $greeting = trim((string) Settings::get('site_assistant.greeting', ''));
        return $greeting !== ''
            ? $greeting
            : 'Merhaba! Ben evrako’nun dijital danışmanıyım. Araç evraklarınızı nasıl takip edebileceğinizi, paketleri ve fiyatları anlatabilirim. Nasıl yardımcı olabilirim?';
    }

    public static function rateKey(): string
    {
        return 'site-assistant:' . session_id() . ':' . (new \App\Core\Request())->ip();
    }

    public static function tooMany(): bool
    {
        return RateLimiter::tooManyAttempts(self::rateKey(), self::RATE_MAX, self::RATE_WINDOW);
    }

    public static function hit(): void
    {
        RateLimiter::hit(self::rateKey(), self::RATE_WINDOW);
    }

    /**
     * Asistana gönderilecek sistem talimatı.
     * Site bilgileri ayarlardan okunur; böylece marka/fiyat değişince metin de güncel kalır.
     */
    public static function systemPrompt(): string
    {
        $brand = (string) Settings::get('brand.title', 'Evrako');
        $company = (string) Settings::get('brand.company', '');
        $phone = (string) Settings::get('brand.phone', '');
        $email = (string) Settings::get('brand.email', '');
        $base = VehiclePricing::basePrice();
        $currency = VehiclePricing::currency();
        $second = VehiclePricing::priceFor(2, $base);
        $third = VehiclePricing::priceFor(3, $base);
        $fourth = VehiclePricing::priceFor(4, $base);
        $custom = trim((string) Settings::get('site_assistant.persona', ''));

        $money = static fn (float $v): string => number_format($v, 2, ',', '.') . ' ' . $currency;

        return <<<PROMPT
Sen {$brand} adlı web sitesinin yapay zekâ destekli müşteri danışmanı ve halkla ilişkiler temsilcisisin.
Adın "{$brand} Asistan". Görevin: siteyi tanıtmak, ziyaretçilerin ve müşterilerin sorularını yanıtlamak,
onları doğru sayfaya ve doğru pakete yönlendirmek, satış öncesi güven oluşturmak.

SİTE HAKKINDA
- {$brand}, Kuzey Kıbrıs'ta araç sahiplerinin muayene, sigorta, kasko, seyrüsefer ve diğer araç evraklarının
  bitiş tarihlerini tek panelden takip etmesini sağlayan bir dijital evrak takip sistemidir.
- Süre dolmadan e-posta ile hatırlatma gönderir; trafik ışığı renkleriyle (yeşil/sarı/turuncu/kırmızı)
  hangi evrağın yaklaştığını veya geciktiğini gösterir.
- Araç, evrak ve hatırlatma yönetimi müşteri panelinden yapılır.

PAKETLER (yalnızca bu iki paket vardır, başka paket uydurma)
1) 6 Ay Ücretsiz Deneme: 0 TL, 6 ay, tek araç, tüm evrak türleri ve e-posta hatırlatmaları dahil.
2) Standart Paket: tek seferlik {$money($base)}. İlk araç bu fiyata dahildir.
   - 2. araç: %20 indirimli, {$money($second)}
   - 3. araç: %30 indirimli, {$money($third)}
   - 4. araç ve sonrası: %30 indirimli, {$money($fourth)} (her ek araç için)
- Araç ücretleri tek seferliktir ve kredi bakiyesinden düşülür.
- Muayene, sigorta, kasko, seyrüsefer ve diğer tüm evrakların eklenmesi, takibi ve hatırlatılması ÜCRETSİZDİR.
  Evrak başına veya evrak sayısına göre ASLA ek ücret yoktur. Bunu sorulduğunda açıkça söyle.

DAVRANIŞ KURALLARI
- Türkçe konuş; kullanıcı başka dilde yazarsa o dilde yanıtla.
- Sıcak, saygılı, kısa ve net ol. Gereksiz uzun listelerden kaçın; genellikle 2-5 cümle yeter.
- Fiyat, indirim, kampanya veya yasal süre uydurma. Bilmediğin veya emin olmadığın konuda
  "Bu konuda net bilgi için ekibimize ulaşabilirsiniz" de ve iletişim kanalını paylaş.
- Tıbbi, hukuki, siyasi veya siteyle ilgisiz konularda yorum yapma; kibarca site konusuna dön.
- Kullanıcıdan kimlik numarası, kart bilgisi, şifre veya hassas kişisel veri isteme; bu bilgileri asla tekrar etme.
- Kayıt için /kayit, fiyatlar için /fiyatlar, iletişim için /iletisim sayfasını öner.
- Gerektiğinde insan desteğine yönlendir: {$phone} {$email}
- Kendini yapay zekâ olduğunu gizlemeden, ama robot gibi de konuşmadan temsil et.
- Cevaplarında Markdown başlık ve tablo kullanma; düz metin yaz.
PROMPT
            . ($custom !== '' ? "\n\nEK TALİMATLAR (işletme tarafından):\n" . $custom : '');
    }

    /**
     * @param array<int,array{role:string,text:string}> $history
     * @return array{ok:bool,reply?:string,error?:string}
     */
    public static function reply(string $message, array $history = []): array
    {
        $message = trim($message);
        if ($message === '') {
            return ['ok' => false, 'error' => 'Lütfen bir mesaj yazın.'];
        }
        if (mb_strlen($message) > self::MAX_MESSAGE) {
            $message = mb_substr($message, 0, self::MAX_MESSAGE);
        }
        if (!self::configured()) {
            return ['ok' => false, 'error' => __('Asistan şu anda çevrimdışı. Bize iletişim sayfasından ulaşabilirsiniz.')];
        }

        $contents = [];
        foreach (array_slice($history, -self::MAX_HISTORY) as $turn) {
            $role = ($turn['role'] ?? '') === 'model' ? 'model' : 'user';
            $text = trim((string) ($turn['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $contents[] = ['role' => $role, 'parts' => [['text' => mb_substr($text, 0, 2000)]]];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

        return self::generate(self::systemPrompt(), $contents, 600);
    }

    /**
     * Gemini'ye sistem talimatı + içerik gönderir.
     * Panel rehberi de aynı API anahtarı ve modeli kullanır.
     *
     * @param array<int,array{role:string,parts:array}> $contents
     * @return array{ok:bool,reply?:string,error?:string}
     */
    public static function generate(string $system, array $contents, int $maxTokens = 600): array
    {
        if (self::apiKey() === '') {
            return ['ok' => false, 'error' => 'Gemini API anahtarı tanımlı değil.'];
        }

        $payload = [
            'system_instruction' => ['parts' => [['text' => $system]]],
            'contents'           => $contents,
            'generationConfig'   => [
                'temperature'      => 0.6,
                'topP'             => 0.9,
                'maxOutputTokens'  => max(120, min(1200, $maxTokens)),
                // Sohbet yanıtları için düşünme bütçesi kapalı: hem 1-2 sn'de yanıt döner
                // hem de token bütçesi düşünmeye harcanıp yanıt cümle ortasında kesilmez.
                'thinkingConfig'   => ['thinkingBudget' => 0],
            ],
            'safetySettings'     => [
                ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ],
        ];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode(self::model()) . ':generateContent';

        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'Asistan bağlantısı kurulamadı.'];
        }
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . self::apiKey(),
            ],
            CURLOPT_POSTFIELDS     => (string) json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $status === 0) {
            Logger::warning('Site asistanı bağlantı hatası', ['error' => $curlError]);
            return ['ok' => false, 'error' => __('Şu anda yanıt veremiyorum. Lütfen biraz sonra tekrar deneyin.')];
        }

        $data = json_decode((string) $raw, true);
        if ($status !== 200 || !is_array($data)) {
            $apiMessage = is_array($data) ? (string) ($data['error']['message'] ?? '') : '';
            Logger::warning('Site asistanı API hatası', ['status' => $status, 'message' => $apiMessage]);
            return [
                'ok'    => false,
                'error' => $status === 429
                    ? __('Şu anda çok yoğunum, birkaç saniye sonra tekrar deneyin.')
                    : __('Şu anda yanıt veremiyorum. Lütfen biraz sonra tekrar deneyin.'),
            ];
        }

        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        $text = '';
        foreach ((array) $parts as $part) {
            if (isset($part['text'])) {
                $text .= (string) $part['text'];
            }
        }
        $text = trim($text);
        if ($text === '') {
            $blocked = (string) ($data['promptFeedback']['blockReason'] ?? '');
            Logger::warning('Site asistanı boş yanıt', ['block' => $blocked]);
            return ['ok' => false, 'error' => 'Bu soruya yanıt veremedim. Farklı bir şekilde sorabilir misiniz?'];
        }

        return ['ok' => true, 'reply' => mb_substr($text, 0, 4000)];
    }
}
