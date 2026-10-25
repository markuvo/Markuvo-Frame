<?php

namespace Core\System;

use Core\Database\DB;
use Core\Support\Arr;

/**
 * Doğrulayıcı. Kurallar "|" ile ayrılmış metin ya da dizi olabilir:
 *
 *   $veri = Validator::make($request->all(), [
 *       'email' => 'required|email|unique:users,email',
 *       'sifre' => 'required|min:6|confirmed',
 *   ])->validate();            // hata varsa ValidationException fırlatır
 */
class Validator
{
    protected array $errors = [];
    protected bool $ran = false;

    public function __construct(
        protected array $data,
        protected array $rules,
        protected array $messages = [],
        protected array $attributes = []
    ) {
    }

    public static function make(array $data, array $rules, array $messages = [], array $attributes = []): static
    {
        return new static($data, $rules, $messages, $attributes);
    }

    public function fails(): bool
    {
        $this->run();

        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    public function errors(): array
    {
        $this->run();

        return $this->errors;
    }

    /** Başarılıysa yalnızca kurallarda geçen alanları döner; değilse ValidationException. */
    public function validate(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors, $this->data);
        }

        $out = [];
        foreach (array_keys($this->rules) as $field) {
            if (!str_contains($field, '*') && Arr::has($this->data, $field)) {
                Arr::set($out, $field, Arr::get($this->data, $field));
            }
        }

        return $out;
    }

    protected function run(): void
    {
        if ($this->ran) {
            return;
        }
        $this->ran = true;

        foreach ($this->rules as $field => $rules) {
            $rules = is_array($rules) ? $rules : explode('|', (string) $rules);
            $value = Arr::get($this->data, $field);
            $nullable = in_array('nullable', $rules, true);
            $isEmpty = $value === null || $value === '' || $value === [];
            $numericRule = (bool) array_intersect(['numeric', 'integer'], $rules);

            foreach ($rules as $rule) {
                if ($rule === 'nullable' || $rule === '' ) {
                    continue;
                }

                [$name, $params] = $this->parse($rule);

                if ($isEmpty && $name !== 'required' && $name !== 'required_with') {
                    continue; // boş ve zorunlu değilse diğer kurallar atlanır
                }
                if ($isEmpty && $nullable && $name === 'required') {
                    continue;
                }

                if (!$this->check($name, $field, $value, $params, $numericRule)) {
                    $this->errors[$field][] = $this->message($field, $name, $params, $numericRule);
                    if ($name === 'required') {
                        break;
                    }
                }
            }
        }
    }

    protected function parse(string $rule): array
    {
        $name = $rule;
        $params = [];
        if (str_contains($rule, ':')) {
            [$name, $rest] = explode(':', $rule, 2);
            $params = $name === 'regex' ? [$rest] : explode(',', $rest);
        }

        return [$name, $params];
    }

    protected function check(string $rule, string $field, mixed $value, array $p, bool $numericRule = false): bool
    {
        switch ($rule) {
            case 'required':
                return !($value === null || (is_string($value) && trim($value) === '') || $value === []);
            case 'string':
                return is_string($value);
            case 'integer':
                return filter_var($value, FILTER_VALIDATE_INT) !== false;
            case 'numeric':
                return is_numeric($value);
            case 'boolean':
                return in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true);
            case 'array':
                return is_array($value);
            case 'email':
                return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            case 'url':
                return is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false;
            case 'date':
                return is_string($value) && strtotime($value) !== false;
            case 'alpha':
                return is_string($value) && preg_match('/^\pL+$/u', $value) === 1;
            case 'alpha_num':
                return is_string($value) && preg_match('/^[\pL\pN]+$/u', $value) === 1;
            case 'min':
                return $this->size($value, $numericRule) >= (float) $p[0];
            case 'max':
                return $this->size($value, $numericRule) <= (float) $p[0];
            case 'between':
                return $this->size($value, $numericRule) >= (float) $p[0] && $this->size($value, $numericRule) <= (float) $p[1];
            case 'in':
                return in_array((string) $value, $p, true);
            case 'not_in':
                return !in_array((string) $value, $p, true);
            case 'confirmed':
                return $value === Arr::get($this->data, $field . '_confirmation');
            case 'same':
                return $value === Arr::get($this->data, $p[0]);
            case 'different':
                return $value !== Arr::get($this->data, $p[0]);
            case 'regex':
                return is_string($value) && @preg_match($p[0], $value) === 1;
            case 'required_with':
                $other = Arr::get($this->data, $p[0]);
                return ($other === null || $other === '') || !($value === null || $value === '');
            case 'unique':
                return $this->unique($field, $value, $p);
            case 'exists':
                return DB::table($p[0])->where($p[1] ?? $field, '=', $value)->exists();
        }

        throw new \InvalidArgumentException("Bilinmeyen doğrulama kuralı: {$rule}");
    }

    /** unique:tablo,sütun,yoksayılacak_id,id_sütunu */
    protected function unique(string $field, mixed $value, array $p): bool
    {
        $query = DB::table($p[0])->where($p[1] ?? $field, '=', $value);

        if (isset($p[2]) && $p[2] !== '' && $p[2] !== 'NULL') {
            $query->where($p[3] ?? 'id', '!=', $p[2]);
        }

        return !$query->exists();
    }

    /** Sayı ise değerin kendisi, metin ise karakter sayısı, dizi ise eleman sayısı. */
    protected function size(mixed $value, bool $numericRule = false): float
    {
        if (is_array($value)) {
            return count($value);
        }
        if ($numericRule && is_numeric($value)) {
            return (float) $value;
        }

        return (float) mb_strlen((string) $value);
    }

    protected function message(string $field, string $rule, array $p, bool $numericRule = false): string
    {
        $custom = $this->messages[$field . '.' . $rule] ?? $this->messages[$rule] ?? null;
        $label = $this->attributes[$field] ?? str_replace('_', ' ', $field);

        $text = $custom ?? $this->defaultMessage($rule, $field, $p, $numericRule);

        return str_replace([':attribute', ':min', ':max', ':values', ':other'], [
            $label, $p[0] ?? '', $rule === 'between' ? ($p[1] ?? '') : ($p[0] ?? ''), implode(', ', $p), $p[0] ?? '',
        ], $text);
    }

    protected function defaultMessage(string $rule, string $field, array $p, bool $numeric = false): string
    {
        $unit = fn() => is_array(Arr::get($this->data, $field)) ? 'öğe' : ($numeric ? '' : ' karakter');

        return match ($rule) {
            'required', 'required_with' => ':attribute alanı zorunludur.',
            'string' => ':attribute metin olmalıdır.',
            'integer' => ':attribute tam sayı olmalıdır.',
            'numeric' => ':attribute sayı olmalıdır.',
            'boolean' => ':attribute doğru/yanlış olmalıdır.',
            'array' => ':attribute liste olmalıdır.',
            'email' => ':attribute geçerli bir e-posta adresi olmalıdır.',
            'url' => ':attribute geçerli bir adres (URL) olmalıdır.',
            'date' => ':attribute geçerli bir tarih olmalıdır.',
            'alpha' => ':attribute yalnızca harf içermelidir.',
            'alpha_num' => ':attribute yalnızca harf ve rakam içermelidir.',
            'min' => ':attribute en az :min' . $unit() . ' olmalıdır.',
            'max' => ':attribute en fazla :max' . $unit() . ' olmalıdır.',
            'between' => ':attribute :min ile :max' . $unit() . ' arasında olmalıdır.',
            'in' => 'Seçilen :attribute geçersiz.',
            'not_in' => 'Seçilen :attribute geçersiz.',
            'confirmed' => ':attribute doğrulaması eşleşmiyor.',
            'same' => ':attribute ile :other aynı olmalıdır.',
            'different' => ':attribute ile :other farklı olmalıdır.',
            'regex' => ':attribute biçimi geçersiz.',
            'unique' => 'Bu :attribute zaten kullanılıyor.',
            'exists' => 'Seçilen :attribute geçersiz.',
            default => ':attribute geçersiz.',
        };
    }
}
