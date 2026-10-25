<?php

namespace Core;

use Core\System\Auth;
use Core\System\Cache\CacheManager;
use Core\System\ErrorHandler;
use Core\System\Logger;
use Core\System\RateLimiter;
use Core\System\Redirector;
use Core\System\Request;
use Core\System\Response;
use Core\System\Router;
use Core\System\Session;

/**
 * Uygulama çekirdeği: .env + config yükler, servisleri container'a kaydeder,
 * isteği Router'a verir ve yanıtı gönderir.
 *
 *   $app = Application::boot(dirname(__DIR__));
 *   require 'routes/web.php';
 *   $app->run();
 */
class Application
{
    protected static ?self $instance = null;

    protected Container $container;
    protected Config $config;
    protected string $basePath;
    protected ?string $storagePath;
    protected bool $console;

    /**
     * Seçenekler:
     *  console       true ise web hata yakalayıcı kurulmaz (CLI ve testler)
     *  load_env      false ise .env okunmaz
     *  storage_path  storage klasörünü değiştirir (testler için)
     */
    public function __construct(string $basePath, array $options = [])
    {
        $this->basePath = rtrim($basePath, '/\\');
        $this->console = (bool) ($options['console'] ?? false);
        $this->storagePath = $options['storage_path'] ?? null;

        $this->container = new Container();
        $this->config = new Config();
        static::$instance = $this;

        $this->container->instance(self::class, $this);
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(Config::class, $this->config);
        $this->container->alias('config', Config::class);

        // .env hatası da yakalansın diye hata yakalayıcı en başta kurulur
        if (!$this->console) {
            ErrorHandler::register();
        }

        if ($options['load_env'] ?? true) {
            $this->loadEnvironment();
        }

        $this->config->load($this->basePath . '/config');

        $timezone = $this->config->get('app.timezone');
        if (is_string($timezone) && $timezone !== '') {
            date_default_timezone_set($timezone);
        }

        $this->registerServices();
        $this->registerAliases();
    }

    public static function boot(string $basePath, array $options = []): static
    {
        return new static($basePath, $options);
    }

    public static function getInstance(): ?static
    {
        return static::$instance;
    }

    // ---------------------------------------------------------------------
    // Başlatma
    // ---------------------------------------------------------------------

    protected function loadEnvironment(): void
    {
        $file = $this->basePath . '/.env';

        if (is_file($file)) {
            (new DotEnv($file))->load();
        }
    }

    protected function registerServices(): void
    {
        $c = $this->container;

        $c->singleton(Logger::class, function () {
            return new Logger(
                $this->storagePath('logs'),
                'app',
                filter_var($this->config->get('log.daily', false), FILTER_VALIDATE_BOOLEAN),
                (string) $this->config->get('log.level', 'debug')
            );
        });
        $c->alias('log', Logger::class);

        $c->singleton(Request::class, fn() => Request::capture());
        $c->alias('request', Request::class);

        $c->singleton(Session::class, fn() => new Session((array) $this->config->get('session', [])));
        $c->alias('session', Session::class);

        $c->singleton(Auth::class, fn($c) => new Auth($c->make(Session::class), (array) $this->config->get('auth', [])));
        $c->alias('auth', Auth::class);

        $c->singleton(CacheManager::class, fn() => new CacheManager((array) $this->config->get('cache', []), $this->storagePath('cache/data')));
        $c->alias('cache', CacheManager::class);

        $c->singleton(RateLimiter::class, fn($c) => new RateLimiter($c->make(CacheManager::class)));
        $c->alias('ratelimiter', RateLimiter::class);

        $c->singleton(Router::class, fn($c) => new Router($c));
        $c->alias('router', Router::class);

        $c->singleton(Redirector::class, fn($c) => new Redirector($c->make(Session::class)));
        $c->alias('redirect', Redirector::class);
    }

    /** config/app.php içindeki 'aliases' listesini global sınıf takma adı yapar (Auth::check() gibi). */
    protected function registerAliases(): void
    {
        foreach ((array) $this->config->get('app.aliases', []) as $alias => $class) {
            if (!class_exists($alias, false) && class_exists($class)) {
                class_alias($class, $alias);
            }
        }
    }

    // ---------------------------------------------------------------------
    // Çalıştırma
    // ---------------------------------------------------------------------

    /** İsteği işler ve yanıtı gönderir. */
    public function run(): void
    {
        $this->handle($this->container->make(Request::class))->send();
    }

    /** İsteği Router'a verir; hataları uygun yanıta çevirir. Yanıtı göndermez. */
    public function handle(Request $request): Response
    {
        $this->container->instance(Request::class, $request);

        // CLI/test gibi tek süreçte birden çok istek işlendiğinde oturum ve kullanıcı durumu yenilenir
        if ($this->console) {
            $this->container->make(Session::class)->restart();
            $this->container->make(Auth::class)->forgetUser();
        }

        try {
            return $this->container->make(Router::class)->dispatch($request);
        } catch (\Throwable $e) {
            return ErrorHandler::render($e, $request);
        }
    }

    // ---------------------------------------------------------------------
    // Erişim
    // ---------------------------------------------------------------------

    public function make(string $abstract, array $parameters = []): mixed
    {
        return $this->container->make($abstract, $parameters);
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function isConsole(): bool
    {
        return $this->console;
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
    }

    public function storagePath(string $path = ''): string
    {
        $base = $this->storagePath ?? $this->basePath('storage');

        return rtrim($base, '/\\') . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
    }
}
