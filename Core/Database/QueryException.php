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
            'Markuvo Veritabanı Sorgu Hatası: ' . static::redact($previous->getMessage()) . ' (SQL: ' . $sql . ')',
            (int) $previous->getCode(),
            $previous
        );
    }

    /**
     * Sürücü hata metinleri kullanıcının girdiği DEĞERLERİ içerebilir
     * (MySQL: "Duplicate entry 'ali@x.com' ...", PostgreSQL: "Key (email)=(ali@x.com) ...").
     * Günlüğe ve hata sayfasına gitmeden önce değerler maskelenir.
     */
    public static function redact(string $message): string
    {
        $patterns = [
            // MySQL / MariaDB
            "/Duplicate entry '.*?' for key/s" => "Duplicate entry '***' for key",
            "/(value: )'.*?'( for column)/s" => "$1'***'$2",
            "/(Data too long for column '[^']*' at row \d+)/" => '$1',
            // PostgreSQL
            '/Key \(([^)]*)\)=\(.*?\) (already exists|is not present|is still referenced)/s' => 'Key ($1)=(***) $2',
            '/(Failing row contains )\(.*\)/s' => '$1(***)',
            '/(invalid input syntax for [^:]+: )"[^"]*"/' => '$1"***"',
            '/(invalid input syntax for [^:]+: )\'[^\']*\'/' => "$1'***'",
        ];

        foreach ($patterns as $regex => $replacement) {
            $message = preg_replace($regex, $replacement, $message) ?? $message;
        }

        return $message;
    }

    public function getSql(): string
    {
        return $this->sql;
    }
}
