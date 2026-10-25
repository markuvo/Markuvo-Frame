<?php

namespace Core\System\Cache;

/** Yalnızca istek boyunca bellekte tutar (testler ve geçici veri için). */
class ArrayStore implements Store
{
    protected array $items = [];

    public function get(string $key): mixed
    {
        if (!isset($this->items[$key])) {
            return null;
        }
        [$expires, $value] = $this->items[$key];
        if ($expires !== 0 && $expires <= time()) {
            unset($this->items[$key]);

            return null;
        }

        return $value;
    }

    public function put(string $key, mixed $value, int $seconds): void
    {
        $this->items[$key] = [$seconds > 0 ? time() + $seconds : 0, $value];
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function forget(string $key): void
    {
        unset($this->items[$key]);
    }

    public function flush(): void
    {
        $this->items = [];
    }

    public function increment(string $key, int $by = 1, int $ttl = 0): int
    {
        $current = $this->get($key);
        $expires = $this->items[$key][0] ?? ($ttl > 0 ? time() + $ttl : 0);
        $value = (int) $current + $by;
        $this->items[$key] = [$expires, $value];

        return $value;
    }
}
