<?php

namespace Core\System;

class Response
{
    public function __construct(
        protected string $content = '',
        protected int $status = 200,
        protected array $headers = []
    ) {
        if (!$this->hasHeader('Content-Type')) {
            $this->headers['Content-Type'] = 'text/html; charset=UTF-8';
        }
    }

    public static function html(string $content, int $status = 200, array $headers = []): static
    {
        return new static($content, $status, $headers);
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): static
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return new static($json === false ? 'null' : $json, $status, ['Content-Type' => 'application/json; charset=UTF-8'] + $headers);
    }

    public static function redirect(string $url, int $status = 302): static
    {
        // Başlık enjeksiyonunu (CRLF) engelle
        $url = str_replace(["\r", "\n"], '', $url);

        return new static('', $status, ['Location' => $url]);
    }

    /** Controller'ın döndürdüğü her değeri Response'a çevirir. */
    public static function make(mixed $value): static
    {
        if ($value instanceof static) {
            return $value;
        }
        if (is_array($value) || $value instanceof \JsonSerializable) {
            return static::json($value);
        }
        if ($value === null || is_bool($value)) {
            return new static('');
        }

        return new static((string) $value);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function header(string $name, string $value): static
    {
        $this->headers[$name] = str_replace(["\r", "\n"], '', $value);

        return $this;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        foreach (array_keys($this->headers) as $key) {
            if (strcasecmp($key, $name) === 0) {
                return true;
            }
        }

        return false;
    }

    public function getHeader(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }

        echo $this->content;
    }

    public function __toString(): string
    {
        return $this->content;
    }
}
