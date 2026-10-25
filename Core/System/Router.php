<?php

namespace Core\System;

use Closure;
use Core\Container;

/**
 * Rota tanımları (statik) ve istek yönlendirme (örnek).
 *
 *   Router::get('/blog/{id}', [BlogController::class, 'show'])->name('blog.show')->where('id', '\d+');
 *   Router::post('/login', 'AuthController@login');
 *   Router::prefix('/admin')->middleware('auth')->group(function () { ... });
 */
class Router
{
    /** @var Route[] */
    protected static array $routes = [];
    protected static array $named = [];
    protected static array $groupStack = [];

    public function __construct(protected Container $container)
    {
    }

    // ------------------------------------------------------------------ tanımlama

    public static function get(string $uri, mixed $action): Route { return static::add(['GET', 'HEAD'], $uri, $action); }
    public static function post(string $uri, mixed $action): Route { return static::add(['POST'], $uri, $action); }
    public static function put(string $uri, mixed $action): Route { return static::add(['PUT'], $uri, $action); }
    public static function patch(string $uri, mixed $action): Route { return static::add(['PATCH'], $uri, $action); }
    public static function delete(string $uri, mixed $action): Route { return static::add(['DELETE'], $uri, $action); }

    public static function any(string $uri, mixed $action): Route
    {
        return static::add(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], $uri, $action);
    }

    public static function match(array $methods, string $uri, mixed $action): Route
    {
        return static::add(array_map('strtoupper', $methods), $uri, $action);
    }

    public static function prefix(string $prefix): RouteGroup
    {
        return (new RouteGroup())->prefix($prefix);
    }

    public static function middleware(string|array ...$middleware): RouteGroup
    {
        return (new RouteGroup())->middleware(...$middleware);
    }

    /** Router::group(['prefix' => '/admin', 'middleware' => ['auth']], function () { ... }) */
    public static function group(array $attributes, Closure $routes): void
    {
        $parent = end(static::$groupStack) ?: ['prefix' => '', 'middleware' => [], 'name' => ''];

        static::$groupStack[] = [
            'prefix' => $parent['prefix'] . (isset($attributes['prefix']) ? '/' . trim($attributes['prefix'], '/') : ''),
            'middleware' => array_merge($parent['middleware'], (array) ($attributes['middleware'] ?? [])),
            'name' => $parent['name'] . ($attributes['name'] ?? ''),
        ];

        try {
            $routes();
        } finally {
            array_pop(static::$groupStack);
        }
    }

    protected static function add(array $methods, string $uri, mixed $action): Route
    {
        $group = end(static::$groupStack) ?: ['prefix' => '', 'middleware' => [], 'name' => ''];
        $full = '/' . trim(($group['prefix'] !== '' ? $group['prefix'] : '') . '/' . trim($uri, '/'), '/');
        $full = preg_replace('#/+#', '/', $full);

        $route = new Route($methods, $full, $action, $group['middleware']);
        static::$routes[] = $route;

        return $route;
    }

    public static function registerName(string $name, Route $route): void
    {
        $prefix = (end(static::$groupStack) ?: ['name' => ''])['name'];
        static::$named[$prefix . $name] = $route;
        $route->name = $prefix . $name;
    }

    public static function flush(): void
    {
        static::$routes = [];
        static::$named = [];
        static::$groupStack = [];
    }

    /** @return Route[] */
    public static function getRoutes(): array
    {
        return static::$routes;
    }

    // ------------------------------------------------------------------ URL üretme

    /** route('blog.show', ['id' => 5, 'sayfa' => 2]) -> /blog/5?sayfa=2 */
    public function url(string $name, array $parameters = []): string
    {
        $route = static::$named[$name] ?? null;
        if ($route === null) {
            throw new \InvalidArgumentException("'{$name}' adında bir rota yok.");
        }

        $used = [];
        $uri = preg_replace_callback('/\{(\w+)(\?)?\}|:(\w+)/', function ($m) use (&$parameters, &$used, $name) {
            $key = $m[1] !== '' ? $m[1] : $m[3];
            if (array_key_exists($key, $parameters)) {
                $used[] = $key;

                return rawurlencode((string) $parameters[$key]);
            }
            if (($m[2] ?? '') === '?') {
                return '';
            }
            throw new \InvalidArgumentException("'{$name}' rotası için '{$key}' parametresi eksik.");
        }, $route->uri);

        $query = array_diff_key($parameters, array_flip($used));
        $base = $this->container->has(Request::class) ? $this->container->make(Request::class)->getBasePath() : '';

        return $base . rtrim($uri, '/') . ($query ? '?' . http_build_query($query) : '');
    }

    // ------------------------------------------------------------------ yönlendirme

    public function dispatch(Request $request): Response
    {
        $path = $request->getPath();
        $method = $request->method();
        $allowed = [];

        foreach (static::$routes as $route) {
            $params = $route->match($path);
            if ($params === null) {
                continue;
            }

            if (in_array($method, $route->methods, true)) {
                return $this->run($route, $request->setRouteParams($params), $params);
            }

            $allowed = array_merge($allowed, $route->methods);
        }

        if ($allowed !== []) {
            throw new HttpException(405, 'Bu adres için bu yöntem desteklenmiyor.', ['Allow' => implode(', ', array_unique($allowed))]);
        }

        throw new HttpException(404, 'Sayfa bulunamadı.');
    }

    protected function run(Route $route, Request $request, array $params): Response
    {
        $global = (array) config('app.middleware', []);
        $stack = array_merge($global, $route->middleware);

        $core = function (Request $request) use ($route, $params) {
            return Response::make($this->invoke($route->action, $params));
        };

        $pipeline = array_reduce(
            array_reverse($stack),
            fn(Closure $next, $middleware) => function (Request $request) use ($middleware, $next) {
                [$instance, $args] = $this->resolveMiddleware($middleware);

                return Response::make($instance->handle($request, $next, ...$args));
            },
            $core
        );

        return Response::make($pipeline($request));
    }

    /** 'auth', 'throttle:60,1' ya da sınıf adı -> [nesne, ek parametreler] */
    protected function resolveMiddleware(string|object $middleware): array
    {
        if (is_object($middleware)) {
            return [$middleware, []];
        }

        $args = [];
        if (str_contains($middleware, ':')) {
            [$middleware, $rest] = explode(':', $middleware, 2);
            $args = explode(',', $rest);
        }

        $aliases = (array) config('app.middleware_aliases', []);
        $class = $aliases[$middleware] ?? $middleware;

        if (!class_exists($class)) {
            throw new \RuntimeException("Middleware bulunamadı: {$middleware}");
        }

        return [$this->container->make($class), $args];
    }

    protected function invoke(mixed $action, array $params): mixed
    {
        if (is_string($action) && str_contains($action, '@')) {
            [$class, $method] = explode('@', $action, 2);
            $action = [$class[0] === '\\' || class_exists($class) ? $class : 'App\\Controllers\\' . $class, $method];
        }

        if (is_array($action) && is_string($action[0])) {
            if (!class_exists($action[0])) {
                throw new \RuntimeException("Controller sınıfı bulunamadı: {$action[0]}");
            }
            $action[0] = $this->container->make($action[0]);
        }

        if (is_array($action) && !method_exists($action[0], $action[1])) {
            throw new \RuntimeException(get_class($action[0]) . '::' . $action[1] . '() metodu bulunamadı.');
        }

        return $this->container->call($action, $params);
    }
}
