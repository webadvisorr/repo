<?php
declare(strict_types=1);

/**
 * evrako — Web kurulum sihirbazi (SSH/Composer gerekmez).
 * Kurulum sonrasi bu dosyayi sunucudan silin.
 */
define('BASE_PATH', __DIR__);
require BASE_PATH . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\Autoloader;
use App\Core\Database;
use App\Core\Security;
use App\Core\Session;
use App\Core\Settings;
use App\Core\Migrator;
use App\Core\Str;

error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('Europe/Nicosia');
session_start();

$configFile = BASE_PATH . '/config/config.php';
$installed = is_file($configFile) && trim((string) (require $configFile)['app']['key'] ?? '') !== '';
$lockFile = BASE_PATH . '/storage/installed.lock';
$isLocked = is_file($lockFile);

require BASE_PATH . '/app/Core/helpers.php';

$saved = $_SESSION['install'] ?? [];
$errors = [];
$done = false;
$createdTables = 0;
$log = [];

/** Gereksinim kontrolu */
function requirements(): array
{
    $paths = [
        'config'  => BASE_PATH . '/config',
        'storage' => BASE_PATH . '/storage',
        'storage/logs' => BASE_PATH . '/storage/logs',
        'storage/uploads' => BASE_PATH . '/storage/uploads',
        'storage/cache' => BASE_PATH . '/storage/cache',
    ];
    $items = [
        ['PHP >= 8.0', PHP_VERSION_ID >= 80000, 'PHP ' . PHP_VERSION],
        ['pdo_mysql eklentisi', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? 'yüklü' : 'eksik'],
        ['mbstring eklentisi', extension_loaded('mbstring'), extension_loaded('mbstring') ? 'yüklü' : 'eksik'],
        ['openssl eklentisi', extension_loaded('openssl'), extension_loaded('openssl') ? 'yüklü' : 'eksik'],
        ['fileinfo eklentisi', extension_loaded('fileinfo'), extension_loaded('fileinfo') ? 'yüklü' : 'eksik'],
        ['json eklentisi', extension_loaded('json'), extension_loaded('json') ? 'yüklü' : 'eksik'],
        ['GD (görsel işleme)', extension_loaded('gd'), extension_loaded('gd') ? 'yüklü' : 'önerilir'],
    ];
    foreach ($paths as $label => $path) {
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
        $items[] = [$label . '/ yazılabilir', is_writable($path), is_writable($path) ? 'yazılabilir' : 'yazılamaz'];
    }
    return $items;
}

$reqs = requirements();
$reqsOk = true;
foreach ($reqs as [$label, $pass, $note]) {
    if (!$pass && !str_contains($label, 'GD')) {
        $reqsOk = false;
    }
}

// ---------------------------------------------------------------- POST islemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLocked) {
    $post = $_POST;
    $saved = [
        'db_host' => trim((string) ($post['db_host'] ?? 'localhost')),
        'db_port' => (int) ($post['db_port'] ?? 3306),
        'db_name' => trim((string) ($post['db_name'] ?? '')),
        'db_user' => trim((string) ($post['db_user'] ?? '')),
        'db_pass' => (string) ($post['db_pass'] ?? ''),
        'app_url' => rtrim(trim((string) ($post['app_url'] ?? '')), '/'),
        'app_name' => trim((string) ($post['app_name'] ?? 'evrako')),
        'slogan' => trim((string) ($post['slogan'] ?? '')),
        'admin_name' => trim((string) ($post['admin_name'] ?? '')),
        'admin_email' => trim((string) ($post['admin_email'] ?? '')),
        'admin_pass' => (string) ($post['admin_pass'] ?? ''),
    ];
    $_SESSION['install'] = $saved;

    if (!$reqsOk) {
        $errors[] = 'Sunucu gereksinimleri karşılanmıyor.';
    }
    if ($saved['db_name'] === '' || $saved['db_user'] === '') {
        $errors[] = 'Veritabanı adı ve kullanıcı adı zorunludur.';
    }
    if ($saved['app_url'] === '' || filter_var($saved['app_url'], FILTER_VALIDATE_URL) === false) {
        $errors[] = 'Geçerli bir site adresi girin (örn. https://evrako.com).';
    }
    if (mb_strlen($saved['admin_name']) < 3) {
        $errors[] = 'Yönetici adı en az 3 karakter olmalıdır.';
    }
    if (filter_var($saved['admin_email'], FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = 'Geçerli bir yönetici e-postası girin.';
    }
    if (mb_strlen($saved['admin_pass']) < 8) {
        $errors[] = 'Yönetici şifresi en az 8 karakter olmalıdır.';
    }

    // Veritabani baglantisi testi
    $pdo = null;
    if ($errors === []) {
        try {
            $dsn = "mysql:host={$saved['db_host']};port={$saved['db_port']};dbname={$saved['db_name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $saved['db_user'], $saved['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $log[] = 'Veritabanı bağlantısı başarılı.';
        } catch (PDOException $e) {
            $errors[] = 'Veritabanı bağlantısı kurulamadı: ' . $e->getMessage();
        }
    }

    // config/config.php yaz
    if ($errors === [] && $pdo instanceof PDO) {
        $template = require BASE_PATH . '/config/config.example.php';
        $template['app']['name'] = $saved['app_name'];
        $template['app']['url'] = $saved['app_url'];
        $template['app']['key'] = Str::random(32);
        $template['app']['installed_at'] = date('c');
        $template['db']['host'] = $saved['db_host'];
        $template['db']['port'] = $saved['db_port'];
        $template['db']['database'] = $saved['db_name'];
        $template['db']['username'] = $saved['db_user'];
        $template['db']['password'] = $saved['db_pass'];
        $template['security']['cron_token'] = Str::random(16);

        $export = "<?php\n"
            . "declare(strict_types=1);\n\n"
            . "// Bu dosya kurulum sihirbazi tarafindan uretildi: " . date('c') . "\n"
            . "// Guvenlik: bu dosyayi kimseyle paylasmayin, yedegini guvenli tutun.\n\n"
            . 'return ' . var_export($template, true) . ";\n";

        if (@file_put_contents($configFile, $export) === false) {
            $errors[] = 'config/config.php yazılamadı. config/ klasörüne yazma izni verin.';
        } else {
            @chmod($configFile, 0644);
            $log[] = 'config/config.php oluşturuldu.';
        }
    }

    // Tablolari kur ve varsayilan verileri yaz
    if ($errors === [] && is_file($configFile)) {
        try {
            \App\Core\Config::load(require $configFile);
            $sql = (string) file_get_contents(BASE_PATH . '/app/Database/schema.sql');
            // Yorumlari ve bos satirlari at, ifadeleri ; ile ayir
            $statements = array_filter(array_map('trim', explode(";\n", $sql)));
            $executed = 0;
            foreach ($statements as $statement) {
                $clean = preg_replace('/^\s*--.*$/m', '', $statement) ?? $statement;
                $clean = trim($clean);
                if ($clean === '' || $clean === ';') {
                    continue;
                }
                $pdo->exec($clean);
                $executed++;
            }
            $createdTables = (int) $pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = " . $pdo->quote($saved['db_name'])
            )->fetchColumn();
            $log[] = "Şema uygulandı ({$createdTables} tablo).";

            // Yeni kurulumda şema zaten güncel: migration'ları uygulanmış olarak işaretle.
            App\Core\Migrator::markAllApplied();
            $log[] = 'Migration kayıtları hazırlandı.';

            Settings::seedDefaults();
            $log[] = 'Varsayılan site ayarları yazıldı.';

            if (is_file(BASE_PATH . '/app/Database/seed.php')) {
                require BASE_PATH . '/app/Database/seed.php';
                Seeder::run();
                $log[] = 'Araç türleri, evrak türleri ve planlar eklendi.';
            }

            // Faz 3: premium e-posta şablonlarının 6 dildeki varsayılanları.
            $templates = App\Services\MailTemplateService::ensureDefaults();
            $log[] = $templates > 0
                ? $templates . ' mail şablonu (6 dil) eklendi.'
                : 'Mail şablonları zaten hazır.';
            App\Services\ReminderService::ensureSettings();

            Settings::set('brand.title', $saved['app_name'], 'brand');
            if ($saved['slogan'] !== '') {
                Settings::set('brand.slogan', $saved['slogan'], 'brand');
            }
            Settings::set('smtp.from_name', $saved['app_name'], 'mail');
            Settings::set('site.default_locale', 'tr', 'site');

            // Yonetici hesabi
            $existing = Database::scalar('SELECT id FROM users WHERE email = ?', [mb_strtolower($saved['admin_email'])]);
            if ($existing) {
                Database::update('users', [
                    'password_hash'   => password_hash($saved['admin_pass'], PASSWORD_DEFAULT),
                    'role'            => 'admin',
                    'status'          => 'active',
                    'email_verified_at' => date('Y-m-d H:i:s'),
                ], 'id = :id', ['id' => (int) $existing]);
                $log[] = 'Mevcut kullanıcı yönetici olarak güncellendi.';
            } else {
                Database::insert('users', [
                    'full_name'         => $saved['admin_name'],
                    'email'             => mb_strtolower($saved['admin_email']),
                    'password_hash'     => password_hash($saved['admin_pass'], PASSWORD_DEFAULT),
                    'role'              => 'admin',
                    'status'            => 'active',
                    'locale'            => 'tr',
                    'email_verified_at' => date('Y-m-d H:i:s'),
                    'created_at'        => date('Y-m-d H:i:s'),
                ]);
                $log[] = 'Yönetici hesabı oluşturuldu.';
            }

            @file_put_contents($lockFile, 'installed at ' . date('c'));
            $done = true;
        } catch (\Throwable $e) {
            $errors[] = 'Kurulum sırasında hata: ' . $e->getMessage();
        }
    }
}
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>evrako — Kurulum</title>
<style>
    :root{--ink:#0A1D3A;--acc:#00C48C;--bg:#F7F9FC;--line:#E3E9F2;--muted:#6B7C93;--danger:#E5484D}
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:#0F172A;font:15px/1.6 Inter,system-ui,-apple-system,Segoe UI,Arial,sans-serif}
    .wrap{max-width:820px;margin:0 auto;padding:32px 20px 64px}
    header{display:flex;align-items:center;gap:14px;margin-bottom:8px}
    h1{font-size:22px;margin:0}
    .sub{color:var(--muted);margin:0 0 24px}
    .card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:22px;margin-bottom:18px}
    .card h2{font-size:15px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin:0 0 16px}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    label{display:block;font-weight:600;font-size:13px;margin-bottom:5px}
    input{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:9px;font:inherit;background:#fff}
    input:focus{outline:2px solid rgba(0,196,140,.35);border-color:var(--acc)}
    .full{grid-column:1/-1}
    ul.req{list-style:none;padding:0;margin:0}
    ul.req li{display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px dashed var(--line)}
    ul.req li:last-child{border:0}
    .badge{font-size:12px;font-weight:700;padding:2px 9px;border-radius:99px}
    .ok{background:#E6FAF3;color:#067A57}
    .bad{background:#FDECEC;color:#B3261E}
    .warn{background:#FFF6E5;color:#8A5A00}
    button{background:var(--ink);color:#fff;border:0;border-radius:10px;padding:13px 22px;font:600 15px/1 inherit;cursor:pointer}
    button:hover{background:#12325C}
    .alert{border-radius:10px;padding:13px 16px;margin-bottom:16px;font-size:14px}
    .alert.err{background:#FDECEC;color:#8C1D18;border:1px solid #F5C2C0}
    .alert.done{background:#E6FAF3;color:#04543C;border:1px solid #A8E6CF}
    .log{font:12px/1.7 ui-monospace,Menlo,monospace;color:#33415C;background:#F2F6FB;border-radius:9px;padding:12px;margin:14px 0 0}
    a.btn{display:inline-block;background:var(--acc);color:#fff;text-decoration:none;padding:13px 22px;border-radius:10px;font-weight:700}
    code{background:#F2F6FB;padding:2px 6px;border-radius:6px}
    @media(max-width:640px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
    <header>
        <svg width="42" height="42" viewBox="0 0 64 64" aria-hidden="true">
            <rect width="64" height="64" rx="16" fill="#0A1D3A"/>
            <rect x="10" y="18" width="44" height="28" rx="7" fill="#fff"/>
            <path d="M20 32.5 L26.5 39 L44 23" fill="none" stroke="#00C48C" stroke-width="5.2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <div>
            <h1>evrako kurulum</h1>
            <p class="sub">Araç evrak takip sistemi — 3 adımda kurulum</p>
        </div>
    </header>

    <?php if ($isLocked && !$done): ?>
        <div class="alert done">Kurulum daha önce tamamlanmış. Güvenlik için <code>install.php</code> ve <code>storage/installed.lock</code> dosyalarını silin.</div>
        <p><a class="btn" href="yonetim/">Yönetim paneline git</a></p>
    <?php elseif ($done): ?>
        <div class="alert done"><strong><?php echo 'Kurulum tamamlandı!'; ?></strong> Siteyi kullanmaya hazırsınız.</div>
        <?php if ($log !== []): ?><div class="log"><?php echo htmlspecialchars(implode("\n", $log)); ?></div><?php endif; ?>
        <p style="margin-top:22px">
            <a class="btn" href="yonetim/">Yönetim paneline git</a>
        </p>
        <div class="card" style="margin-top:22px">
            <h2>Güvenlik — şimdi yapın</h2>
            <ul class="req">
                <li><span><code>install.php</code> dosyasını silin</span><span class="badge warn">zorunlu</span></li>
                <li><span><code>storage/installed.lock</code> dosyasını silin</span><span class="badge warn">zorunlu</span></li>
                <li><span>Panelden SMTP bilgilerinizi girin (e-posta gönderimi için)</span><span class="badge warn">önerilir</span></li>
                <li><span>cPanel → Cron: günde 1 kez <code>php <?php echo htmlspecialchars(BASE_PATH); ?>/cron/reminders.php</code></span><span class="badge warn">önerilir</span></li>
                <li><span><code>config/config.php</code> dosyasını yedekleyin</span><span class="badge warn">önerilir</span></li>
            </ul>
        </div>
    <?php else: ?>
        <?php foreach ($errors as $error): ?>
            <div class="alert err"><?php echo htmlspecialchars($error); ?></div>
        <?php endforeach; ?>

        <form method="post">
            <div class="card">
                <h2>1. Sunucu kontrolü</h2>
                <ul class="req">
                    <?php foreach ($reqs as [$label, $pass, $note]): ?>
                        <li>
                            <span><?php echo htmlspecialchars($label); ?></span>
                            <span class="badge <?php echo $pass ? 'ok' : (str_contains($label, 'GD') ? 'warn' : 'bad'); ?>">
                                <?php echo htmlspecialchars($note); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="card">
                <h2>2. Veritabanı &amp; site</h2>
                <div class="grid">
                    <div>
                        <label for="db_host">Veritabanı sunucusu</label>
                        <input id="db_host" name="db_host" value="<?php echo htmlspecialchars((string)($saved['db_host'] ?? 'localhost')); ?>" required>
                    </div>
                    <div>
                        <label for="db_port">Port</label>
                        <input id="db_port" name="db_port" value="<?php echo htmlspecialchars((string)($saved['db_port'] ?? '3306')); ?>">
                    </div>
                    <div>
                        <label for="db_name">Veritabanı adı</label>
                        <input id="db_name" name="db_name" value="<?php echo htmlspecialchars((string)($saved['db_name'] ?? '')); ?>" required>
                    </div>
                    <div>
                        <label for="db_user">Veritabanı kullanıcısı</label>
                        <input id="db_user" name="db_user" value="<?php echo htmlspecialchars((string)($saved['db_user'] ?? '')); ?>" required>
                    </div>
                    <div>
                        <label for="db_pass">Veritabanı şifresi</label>
                        <input id="db_pass" name="db_pass" type="password" value="">
                    </div>
                    <div>
                        <label for="app_url">Site adresi</label>
                        <input id="app_url" name="app_url" placeholder="https://evrako.com" value="<?php echo htmlspecialchars((string)($saved['app_url'] ?? '')); ?>" required>
                    </div>
                    <div>
                        <label for="app_name">Site adı</label>
                        <input id="app_name" name="app_name" value="<?php echo htmlspecialchars((string)($saved['app_name'] ?? 'evrako')); ?>" required>
                    </div>
                    <div>
                        <label for="slogan">Slogan</label>
                        <input id="slogan" name="slogan" value="<?php echo htmlspecialchars((string)($saved['slogan'] ?? 'Evrak bitişini asla kaçırma.')); ?>">
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>3. Yönetici hesabı</h2>
                <div class="grid">
                    <div>
                        <label for="admin_name">Ad Soyad</label>
                        <input id="admin_name" name="admin_name" value="<?php echo htmlspecialchars((string)($saved['admin_name'] ?? '')); ?>" required>
                    </div>
                    <div>
                        <label for="admin_email">E-posta</label>
                        <input id="admin_email" name="admin_email" type="email" value="<?php echo htmlspecialchars((string)($saved['admin_email'] ?? '')); ?>" required>
                    </div>
                    <div class="full">
                        <label for="admin_pass">Şifre (en az 8 karakter)</label>
                        <input id="admin_pass" name="admin_pass" type="password" minlength="8" required autocomplete="new-password">
                    </div>
                </div>
            </div>

            <button type="submit">Kurulumu başlat</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
