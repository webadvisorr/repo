<?php
declare(strict_types=1);

/**
 * e2e yardımcısı: panel rehberi ayarlarını yedekle / geri yükle.
 * Kullanım:
 *   guide-settings.php export /tmp/assistant.json
 *   guide-settings.php restore /tmp/assistant.json
 */
require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;

$cmd = (string) ($argv[1] ?? '');
$file = (string) ($argv[2] ?? '');

if ($file === '') {
    fwrite(STDERR, "dosya yolu gerekli\n");
    exit(1);
}

if ($cmd === 'export') {
    $rows = Database::select("SELECT `group`, `key`, `value`, is_json, updated_by, updated_at FROM settings WHERE `group` = 'assistant' OR `key` = 'site_assistant.api_key'");
    file_put_contents($file, json_encode($rows, JSON_UNESCAPED_UNICODE));
    exit(0);
}

if ($cmd === 'restore') {
    $rows = json_decode((string) @file_get_contents($file), true);
    if (!is_array($rows)) {
        fwrite(STDERR, "yedek dosyası okunamadı\n");
        exit(1);
    }
    Database::run("DELETE FROM settings WHERE `group` = 'assistant' OR `key` = 'site_assistant.api_key'");
    foreach ($rows as $row) {
        Database::insert('settings', [
            'group'      => (string) $row['group'],
            'key'        => (string) $row['key'],
            'value'      => (string) $row['value'],
            'is_json'    => (int) $row['is_json'],
            'updated_by' => $row['updated_by'] !== null ? (int) $row['updated_by'] : null,
            'updated_at' => (string) $row['updated_at'],
        ]);
    }
    exit(0);
}

if ($cmd === 'clear-gemini') {
    Database::run("UPDATE settings SET value = '' WHERE `key` = 'site_assistant.api_key'");
    exit(0);
}

if ($cmd === 'restore-gemini') {
    $rows = json_decode((string) @file_get_contents($file), true);
    foreach (is_array($rows) ? $rows : [] as $row) {
        if ((string) $row['key'] !== 'site_assistant.api_key') {
            continue;
        }
        Database::run("UPDATE settings SET value = ? WHERE `key` = 'site_assistant.api_key'", [(string) $row['value']]);
    }
    exit(0);
}

fwrite(STDERR, "bilinmeyen komut: {$cmd}\n");
exit(1);
