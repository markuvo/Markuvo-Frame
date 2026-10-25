<?php

namespace Core\System\Cache;

use Closure;

/**
 *   cache()->put('anahtar', $deger, 600);   // 10 dk
 *   cache('anahtar');                        // oku
 *   $liste = Cache::remember('liste', 300, fn() => Blog::all()->toArray());
 *   Cache::store('redis')->get('x');
 */
class CacheManager
{
    protected array $stores = [];

    public function __construct(protected array $config, protected string $directory)
    {
    }

    public function store(?string $name = null): Store
    {
        $name ??= (string) ($this->config['default'] ?? 'file');

        return $this->stores[$name] ??= $this->create($name);
    }

    protected function create(string $name): Store
    {
        return match ($name) {
            'file' => new FileStore($this->directory),
            'array' => new ArrayStore(),
            'redis' => new RedisStore((array) ($this->config['stores']['redis'] ?? []), (string) ($this->config['prefix'] ?? 'markuvo')),
            default => throw new \InvalidArgumentException("Bilinmeyen önbellek sürücüsü: {$name} (file, array, redis)"),
        };
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->store()->get($key);

        return $value === null ? ($default instanceof Closure ? $default() : $default) : $value;
    }

    /** $seconds = 0 süresizdir. */
    public function put(string $key, mixed $value, int $seconds = 3600): void
    {
        $this->store()->put($key, $value, $seconds);
    }

    public function forever(string $key, mixed $value): void
    {
        $this->store()->put($key, $value, 0);
    }

    /** Yoksa yazar; yazdıysa true döner. */
    public function add(string $key, mixed $value, int $seconds = 3600): bool
    {
        if ($this->store()->has($key)) {
            return false;
        }
        $this->store()->put($key, $value, $seconds);

        return true;
    }

    public function has(string $key): bool
    {
        return $this->store()->has($key);
    }

    public function forget(string $key): void
    {
        $this->store()->forget($key);
    }

    public function flush(): void
    {
        $this->store()->flush();
    }

    public function increment(string $key, int $by = 1, int $ttl = 0): int
    {
        return $this->store()->increment($key, $by, $ttl);
    }

    public function remember(string $key, int $seconds, Closure $callback): mixed
    {
        $value = $this->store()->get($key);
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->store()->put($key, $value, $seconds);

        return $value;
    }
}
