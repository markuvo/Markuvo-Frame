<?php

namespace Core\Database;

/**
 * Doğrulama/tırnaklama uygulanmadan olduğu gibi SQL'e yazılan ifade.
 * YALNIZCA sizin yazdığınız sabit SQL için kullanın; kullanıcı girdisini asla eklemeyin.
 */
class Expression
{
    public function __construct(protected string $value)
    {
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
