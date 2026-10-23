<?php
declare(strict_types=1);
$locale = (string) ($argv[1] ?? '');
$jsonFile = (string) ($argv[2] ?? '');
if (!preg_match('/^[a-z]{2}$/', $locale) || !is_file($jsonFile)) {
    fwrite(STDERR, "usage: i18n_apply_map.php <locale> <json>\n");
    exit(1);
}
$incoming = json_decode((string) file_get_contents($jsonFile), true);
if (!is_array($incoming)) {
    fwrite(STDERR, "invalid json\n");
    exit(1);
}
$file = __DIR__ . '/../lang/ui/' . $locale . '.php';
@mkdir(dirname($file), 0775, true);
$map = is_file($file) ? (array) require $file : [];
foreach ($incoming as $source => $target) {
    if (is_string($source) && is_string($target) && trim($target) !== '') {
        $map[$source] = $target;
    }
}
ksort($map, SORT_STRING);
$php = "<?php\ndeclare(strict_types=1);\n\n/** Otomatik çeviri: .freebuff/i18n_translate.php / i18n_google.py. Elle düzenlenebilir. */\nreturn [\n";
foreach ($map as $source => $target) {
    $php .= '    ' . var_export((string) $source, true) . ' => ' . var_export((string) $target, true) . ",\n";
}
$php .= "];\n";
file_put_contents($file, $php);
echo $locale . ': ' . count($map) . " çeviri\n";
