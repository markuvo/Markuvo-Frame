<?php

namespace Core\Support;

class Str
{
    protected const IRREGULAR = [
        'person' => 'people', 'man' => 'men', 'woman' => 'women', 'child' => 'children',
        'foot' => 'feet', 'tooth' => 'teeth', 'mouse' => 'mice', 'goose' => 'geese', 'ox' => 'oxen',
    ];

    protected const UNCOUNTABLE = [
        'sheep', 'fish', 'series', 'species', 'news', 'data', 'information', 'equipment', 'media',
    ];

    /** UserProfile -> user_profile */
    public static function snake(string $value): string
    {
        $value = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $value);
        $value = preg_replace('/[\s\-]+/', '_', $value);

        return strtolower($value);
    }

    /** user_profile -> UserProfile */
    public static function studly(string $value): string
    {
        $words = preg_split('/[\s_\-]+/', $value, -1, PREG_SPLIT_NO_EMPTY);

        return implode('', array_map(fn($w) => ucfirst($w), $words));
    }

    /** İngilizce basit çoğul: user -> users, category -> categories, box -> boxes */
    public static function plural(string $value): string
    {
        $parts = explode('_', $value);
        $last = array_pop($parts);
        $lower = strtolower($last);

        if (in_array($lower, self::UNCOUNTABLE, true)) {
            $plural = $last;
        } elseif (isset(self::IRREGULAR[$lower])) {
            $plural = self::IRREGULAR[$lower];
        } elseif (preg_match('/(s|x|z|ch|sh)$/i', $last)) {
            $plural = $last . 'es';
        } elseif (preg_match('/[^aeiou]y$/i', $last)) {
            $plural = substr($last, 0, -1) . 'ies';
        } else {
            $plural = $last . 's';
        }

        $parts[] = $plural;

        return implode('_', $parts);
    }

    public static function slug(string $value, string $separator = '-'): string
    {
        $value = strtr($value, [
            'ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'İ' => 'i',
            'ö' => 'o', 'Ö' => 'o', 'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u',
        ]);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', $separator, $value);

        return trim($value, $separator);
    }
}
