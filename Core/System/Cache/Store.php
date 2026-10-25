<?php

namespace Core\System\Cache;

interface Store
{
    public function get(string $key): mixed;                 // yoksa null
    public function put(string $key, mixed $value, int $seconds): void;   // 0 = süresiz
    public function has(string $key): bool;
    public function forget(string $key): void;
    public function flush(): void;
    public function increment(string $key, int $by = 1, int $ttl = 0): int;
}
