<?php
declare(strict_types=1);

/**
 * Geliştirici aracı: site arayüzü metinlerini Gemini ile hedef dile çevirir.
 * Kullanım: php .freebuff/i18n_translate.php <locale> [batch_size]
 * Çıktı: lang/ui/<locale>.php  (Türkçe metin => çeviri)
 */
require __DIR__ . '/../app/bootstrap.php';

use App\Core\Settings;

$locale = $argv[1] ?? '';
$batchSize = max(5, min(40, (int) ($argv[2] ?? 25)));
$names = ['en' => 'English', 'ru' => 'Russian', 'ar' => 'Arabic', 'fa' => 'Persian', 'el' => 'Greek'];
if (!isset($names[$locale])) {
    fwrite(STDERR, "Desteklenen diller: " . implode(', ', array_keys($names)) . "\n");
    exit(1);
}
$phrases = json_decode((string) file_get_contents(__DIR__ . '/i18n-phrases.json'), true);
if (!is_array($phrases) || $phrases === []) {
    fwrite(STDERR, "i18n-phrases.json okunamadı.\n");
    exit(1);
}
$key = (string) Settings::get('site_assistant.api_key', '');
$model = (string) (getenv('I18N_MODEL') ?: Settings::get('site_assistant.model', 'gemini-3.6-flash'));
if ($key === '') {
    fwrite(STDERR, "Gemini API anahtarı ayarlarda yok.\n");
    exit(1);
}

$outFile = __DIR__ . '/../lang/ui/' . $locale . '.php';
@mkdir(dirname($outFile), 0775, true);
$map = [];
if (is_file($outFile)) {
    $map = (array) require $outFile;
}

function write_map(string $file, array $map): void
{
    ksort($map, SORT_STRING);
    $php = "<?php\ndeclare(strict_types=1);\n\n/** Otomatik çeviri: .freebuff/i18n_translate.php (Gemini). Elle düzenlenebilir. */\nreturn [\n";
    foreach ($map as $source => $target) {
        $php .= '    ' . var_export((string) $source, true) . ' => ' . var_export((string) $target, true) . ",\n";
    }
    $php .= "];\n";
    file_put_contents($file, $php);
}

function call_gemini(string $key, string $model, string $text): ?string
{
    $body = [
        'contents' => [['role' => 'user', 'parts' => [['text' => $text]]]],
        'generationConfig' => ['temperature' => 0.15, 'responseMimeType' => 'application/json', 'maxOutputTokens' => 16384],
    ];
    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . $key);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => (string) json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 90,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || !is_string($raw)) {
        return null;
    }
    $json = json_decode($raw, true);
    $textOut = (string) ($json['candidates'][0]['content']['parts'][0]['text'] ?? '');
    if ($textOut === '') {
        return null;
    }
    $textOut = preg_replace('/^```(?:json)?|```$/m', '', trim($textOut)) ?? $textOut;
    return $textOut;
}

$missing = array_values(array_filter($phrases, static fn (string $p): bool => !array_key_exists($p, $map)));
echo "[$locale] toplam " . count($phrases) . ', eksik ' . count($missing) . " (batch {$batchSize})\n";

$instruction = 'You are translating user-interface strings of a Turkish vehicle document tracking SaaS (brand: Evrako) into ' . $names[$locale] . '. '
    . 'Translate naturally and consistently, as a professional website localization. '
    . 'Preserve placeholders such as :count, {name}, __COUNT__, emojis, arrows, punctuation and line breaks exactly. '
    . 'Keep the brand name Evrako and legal/contract meaning. Do not shorten legal texts. '
    . "Return ONLY a JSON object whose keys are the exact original Turkish strings and whose values are the translations.\n";

$total = count($missing);
for ($i = 0; $i < $total; $i += $batchSize) {
    $batch = array_slice($missing, $i, $batchSize);
    $prompt = $instruction . json_encode($batch, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $done = false;
    for ($attempt = 1; $attempt <= 4 && !$done; $attempt++) {
        $textOut = call_gemini($key, $model, $prompt);
        if ($textOut === null) {
            sleep(2 * $attempt);
            continue;
        }
        $decoded = json_decode($textOut, true);
        if (!is_array($decoded)) {
            sleep(2 * $attempt);
            continue;
        }
        $added = 0;
        foreach ($batch as $source) {
            if (isset($decoded[$source]) && is_string($decoded[$source]) && trim($decoded[$source]) !== '') {
                $map[$source] = $decoded[$source];
                $added++;
            }
        }
        write_map($outFile, $map);
        echo sprintf("  batch %d/%d → %d çeviri\n", (int) ($i / $batchSize) + 1, (int) ceil($total / $batchSize), $added);
        $done = $added > 0;
        if (!$done) {
            sleep(2 * $attempt);
        }
    }
    if (!$done) {
        echo "  ! batch atlandı: " . substr($batch[0] ?? '', 0, 60) . "\n";
    }
    usleep(5000000);
}

write_map($outFile, $map);
echo "[$locale] bitti: " . count($map) . " çeviri → lang/ui/$locale.php\n";
