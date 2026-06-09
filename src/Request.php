<?php

namespace Luminus;

class Request
{
    private array $query;
    private array $body;
    private array $files;
    private array $server;
    private array $cookies;
    private ?string $rawBody;
    private ?array $json = null;

    public function __construct(
        ?array $query = null,
        ?array $body = null,
        ?array $files = null,
        ?array $server = null,
        ?array $cookies = null,
        ?string $rawBody = null
    ) {
        $this->query = $query ?? $_GET;
        $this->body = $body ?? $_POST;
        $this->files = $files ?? $_FILES;
        $this->server = $server ?? $_SERVER;
        $this->cookies = $cookies ?? $_COOKIE;
        $this->rawBody = $rawBody;
    }

    public static function capture(): static
    {
        return new static();
    }

    public function method(): string
    {
        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST') {
            $override = $this->post('_method');

            if (is_string($override)) {
                $override = strtoupper($override);

                if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                    return $override;
                }
            }
        }

        return $method;
    }

    public function path(): string
    {
        $uri = parse_url($this->server['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        return rtrim($uri, '/') ?: '/';
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $this->body[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function header(string $name, mixed $default = null): mixed
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->server[$key] ?? $default;
    }

    public function json(): ?array
    {
        if ($this->json === null) {
            $raw = $this->rawBody ?? file_get_contents('php://input');
            $this->json = $raw ? json_decode($raw, true) : null;
        }
        return $this->json;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) && $this->files[$key]['error'] !== UPLOAD_ERR_NO_FILE;
    }

    public function uri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    public function scheme(): string
    {
        if (
            (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off')
            || ($this->server['SERVER_PORT'] ?? 80) == 443
        ) {
            return 'https';
        }
        return 'http';
    }

    public function host(): string
    {
        return $this->server['HTTP_HOST'] ?? $this->server['SERVER_NAME'] ?? 'localhost';
    }

    public function isSecure(): bool
    {
        return $this->scheme() === 'https';
    }
}
