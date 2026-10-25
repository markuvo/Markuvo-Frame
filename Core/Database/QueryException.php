<?php

namespace Core\Database;

use PDOException;
use RuntimeException;

/** Bir SQL sorgusu çalışırken oluşan hata. Ham SQL'i taşır (bağlama değerlerini TAŞIMAZ: şifre sızmasın). */
class QueryException extends RuntimeException
{
    protected string $sql;

    public function __construct(string $sql, PDOException $previous)
    {
        $this->sql = $sql;

        parent::__construct(
            'Markuvo Veritabanı Sorgu Hatası: ' . $previous->getMessage() . ' (SQL: ' . $sql . ')',
            (int) $previous->getCode(),
            $previous
        );
    }

    public function getSql(): string
    {
        return $this->sql;
    }
}
