<?php
declare(strict_types=1);

/**
 * Test yardımcısı: yerel SMTP alıcısı (sink).
 *
 * Kullanım:
 *   php .freebuff/smtp-sink.php 2525 /tmp/smtp-sink 3 60
 *     argümanlar: port, çıktı klasörü, mesaj sayısı, saniye cinsinden zaman aşımı
 *
 * AUTH gerektirmez (test sırasında smtp.auth=0 kullanılır, encryption=none).
 * Gelen her mesajı <klasör>/<n>.eml olarak yazar. Belirtilen mesaj sayısına
 * ulaşınca veya zaman aşımında çıkar.
 */

$port = (int) ($argv[1] ?? 2525);
$dir = (string) ($argv[2] ?? '/tmp/smtp-sink');
$maxMessages = (int) ($argv[3] ?? 1);
$timeout = (int) ($argv[4] ?? 60);

@mkdir($dir, 0777, true);

$server = stream_socket_server('tcp://127.0.0.1:' . $port, $errno, $errstr);
if ($server === false) {
    fwrite(STDERR, "Sink başlatılamadı: {$errstr}\n");
    exit(1);
}
stream_set_blocking($server, false);
echo "sink listening on {$port}\n";

$deadline = time() + $timeout;
$received = 0;

while (time() < $deadline && $received < $maxMessages) {
    $client = @stream_socket_accept($server, 1);
    if ($client === false) {
        continue;
    }
    stream_set_timeout($client, 10);
    fwrite($client, "220 sink.local ESMTP ready\r\n");

    $inData = false;
    $buffer = '';
    while (!feof($client)) {
        $line = fgets($client, 8192);
        if ($line === false) {
            break;
        }
        if ($inData) {
            if (rtrim($line, "\r\n") === '.') {
                $inData = false;
                $received++;
                file_put_contents($dir . '/' . $received . '.eml', $buffer);
                echo "message {$received} (" . strlen($buffer) . " bytes)\n";
                if ($received >= $maxMessages) {
                    fwrite($client, "250 2.0.0 Ok: queued\r\n");
                    break;
                }
                fwrite($client, "250 2.0.0 Ok: queued\r\n");
                continue;
            }
            $buffer .= $line;
            continue;
        }

        $command = strtoupper(substr(trim($line), 0, 4));
        if (str_starts_with($command, 'EHLO') || str_starts_with($command, 'HELO')) {
            fwrite($client, "250-sink.local\r\n250-SIZE 10485760\r\n250 8BITMIME\r\n");
        } elseif (str_starts_with($command, 'DATA')) {
            fwrite($client, "354 End data with <CR><LF>.<CR><LF>\r\n");
            $inData = true;
        } elseif (str_starts_with($command, 'QUIT')) {
            fwrite($client, "221 Bye\r\n");
            break;
        } else {
            fwrite($client, "250 2.1.0 Ok\r\n");
        }
    }
    fclose($client);
}

fclose($server);
echo "sink done ({$received} mesaj)\n";
exit(0);
