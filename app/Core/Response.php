<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    private int $status = 200;
    private array $headers = [];
    private string $body = '';

    public static function make(string $body = '', int $status = 200): self
    {
        $r = new self();
        $r->body = $body;
        $r->status = $status;
        return $r;
    }

    public static function redirect(string $to, int $status = 302): never
    {
        if (!headers_sent()) {
            header('Location: ' . $to, true, $status);
        }
        exit;
    }

    public static function json(mixed $data, int $status = 200): never
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            http_response_code($status);
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function body(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        echo $this->body;
    }
}
