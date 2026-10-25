<?php

namespace Core\System;

class HttpException extends \RuntimeException
{
    public function __construct(protected int $statusCode, string $message = '', protected array $headers = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
