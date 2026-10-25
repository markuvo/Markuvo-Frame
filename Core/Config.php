<?php

namespace Core;

use Core\Support\Arr;

/**
 * config/*.php dosyalarını okur. config('app.debug') gibi nokta gösterimiyle erişilir.
 * Dosya adı ilk anahtardır: config/database.php -> config('database.default')
 */
class Config
{
    protected array $items = [];

    public function load(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (glob(rtrim($directory, '/\\') . '/*.php') ?: [] as $file) {
            $values = require $file;

            if (is_array($values)) {
                $this->items[basename($file, '.php')] = $values;
            }
        }
    }

    public function get(string $key, $default = null)
    {
        return Arr::get($this->items, $key, $default);
    }

    public function has(string $key): bool
    {
        return Arr::has($this->items, $key);
    }

    public function set(string $key, $value): void
    {
        Arr::set($this->items, $key, $value);
    }

    public function all(): array
    {
        return $this->items;
    }
}
