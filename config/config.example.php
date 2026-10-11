<?php
declare(strict_types=1);

/**
 * Bu dosya kurulum ekraninda (install.php) otomatik olarak config/config.php
 * olarak uretilir. Elle duzenlemek isterseniz bu ornegi kopyalayabilirsiniz.
 */
return [
    'app' => [
        'name'          => 'evrako',
        'url'           => 'https://example.com',
        'key'           => '',                    // kurulumda uretilir (sifreleme/imza anahtari)
        'debug'         => false,
        'locale'        => 'tr',
        'timezone'      => 'Europe/Nicosia',
        'log_level'     => 'warning',
        'session_name'  => 'evrako_session',
        'asset_version' => '1',
        'installed_at'  => '',
    ],

    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'database' => '',
        'username' => '',
        'password' => '',
        'socket'   => '',                         // yerel socket varsa: /var/run/mysqld/mysqld.sock
    ],

    'mail' => [
        'timeout' => 20,
        'dev_log' => true,                        // SMTP bosken mailleri storage/logs/mail.log'a yazar
    ],

    'uploads' => [
        'max_image_bytes' => 8388608,             // 8 MB
        'max_document_bytes' => 12582912,         // 12 MB
    ],

    'security' => [
        'trusted_proxies' => [],
        'csp_script_extra' => '',
        'cron_token' => '',                       // web-cron icin gizli anahtar
    ],
];
