<?php
declare(strict_types=1);

/**
 * Eksik anahtarlari lang/<locale>.php icindeki 'settings' bloguna ekler.
 * stdin: {"settings.kolay_adi": "ceviri", ...}
 */
$locale = (string) ($argv[1] ?? '');
if (!preg_match('/^[a-z]{2}$/', $locale)) {
    fwrite(STDERR, "usage: i18n_insert_keys.php <locale>\n");
    exit(1);
}
$incoming = json_decode((string) stream_get_contents(STDIN), true);
if (!is_array($incoming) || $incoming === []) {
    fwrite(STDERR, "no data\n");
    exit(1);
}
$file = __DIR__ . '/../lang/' . $locale . '.php';
$map = (array) require $file;

$lines = [];
foreach ($incoming as $key => $value) {
    $segments = explode('.', (string) $key);
    if (count($segments) !== 2 || $segments[0] !== 'settings') {
        continue;
    }
    if (isset($map['settings'][$segments[1]])) {
        continue;
    }
    $map['settings'][$segments[1]] = (string) $value;
    $lines[] = '        ' . var_export($segments[1], true) . ' => ' . var_export((string) $value, true) . ',';
}
if ($lines === []) {
    echo "$locale: eklenecek yeni anahtar yok\n";
    exit(0);
}

$source = (string) file_get_contents($file);
$needle = "    'settings' => [\n";
$pos = strpos($source, $needle);
if ($pos === false) {
    fwrite(STDERR, "settings blogu bulunamadi: $file\n");
    exit(1);
}
$insertAt = $pos + strlen($needle);
$source = substr($source, 0, $insertAt) . implode("\n", $lines) . "\n" . substr($source, $insertAt);
file_put_contents($file, $source);

$check = (array) require $file;
echo $locale . ': ' . count($lines) . ' anahtar eklendi (toplam settings: ' . count($check['settings'] ?? []) . ")\n";
