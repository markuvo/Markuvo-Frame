<?php

use Core\Application;
use Core\System\Auth;
use Core\System\Cache\CacheManager;
use Core\System\HttpException;
use Core\System\Logger;
use Core\System\Redirector;
use Core\System\Request;
use Core\System\Response;
use Core\System\Router;
use Core\System\Session;

// .env dosyasından (ya da sistem ortamından) değer okur
if (!function_exists('env')) {
    function env(string $key, $default = null) {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        return $value;
    }
}

// Uygulama nesnesi ya da container'dan servis: app() / app(Request::class)
if (!function_exists('app')) {
    function app(?string $abstract = null, array $parameters = []) {
        $app = Application::getInstance();

        if ($app === null) {
            throw new RuntimeException('Uygulama henüz başlatılmadı (Application::boot).');
        }

        return $abstract === null ? $app : $app->make($abstract, $parameters);
    }
}

// config('app.debug') okur; config(['app.debug' => true]) yazar
if (!function_exists('config')) {
    function config(string|array|null $key = null, $default = null) {
        $app = Application::getInstance();

        if ($app === null) {
            return is_string($key) ? $default : null;
        }

        if ($key === null) {
            return $app->config();
        }

        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $app->config()->set($k, $v);
            }
            return null;
        }

        return $app->config()->get($key, $default);
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string {
        return app()->basePath($path);
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string {
        return app()->storagePath($path);
    }
}

if (!function_exists('request')) {
    function request(?string $key = null, $default = null) {
        $request = app(Request::class);

        return $key === null ? $request : $request->input($key, $default);
    }
}

// Alt klasörde çalışan kurulumlarda doğru adresi üretir: url('/login')
if (!function_exists('url')) {
    function url(string $path = ''): string {
        if (preg_match('#^([a-z][a-z0-9+.\-]*:|//)#i', $path)) {
            return $path; // zaten tam adres
        }

        $base = Application::getInstance() ? request()->getBasePath() : '';

        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string {
        return url($path);
    }
}

if (!function_exists('route')) {
    function route(string $name, array $parameters = []): string {
        return app(Router::class)->url($name, $parameters);
    }
}

// Yönlendirme yanıtı döner: return redirect('/login');
if (!function_exists('redirect')) {
    function redirect(string $url, int $status = 302): Response {
        return app(Redirector::class)->to($url, $status);
    }
}

if (!function_exists('back')) {
    function back(string $fallback = '/'): Response {
        return app(Redirector::class)->back($fallback);
    }
}

if (!function_exists('view')) {
    function view(string $view, array $data = []): string {
        return \Core\View::render($view, $data);
    }
}

if (!function_exists('session')) {
    function session(string|array|null $key = null, $default = null) {
        $session = app(Session::class);

        if ($key === null) {
            return $session;
        }

        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $session->put($k, $v);
            }
            return null;
        }

        return $session->get($key, $default);
    }
}

if (!function_exists('auth')) {
    function auth(): Auth {
        return app(Auth::class);
    }
}

if (!function_exists('cache')) {
    function cache(?string $key = null, $default = null) {
        $cache = app(CacheManager::class);

        return $key === null ? $cache : $cache->get($key, $default);
    }
}

if (!function_exists('logger')) {
    function logger(?string $channel = null): Logger {
        $logger = app(Logger::class);

        return $channel === null ? $logger : $logger->channel($channel);
    }
}

// HTML çıktısı için kaçışlama (XSS koruması): e($kullaniciGirdisi)
if (!function_exists('e')) {
    function e($value): string {
        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('abort')) {
    function abort(int $status, string $message = '', array $headers = []): never {
        throw new HttpException($status, $message, $headers);
    }
}

// Güvenlik için CSRF Token üreten fonksiyon
if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        return app(Session::class)->token();
    }
}

// Formların içine gizli CSRF inputu basan fonksiyon
if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

// PUT/PATCH/DELETE taklidi için gizli input: <form method="POST"> + method_field('DELETE')
if (!function_exists('method_field')) {
    function method_field(string $method): string {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

// Doğrulama hatasından sonra formdaki eski değer
if (!function_exists('old')) {
    function old(string $key, $default = '') {
        return app(Session::class)->getFlash('old.' . $key, $default);
    }
}

if (!function_exists('has_error')) {
    function has_error(string $field): bool {
        return app(Session::class)->getFlash('errors.' . $field) !== null;
    }
}

// Bir alanın ilk doğrulama hatası mesajı
if (!function_exists('error')) {
    function error(string $field): string {
        $messages = app(Session::class)->getFlash('errors.' . $field);

        return is_array($messages) ? (string) ($messages[0] ?? '') : '';
    }
}
