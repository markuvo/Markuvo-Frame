<?php

namespace Core\System\Cache;

/**
 * Dosya tabanlı önbellek. Güvenlik: değerler serialize edilir ve geri okunurken nesne sınıfları
 * yüklenmez (allowed_classes=false) - önbelleğe yalnızca skaler/dizi koyun. Yazma atomiktir.
 */
class FileStore implements Store
{
    public function __construct(protected string $directory)
    {
    }

    protected function path(string $key): string
    {
        $hash = sha1($key);

        return rtrim($this->directory, '/\\') . '/' . substr($hash, 0, 2) . '/' . $hash . '.cache';
    }

    protected function read(string $path): ?array
    {
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        $nl = strpos($raw, "\n");
        if ($nl === false) {
            return null;
        }

        $expires = (int) substr($raw, 0, $nl);
        if ($expires !== 0 && $expires <= time()) {
            @unlink($path);

            return null;
        }

        $value = @unserialize(substr($raw, $nl + 1), ['allowed_classes' => false]);

        return [$expires, $value];
    }

    protected function write(string $path, int $expires, mixed $value): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Önbellek klasörü oluşturulamadı: {$dir}");
        }

        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $expires . "\n" . serialize($value)) === false) {
            throw new \RuntimeException("Önbellek dosyası yazılamadı: {$path}");
        }
        rename($tmp, $path); // atomik: okuyan yarım dosya görmez
    }

    public function get(string $key): mixed
    {
        return $this->read($this->path($key))[1] ?? null;
    }

    public function put(string $key, mixed $value, int $seconds): void
    {
        $this->write($this->path($key), $seconds > 0 ? time() + $seconds : 0, $value);
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function forget(string $key): void
    {
        @unlink($this->path($key));
    }

    public function flush(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.cache')) {
                @unlink($file->getPathname());
            }
        }
    }

    /** Dosya kilidiyle eşzamanlı isteklerde sayaç kaybı olmaz. */
    public function increment(string $key, int $by = 1, int $ttl = 0): int
    {
        $path = $this->path($key);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $lock = fopen($path . '.lock', 'c');
        if ($lock === false) {
            throw new \RuntimeException('Önbellek kilidi açılamadı.');
        }

        try {
            flock($lock, LOCK_EX);
            $current = $this->read($path);
            $expires = $current[0] ?? ($ttl > 0 ? time() + $ttl : 0);
            $value = (int) ($current[1] ?? 0) + $by;
            $this->write($path, $expires, $value);

            return $value;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
