<?php

namespace Core\System;

use Core\Support\Arr;

/**
 * Oturum + flash verisi. Web'de PHP oturumunu güvenli çerez ayarlarıyla başlatır;
 * CLI/testlerde dizi üzerinde çalışır.
 */
class Session
{
    protected array $data = [];
    protected bool $started = false;

    public function __construct(protected array $config = [])
    {
    }

    public function start(): void
    {
        if ($this->started) {
            return;
        }

        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
            $secure = $this->config['secure'] ?? null;
            $secure = $secure === null ? (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') : (bool) $secure;

            session_name((string) ($this->config['cookie'] ?? 'markuvo_session'));
            session_set_cookie_params([
                'lifetime' => ((int) ($this->config['lifetime'] ?? 120)) * 60,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => (string) ($this->config['same_site'] ?? 'Lax'),
            ]);
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            session_start();
            $this->data = &$_SESSION;
        }

        // Flash dönüşümü: önceki isteğin "new" verisi bu istekte "old" olur
        $this->data['_flash']['old'] = $this->data['_flash']['new'] ?? [];
        $this->data['_flash']['new'] = [];
        $this->started = true;
    }

    /** Aynı süreçte yeni bir istek başlatır (flash döner). Yalnızca CLI/test içindir. */
    public function restart(): void
    {
        $this->started = false;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->data, $key, $default);
    }

    public function put(string $key, mixed $value): void
    {
        Arr::set($this->data, $key, $value);
    }

    public function has(string $key): bool
    {
        return Arr::get($this->data, $key) !== null;
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    public function all(): array
    {
        return $this->data;
    }

    /** Yalnızca bir sonraki istekte okunabilen veri. */
    public function flash(string $key, mixed $value): void
    {
        $this->data['_flash']['new'][$key] = $value;
    }

    /** Flash verisini (bu istekte ya da önceki istekten) okur; nokta gösterimi destekler: old.email */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        $new = Arr::get($this->data['_flash']['new'] ?? [], $key);
        if ($new !== null) {
            return $new;
        }

        return Arr::get($this->data['_flash']['old'] ?? [], $key, $default);
    }

    public function token(): string
    {
        if (!isset($this->data['_token']) || !is_string($this->data['_token'])) {
            $this->data['_token'] = bin2hex(random_bytes(32));
        }

        return $this->data['_token'];
    }

    /** Oturum sabitleme saldırısına karşı: giriş/çıkışta kimliği yenile. */
    public function regenerate(): void
    {
        if ($this->started && PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function flush(): void
    {
        $this->data = [];
        if ($this->started && isset($_SESSION) && PHP_SAPI !== 'cli') {
            $_SESSION = [];
            $this->data = &$_SESSION;
        }
    }
}
