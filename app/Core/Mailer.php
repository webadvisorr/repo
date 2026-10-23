<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Saf PHP SMTP istemcisi (harici kutuphane/Composer gerekmez).
 * Destekler: SMTPS (465, ssl) ve STARTTLS (587/25, tls), AUTH LOGIN/PLAIN,
 * UTF-8 konu kodlama, HTML + düz metin alternatifi, ek dosya.
 *
 * Kurumsal mail bilgileri panelden girilir: host, port, sifreleme, kullanici, sifre.
 */
final class Mailer
{
    private string $host;
    private int $port;
    private string $encryption;   // tls | ssl | none
    private string $username;
    private string $password;
    private int $timeout;
    private string $fromEmail;
    private string $fromName;
    private bool $smtpAuth;

    /** @var resource|null */
    private $socket = null;
    private array $transcript = [];

    /**
     * @param array|null $config Yalnızca geçersiz kılınacak alanlar verilebilir
     *                           (ör. ['timeout' => 6]); diğerleri ayarlardan okunur.
     */
    public function __construct(?array $config = null)
    {
        $config = array_merge([
            'host'       => (string) Settings::get('smtp.host', ''),
            'port'       => (int) Settings::get('smtp.port', 587),
            'encryption' => (string) Settings::get('smtp.encryption', 'tls'),
            'username'   => (string) Settings::get('smtp.username', ''),
            'password'   => (string) Settings::get('smtp.password', ''),
            'auth'       => (bool) Settings::get('smtp.auth', true),
            'from_email' => (string) Settings::get('smtp.from_email', ''),
            'from_name'  => (string) Settings::get('smtp.from_name', (string) Settings::get('brand.title', 'Evrako')),
            'timeout'    => (int) Config::get('mail.timeout', 20),
        ], $config ?? []);
        $this->host = (string) ($config['host'] ?? '');
        $this->port = (int) ($config['port'] ?? 587);
        $this->encryption = strtolower((string) ($config['encryption'] ?? 'tls'));
        $this->username = (string) ($config['username'] ?? '');
        $this->password = (string) ($config['password'] ?? '');
        $this->smtpAuth = (bool) ($config['auth'] ?? true);
        $this->fromEmail = (string) ($config['from_email'] ?? $this->username);
        $this->fromName = (string) ($config['from_name'] ?? '');
        $this->timeout = max(3, (int) ($config['timeout'] ?? 20));
    }

    public function isConfigured(): bool
    {
        return $this->host !== '' && $this->fromEmail !== '';
    }

    /**
     * @param array<int,array{path:string,name?:string}> $attachments
     * @return array{ok:bool,error?:string}
     */
    public function send(string $toEmail, string $subject, string $htmlBody, ?string $textBody = null, array $attachments = [], array $headers = []): array
    {
        $toEmail = trim($toEmail);
        if (filter_var($toEmail, FILTER_VALIDATE_EMAIL) === false) {
            return ['ok' => false, 'error' => 'Gecersiz alici adresi'];
        }
        if (!$this->isConfigured()) {
            // Geliştirme modu: SMTP yoksa maili dosyaya yaz (config: mail.dev_log).
            if ((bool) Config::get('mail.dev_log', false)) {
                $this->writeDevLog($toEmail, $subject, $htmlBody, $textBody);
                return ['ok' => true, 'dev' => true];
            }
            return ['ok' => false, 'error' => 'SMTP ayarlari eksik (host / gonderen adresi)'];
        }

        $textBody ??= trim(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody) ?? $htmlBody));
        $boundaryMixed = 'mix_' . Str::random(10);
        $boundaryAlt = 'alt_' . Str::random(10);
        $messageId = '<' . Str::random(12) . '@' . $this->domainOf($this->fromEmail) . '>';

        $head = [];
        $head[] = 'Date: ' . date('r');
        $head[] = 'From: ' . $this->formatAddress($this->fromEmail, $this->fromName);
        $head[] = 'To: ' . $this->formatAddress($toEmail, '');
        $head[] = 'Subject: ' . $this->encodeHeader($subject);
        $head[] = 'Message-ID: ' . $messageId;
        $head[] = 'MIME-Version: 1.0';
        $head[] = 'X-Mailer: evrako';
        foreach ($headers as $name => $value) {
            if (preg_match('/^[A-Za-z0-9\-]+$/', (string) $name)) {
                $head[] = $name . ': ' . $this->sanitizeHeader((string) $value);
            }
        }

        $alt = "--{$boundaryAlt}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($textBody), 76, "\r\n")
            . "--{$boundaryAlt}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($htmlBody), 76, "\r\n")
            . "--{$boundaryAlt}--\r\n";

        if ($attachments === []) {
            $body = "Content-Type: multipart/alternative; boundary=\"{$boundaryAlt}\"\r\n\r\n" . $alt;
        } else {
            $body = "Content-Type: multipart/mixed; boundary=\"{$boundaryMixed}\"\r\n\r\n"
                . "--{$boundaryMixed}\r\n"
                . "Content-Type: multipart/alternative; boundary=\"{$boundaryAlt}\"\r\n\r\n" . $alt;
            foreach ($attachments as $attachment) {
                $path = (string) ($attachment['path'] ?? '');
                if (!is_file($path)) {
                    continue;
                }
                $name = (string) ($attachment['name'] ?? basename($path));
                $body .= "--{$boundaryMixed}\r\n"
                    . 'Content-Type: application/octet-stream; name="' . $this->sanitizeHeader($name) . "\"\r\n"
                    . "Content-Transfer-Encoding: base64\r\n"
                    . 'Content-Disposition: attachment; filename="' . $this->sanitizeHeader($name) . "\"\r\n\r\n"
                    . chunk_split(base64_encode((string) file_get_contents($path)), 76, "\r\n");
            }
            $body .= "--{$boundaryMixed}--\r\n";
        }

        $data = implode("\r\n", $head) . "\r\n" . $body;

        return $this->deliver($toEmail, $data);
    }

    /** SMTP yokken geliştirme ortamında maili storage/logs/mail.log'a yazar. */
    private function writeDevLog(string $toEmail, string $subject, string $htmlBody, ?string $textBody): void
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }
        $entry = "\n===== " . date('Y-m-d H:i:s') . " =====\n"
            . 'To: ' . $toEmail . "\n"
            . 'Subject: ' . $subject . "\n"
            . "\n--- text/plain ---\n" . ($textBody ?? '')
            . "\n--- text/html ---\n" . $htmlBody . "\n";
        @file_put_contents($dir . '/mail.log', $entry, FILE_APPEND | LOCK_EX);
    }

    private function deliver(string $toEmail, string $data): array
    {
        try {
            $this->connect();
            $this->command('EHLO ' . $this->domainOf($this->fromEmail), 250);

            if ($this->encryption === 'tls') {
                $this->command('STARTTLS', 220);
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('STARTTLS baslatilamadi');
                }
                $this->command('EHLO ' . $this->domainOf($this->fromEmail), 250);
            }

            if ($this->smtpAuth) {
                $this->authenticate();
            }

            $this->command('MAIL FROM:<' . $this->fromEmail . '>', 250);
            $this->command('RCPT TO:<' . $toEmail . '>', [250, 251]);
            $this->command('DATA', 354);

            // Nokta ile baslayan satirlari kacir (SMTP kurali), satir sonlarini normalize et.
            $normalized = preg_replace('/\r\n|\r|\n/', "\r\n", $data) ?? $data;
            $normalized = preg_replace('/^\./m', '..', $normalized) ?? $normalized;
            fwrite($this->socket, $normalized . "\r\n.\r\n");
            $this->command('', 250);

            $this->command('QUIT', 221);
            $this->close();
            return ['ok' => true];
        } catch (\Throwable $e) {
            $this->close();
            Logger::error('Mail gonderilemedi: ' . $e->getMessage(), ['to' => $toEmail, 'transcript' => array_slice($this->transcript, -6)]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function connect(): void
    {
        $target = ($this->encryption === 'ssl' ? 'ssl://' : '') . $this->host . ':' . $this->port;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false,
                'SNI_enabled'       => true,
            ],
        ]);
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($target, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            throw new \RuntimeException("SMTP sunucusuna baglanilamadi ({$this->host}:{$this->port}) {$errstr}");
        }
        stream_set_timeout($socket, $this->timeout);
        $this->socket = $socket;
        $this->readResponse(220);
    }

    private function authenticate(): void
    {
        if ($this->username === '') {
            return;
        }
        $this->command('AUTH LOGIN', 334);
        $this->command(base64_encode($this->username), 334);
        $this->command(base64_encode($this->password), 235);
    }

    private function command(string $command, int|array $expected): string
    {
        if ($this->socket === null) {
            throw new \RuntimeException('SMTP baglantisi yok');
        }
        if ($command !== '') {
            fwrite($this->socket, $command . "\r\n");
            $this->transcript[] = '> ' . ($this->isSecret($command) ? '[gizli]' : $command);
        }
        $expectedCodes = is_array($expected) ? $expected : [$expected];
        $response = $this->readResponse($expectedCodes);
        return $response;
    }

    private function readResponse(int|array $expected): string
    {
        $expectedCodes = is_array($expected) ? $expected : [$expected];
        $response = '';
        $code = 0;
        while (($line = fgets($this->socket, 2048)) !== false) {
            $response .= $line;
            if (preg_match('/^(\d{3})/', $line, $m)) {
                $code = (int) $m[1];
            }
            // Çok satirli cevaplarda "250-" devam eder, "250 " biter.
            if (!preg_match('/^\d{3}-/', $line)) {
                break;
            }
        }
        $response = trim($response);
        $this->transcript[] = '< ' . $response;
        if (!in_array($code, $expectedCodes, true)) {
            throw new \RuntimeException('SMTP hatasi: ' . Str::limit($response, 200, ''));
        }
        return $response;
    }

    private function isSecret(string $command): bool
    {
        return base64_decode($command, true) === $this->password;
    }

    private function close(): void
    {
        if ($this->socket !== null) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    private function encodeHeader(string $value): string
    {
        $value = $this->sanitizeHeader($value);
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    /** Header injection (satir sonu ile ek basliik enjeksiyonu) korumasi. */
    private function sanitizeHeader(string $value): string
    {
        return trim(str_replace(["\r", "\n", "%0a", "%0d"], '', $value));
    }

    private function formatAddress(string $email, string $name): string
    {
        return $name === '' ? $email : $this->encodeHeader($name) . ' <' . $email . '>';
    }

    private function domainOf(string $email): string
    {
        $parts = explode('@', $email);
        return (string) ($parts[1] ?? 'localhost');
    }
}
