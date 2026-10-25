<?php

namespace Core\System\Cache;

class RedisStore implements Store
{
    protected \Redis $redis;

    public function __construct(array $config, protected string $prefix = '')
    {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('Redis önbelleği için php-redis eklentisi gerekli (php -m | grep redis).');
        }

        $this->redis = new \Redis();
        if (!$this->redis->connect((string) ($config['host'] ?? '127.0.0.1'), (int) ($config['port'] ?? 6379), 2.0)) {
            throw new \RuntimeException('Redis sunucusuna bağlanılamadı.');
        }
        if (!empty($config['password'])) {
            $this->redis->auth($config['password']);
        }
        $this->redis->select((int) ($config['database'] ?? 0));
    }

    protected function k(string $key): string
    {
        return $this->prefix . ':' . $key;
    }

    public function get(string $key): mixed
    {
        $raw = $this->redis->get($this->k($key));

        if ($raw === false) {
            return null;
        }

        // INCRBY ile oluşan sayaçlar düz sayıdır; diğer değerler serialize edilmiştir
        return preg_match('/^-?\d+$/', $raw) === 1 ? (int) $raw : @unserialize($raw, ['allowed_classes' => false]);
    }

    public function put(string $key, mixed $value, int $seconds): void
    {
        $seconds > 0
            ? $this->redis->setex($this->k($key), $seconds, serialize($value))
            : $this->redis->set($this->k($key), serialize($value));
    }

    public function has(string $key): bool
    {
        return (bool) $this->redis->exists($this->k($key));
    }

    public function forget(string $key): void
    {
        $this->redis->del($this->k($key));
    }

    /** Yalnızca bu önekli anahtarları siler (FLUSHDB değil; paylaşılan Redis'e zarar vermez). */
    public function flush(): void
    {
        $it = null;
        while (($keys = $this->redis->scan($it, $this->prefix . ':*', 200)) !== false) {
            if ($keys) {
                $this->redis->del($keys);
            }
            if ($it === 0) {
                break;
            }
        }
    }

    public function increment(string $key, int $by = 1, int $ttl = 0): int
    {
        // Sayaçlar düz tamsayı olarak tutulur (INCRBY atomiktir)
        $k = $this->k($key);
        $value = (int) $this->redis->incrBy($k, $by);
        if ($ttl > 0 && $value === $by) {
            $this->redis->expire($k, $ttl);
        }

        return $value;
    }
}
