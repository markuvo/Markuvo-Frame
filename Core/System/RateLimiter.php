<?php

namespace Core\System;

use Core\System\Cache\CacheManager;

/** Sabit pencere: $decaySeconds içinde en fazla $max deneme. */
class RateLimiter
{
    public function __construct(protected CacheManager $cache)
    {
    }

    /** Bir deneme kaydeder, toplam deneme sayısını döner. */
    public function hit(string $key, int $decaySeconds = 60): int
    {
        return $this->cache->increment('rl:' . sha1($key), 1, $decaySeconds);
    }

    public function attempts(string $key): int
    {
        return (int) $this->cache->get('rl:' . sha1($key), 0);
    }

    public function tooManyAttempts(string $key, int $max): bool
    {
        return $this->attempts($key) >= $max;
    }

    public function clear(string $key): void
    {
        $this->cache->forget('rl:' . sha1($key));
    }
}
