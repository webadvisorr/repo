<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    private array $query;
    private array $body;
    private array $files;
    private array $server;
    private array $cookies;

    public function __construct()
    {
        $this->query   = $_GET;
        $this->body    = $_POST;
        $this->files   = $_FILES;
        $this->server  = $_SERVER;
        $this->cookies = $_COOKIE;
    }

    public static function capture(): self
    {
        return new self();
    }

    public function method(): string
    {
        $method = strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
        // Formlarda _method = PUT/DELETE destegi
        $override = $this->body['_method'] ?? $this->server['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? null;
        if ($method === 'POST' && is_string($override)) {
            $override = strtoupper($override);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }
        return $method;
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function path(): string
    {
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        return '/' . trim($path, '/');
    }

    public function url(): string
    {
        return (string) ($this->server['REQUEST_URI'] ?? '/');
    }

    public function fullUrl(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host = (string) ($this->server['HTTP_HOST'] ?? 'localhost');
        return $scheme . '://' . $host . $this->url();
    }

    public function isSecure(): bool
    {
        if (($this->server['HTTPS'] ?? '') === 'on') {
            return true;
        }
        if (($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
            return true;
        }
        return false;
    }

    public function ip(): string
    {
        // Proxy arkasinda sadece guvenilir baslik kullanilir; yine de dogrulanir.
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            $value = $this->server[$key] ?? null;
            if (!is_string($value) || $value === '') {
                continue;
            }
            $candidate = trim(explode(',', $value)[0]);
            if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                return $candidate;
            }
        }
        return '0.0.0.0';
    }

    public function userAgent(): string
    {
        return Str::limit((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 250, '');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);
        if (is_array($value)) {
            return $default;
        }
        return trim((string) $value);
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public function bool(string $key): bool
    {
        $value = $this->input($key, false);
        return in_array($value, ['1', 1, true, 'true', 'on', 'yes'], true);
    }

    /** @return array<int,string> */
    public function array(string $key): array
    {
        $value = $this->input($key, []);
        return is_array($value) ? $value : [];
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function only(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->input($key);
        }
        return $out;
    }

    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $file;
    }

    /** @return array<int,array> */
    public function files(string $key): array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file) || !is_array($file['name'])) {
            $single = $this->file($key);
            return $single === null ? [] : [$single];
        }
        $out = [];
        foreach (array_keys($file['name']) as $i) {
            if (($file['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'name'     => $file['name'][$i],
                'type'     => $file['type'][$i],
                'tmp_name' => $file['tmp_name'][$i],
                'error'    => $file['error'][$i],
                'size'     => $file['size'][$i],
            ];
        }
        return $out;
    }

    public function cookie(string $key, ?string $default = null): ?string
    {
        $value = $this->cookies[$key] ?? null;
        return is_string($value) ? $value : $default;
    }

    /** Sadece ayni origin'den gelen istekleri kabul et (CSRF ek katmani). */
    public function isSameOrigin(): bool
    {
        $origin = $this->server['HTTP_ORIGIN'] ?? null;
        if (!is_string($origin) || $origin === '') {
            return true; // Origin gonderilmiyorsa CSRF token kontrolu devrede kaliyor.
        }
        $host = (string) ($this->server['HTTP_HOST'] ?? '');
        $originHost = parse_url($origin, PHP_URL_HOST);
        return is_string($originHost) && strcasecmp($originHost, $host) === 0;
    }

    public function wantsJson(): bool
    {
        $accept = (string) ($this->server['HTTP_ACCEPT'] ?? '');
        $requested = (string) ($this->server['HTTP_X_REQUESTED_WITH'] ?? '');
        return str_contains($accept, 'application/json') || $requested === 'XMLHttpRequest';
    }
}
