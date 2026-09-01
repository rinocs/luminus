<?php

namespace Luminus;

class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private array $cookies = [];
    private string $body = '';
    private ?string $redirectUrl = null;

    public function status(int $code): static
    {
        $this->statusCode = $code;
        return $this;
    }

    public function header(string $name, string $value): static
    {
        // Strip CR and LF characters from header name and value to prevent HTTP response splitting (CRLF injection)
        $name = str_replace(["\r", "\n"], '', $name);
        $value = str_replace(["\r", "\n"], '', $value);
        if ($name !== '') {
            $this->headers[$name] = $value;
        }
        return $this;
    }

    public function body(string $content): static
    {
        $this->body = $content;
        return $this;
    }

    public function json(mixed $data, int $status = 200): static
    {
        $this->statusCode = $status;
        $this->headers['Content-Type'] = 'application/json';
        $this->body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        return $this;
    }

    public function redirect(string $url, int $status = 302): static
    {
        $this->redirectUrl = $url;
        $this->statusCode = $status;
        return $this;
    }

    /**
     * Set a redirect response, ensuring the URL is safe (local/same-origin) to prevent Open Redirect vulnerabilities.
     */
    public function safeRedirect(string $url, string $default = '/', int $status = 302): static
    {
        if ($this->isSafeUrl($url)) {
            return $this->redirect($url, $status);
        }
        return $this->redirect($default, $status);
    }

    /**
     * Determine if a redirect URL is local and safe (prevents Open Redirect).
     */
    public function isSafeUrl(string $url): bool
    {
        if ($url === '' || str_starts_with($url, '\\')) {
            return false;
        }

        // Must start with '/' but not '//' or '/\' or leading whitespace/control character after '/'
        if (str_starts_with($url, '/')) {
            if (str_starts_with($url, '//') || str_starts_with($url, '/\\')) {
                return false;
            }
            $secondChar = $url[1] ?? '';
            if ($secondChar !== '' && (ctype_space($secondChar) || ctype_cntrl($secondChar))) {
                return false;
            }
            return true;
        }

        // If it is an absolute URL, check if it matches the current application host and uses valid http/https scheme
        $appUrl = $_ENV['APP_URL'] ?? getenv('APP_URL') ?: '';
        if ($appUrl !== '') {
            $appHost = parse_url($appUrl, PHP_URL_HOST);
            $appScheme = parse_url($appUrl, PHP_URL_SCHEME);
            $redirectHost = parse_url($url, PHP_URL_HOST);
            $redirectScheme = parse_url($url, PHP_URL_SCHEME);

            if (
                $appHost && $redirectHost && strtolower($appHost) === strtolower($redirectHost)
                && is_string($redirectScheme) && in_array(strtolower($redirectScheme), ['http', 'https'], true)
            ) {
                // If APP_URL specifies a scheme, enforce matching or valid HTTP/HTTPS equivalence
                if ($appScheme && strtolower($appScheme) === 'https' && strtolower($redirectScheme) !== 'https') {
                    return false;
                }
                return true;
            }
        }

        return false;
    }

    public function cookie(
        string $name,
        string $value = '',
        int $expires = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax'
    ): static {
        $this->cookies[$name] = [
            'name' => $name,
            'value' => $value,
            'expires' => $expires,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httpOnly' => $httpOnly,
            'sameSite' => $sameSite,
        ];
        return $this;
    }

    public function send(): void
    {
        if (php_sapi_name() !== 'cli' && !headers_sent()) {
            foreach ($this->cookies as $cookie) {
                setcookie(
                    $cookie['name'],
                    $cookie['value'],
                    [
                        'expires' => $cookie['expires'],
                        'path' => $cookie['path'],
                        'domain' => $cookie['domain'],
                        'secure' => $cookie['secure'],
                        'httponly' => $cookie['httpOnly'],
                        'samesite' => $cookie['sameSite'],
                    ]
                );
            }
        }

        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        if ($this->redirectUrl !== null) {
            header("Location: {$this->redirectUrl}");
            return;
        }

        echo $this->body;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function __toString(): string
    {
        return $this->body;
    }
}
