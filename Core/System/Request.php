<?php

namespace Core\System;

/**
 * HTTP isteği. Girdi olduğu gibi (ham) tutulur; güvenlik çıkışta kaçışlamayla
 * (e()) ve sorgularda hazırlanmış ifadelerle sağlanır, girişte veri bozulmaz.
 */
class Request
{
    protected array $routeParams = [];
    protected ?array $json = null;

    public function __construct(
        protected array $query = [],
        protected array $post = [],
        protected array $files = [],
        protected array $server = [],
        protected array $cookies = [],
        protected ?string $content = null
    ) {
    }

    public static function capture(): static
    {
        return new static($_GET, $_POST, $_FILES, $_SERVER, $_COOKIE);
    }

    // ---------------------------------------------------------------- Adres / yöntem

    /** Gerçek HTTP yöntemi; yalnızca POST isteğinde _method ile PUT/PATCH/DELETE taklidi yapılabilir. */
    public function method(): string
    {
        $method = strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));

        if ($method === 'POST') {
            $spoof = strtoupper((string) ($this->post['_method'] ?? $this->header('X-HTTP-Method-Override', '')));
            if (in_array($spoof, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $spoof;
            }
        }

        return $method;
    }

    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    /** Uygulamanın kurulu olduğu alt klasör: /proje (kökte çalışıyorsa boş). */
    public function getBasePath(): string
    {
        $script = str_replace('\\', '/', (string) ($this->server['SCRIPT_NAME'] ?? ''));
        $uriPath = $this->rawPath();
        $dir = rtrim(dirname($script), '/');

        if ($dir === '' || $dir === '.') {
            return '';
        }

        // public/index.php kullanılıyorsa (kök .htaccess yönlendirmesi) public'i at
        if ($uriPath !== $dir && !str_starts_with($uriPath, $dir . '/')) {
            $dir = rtrim(dirname($dir), '/');
            if ($dir === '.' || $dir === '\\') {
                $dir = '';
            }
        }

        return $dir;
    }

    protected function rawPath(): string
    {
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);

        return is_string($path) ? rawurldecode($path) : '/';
    }

    /** Alt klasör çıkarılmış yol: /user/5 */
    public function getPath(): string
    {
        $path = $this->rawPath();
        $base = $this->getBasePath();

        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        $path = '/' . trim($path, '/');

        // /public/index.php ya da /index.php ile gelenleri normalleştir
        $path = preg_replace('#^/(public/)?index\.php#', '', $path) ?: '/';

        return $path === '' ? '/' : $path;
    }

    public function fullUrl(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host = (string) ($this->server['HTTP_HOST'] ?? 'localhost');
        $qs = (string) ($this->server['QUERY_STRING'] ?? '');

        return $scheme . '://' . $host . $this->getBasePath() . $this->getPath() . ($qs !== '' ? '?' . $qs : '');
    }

    public function isSecure(): bool
    {
        $https = $this->server['HTTPS'] ?? '';

        return ($https !== '' && strtolower((string) $https) !== 'off') || ($this->server['SERVER_PORT'] ?? null) == 443;
    }

    // ---------------------------------------------------------------- Girdi

    public function query(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->query : ($this->query[$key] ?? $default);
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->post : ($this->post[$key] ?? $default);
    }

    /** JSON gövdesi (Content-Type: application/json) dizi olarak. */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($this->json === null) {
            $this->json = [];
            if ($this->isJson()) {
                $decoded = json_decode($this->body(), true);
                $this->json = is_array($decoded) ? $decoded : [];
            }
        }

        return $key === null ? $this->json : \Core\Support\Arr::get($this->json, $key, $default);
    }

    public function body(): string
    {
        return $this->content ??= (string) file_get_contents('php://input');
    }

    /** GET + POST (+ JSON) birleşimi; POST önceliklidir. */
    public function all(): array
    {
        return array_replace($this->query, $this->json(), $this->post);
    }

    public function input(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->all() : \Core\Support\Arr::get($this->all(), $key, $default);
    }

    public function only(array|string ...$keys): array
    {
        return \Core\Support\Arr::only($this->all(), is_array($keys[0] ?? null) ? $keys[0] : $keys);
    }

    public function except(array|string ...$keys): array
    {
        return \Core\Support\Arr::except($this->all(), is_array($keys[0] ?? null) ? $keys[0] : $keys);
    }

    public function has(string ...$keys): bool
    {
        foreach ($keys as $key) {
            if (!\Core\Support\Arr::has($this->all(), $key)) {
                return false;
            }
        }

        return true;
    }

    /** Anahtar var ve boş değil mi? */
    public function filled(string $key): bool
    {
        $v = $this->input($key);

        return !($v === null || $v === '' || $v === []);
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    // ---------------------------------------------------------------- Başlık / istemci

    public function header(string $name, mixed $default = null): mixed
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        if (isset($this->server[$key])) {
            return $this->server[$key];
        }

        $special = strtoupper(str_replace('-', '_', $name));

        return in_array($special, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) ? ($this->server[$special] ?? $default) : $default;
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return (string) $this->header('User-Agent', '');
    }

    public function isJson(): bool
    {
        return str_contains(strtolower((string) $this->header('Content-Type', '')), 'json');
    }

    public function expectsJson(): bool
    {
        $accept = strtolower((string) $this->header('Accept', ''));

        return $this->isJson()
            || strtolower((string) $this->header('X-Requested-With', '')) === 'xmlhttprequest'
            || str_contains($accept, 'application/json')
            || str_starts_with($this->getPath(), '/api/');
    }

    /** Girdiyi doğrular; hata varsa ValidationException (Application otomatik yönlendirir / JSON 422 döner). */
    public function validate(array $rules, array $messages = [], array $attributes = []): array
    {
        return Validator::make($this->all(), $rules, $messages, $attributes)->validate();
    }

    // ---------------------------------------------------------------- Rota parametreleri

    public function setRouteParams(array $params): static
    {
        $this->routeParams = $params;

        return $this;
    }

    public function route(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->routeParams : ($this->routeParams[$key] ?? $default);
    }
}
