<?php

namespace Luminus;

class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private string $body = '';
    private ?string $redirectUrl = null;

    public function status(int $code): static
    {
        $this->statusCode = $code;
        return $this;
    }

    public function header(string $name, string $value): static
    {
        $this->headers[$name] = $value;
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
        $this->body = json_encode($data, JSON_UNESCAPED_UNICODE);
        return $this;
    }

    public function redirect(string $url, int $status = 302): static
    {
        $this->redirectUrl = $url;
        $this->statusCode = $status;
        return $this;
    }

    public function send(): void
    {
        if ($this->redirectUrl !== null) {
            http_response_code($this->statusCode);
            header("Location: {$this->redirectUrl}");
            return;
        }

        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
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
