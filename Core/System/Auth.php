<?php

namespace Core\System;

use Core\Model;

/**
 * Oturum tabanlı kimlik doğrulama.
 *   if (auth()->attempt(['email' => $e, 'password' => $p])) { ... }
 *   auth()->user()->username;   auth()->logout();
 */
class Auth
{
    protected ?Model $user = null;
    protected bool $resolved = false;

    public function __construct(protected Session $session, protected array $config = [])
    {
    }

    protected function modelClass(): string
    {
        return (string) ($this->config['model'] ?? \App\Models\User::class);
    }

    public function id(): mixed
    {
        return $this->session->get('auth.id');
    }

    /** Önbellekteki kullanıcıyı unutur (aynı süreçte yeni istek için). */
    public function forgetUser(): void
    {
        $this->user = null;
        $this->resolved = false;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function user(): ?Model
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;
        $id = $this->id();

        if ($id !== null) {
            $class = $this->modelClass();
            $this->user = $class::find($id);

            // Kullanıcı silindiyse oturumu temizle
            if ($this->user === null) {
                $this->session->forget('auth');
            }
        }

        return $this->user;
    }

    /**
     * Kimlik bilgilerini doğrular ve girişi yapar.
     * Güvenlik: kullanıcı yokken de aynı maliyette hash doğrulaması yapılır (zamanlama farkıyla
     * "bu e-posta kayıtlı mı" bilgisi sızmaz).
     */
    public function attempt(array $credentials): bool
    {
        $identifier = (string) ($this->config['identifier'] ?? 'email');
        $passwordColumn = (string) ($this->config['password'] ?? 'password');

        $plain = (string) ($credentials[$passwordColumn] ?? '');
        $login = $credentials[$identifier] ?? null;

        if ($login === null || $login === '' || $plain === '') {
            return false;
        }

        $class = $this->modelClass();
        $user = $class::where($identifier, '=', $login)->first();

        $hash = $user ? (string) $user->getAttributes()[$passwordColumn] : self::dummyHash();
        $valid = password_verify($plain, $hash);

        if (!$user || !$valid) {
            return false;
        }

        // Algoritma/maliyet güncellendiyse hash'i yenile
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $user->forceFill([$passwordColumn => password_hash($plain, PASSWORD_DEFAULT)])->save();
        }

        $this->login($user);

        return true;
    }

    public function login(Model $user): void
    {
        $this->session->regenerate();      // oturum sabitleme saldırısına karşı
        $this->session->put('auth.id', $user->getKey());
        $this->session->put('_token', bin2hex(random_bytes(32))); // CSRF token'ı da yenile
        $this->user = $user;
        $this->resolved = true;
    }

    public function logout(): void
    {
        $this->session->forget('auth');
        $this->session->forget('_token');
        $this->session->regenerate();
        $this->user = null;
        $this->resolved = true;
    }

    protected static function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= password_hash('markuvo-dummy', PASSWORD_DEFAULT);
    }
}
