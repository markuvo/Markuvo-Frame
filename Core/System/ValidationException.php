<?php

namespace Core\System;

class ValidationException extends \RuntimeException
{
    public function __construct(protected array $errors, protected array $input = [])
    {
        parent::__construct('Girilen veriler geçersiz.', 422);
    }

    /** @return array<string, string[]> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function input(): array
    {
        return $this->input;
    }
}
