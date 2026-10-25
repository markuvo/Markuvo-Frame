<?php

namespace Core\Database;

use InvalidArgumentException;

/**
 * Sürücüye (mysql / pgsql / sqlite) bağlı küçük SQL yardımcıları.
 */
class Grammar
{
    public const OPERATORS = ['=', '<', '>', '<=', '>=', '<>', '!=', 'like', 'not like', 'ilike', 'not ilike'];

    public static function quoteChar(string $driver): string
    {
        return $driver === 'mysql' ? '`' : '"';
    }

    /**
     * Kolon/tablo adını doğrulayıp tırnaklar. "users.id", "users.*", "name as ad" biçimleri desteklenir.
     * Geçersiz (SQL parçası içeren) ad exception fırlatır: kullanıcı girdisinin kolon adı olarak
     * kullanılması durumunda SQL injection'ı engelleyen ana savunma burasıdır.
     */
    public static function wrap(string $value, string $driver): string
    {
        $value = trim($value);

        if ($value === '*') {
            return '*';
        }

        if (preg_match('/^(.+?)\s+as\s+([A-Za-z_][A-Za-z0-9_]*)$/i', $value, $m)) {
            return self::wrap($m[1], $driver) . ' AS ' . self::wrap($m[2], $driver);
        }

        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)*(\.\*)?$/', $value)) {
            throw new InvalidArgumentException("Geçersiz kolon/tablo adı: '{$value}'. Ham SQL için selectRaw()/whereRaw() kullanın.");
        }

        $q = self::quoteChar($driver);

        return implode('.', array_map(
            fn(string $segment) => $segment === '*' ? '*' : $q . $segment . $q,
            explode('.', $value)
        ));
    }

    public static function normalizeOperator(string $operator): string
    {
        $operator = strtolower(trim($operator));

        if (!in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException("Geçersiz SQL operatörü: '{$operator}'.");
        }

        return strtoupper($operator);
    }

    public static function normalizeDirection(string $direction): string
    {
        $direction = strtoupper(trim($direction));

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException("Geçersiz sıralama yönü: '{$direction}' (ASC ya da DESC olmalı).");
        }

        return $direction;
    }
}
