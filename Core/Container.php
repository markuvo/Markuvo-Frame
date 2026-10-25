<?php

namespace Core;

use Closure;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

/**
 * Bağımlılık enjeksiyonu (DI) kabı.
 *
 *   $container->singleton(Cache::class, fn($c) => new Cache(...));
 *   $container->make(BlogController::class);             // yapıcıdaki sınıf tipli parametreleri kendisi doldurur
 *   $container->call([$controller, 'detay'], ['id' => 5]); // metot parametrelerini doldurur
 */
class Container
{
    /** @var array<string, array{0: Closure|string|null, 1: bool}> */
    protected array $bindings = [];

    /** @var array<string, object> */
    protected array $instances = [];

    /** @var array<string, string> */
    protected array $aliases = [];

    protected array $resolving = [];

    public function bind(string $abstract, Closure|string|null $concrete = null, bool $shared = false): void
    {
        unset($this->instances[$abstract]);
        $this->bindings[$abstract] = [$concrete ?? $abstract, $shared];
    }

    public function singleton(string $abstract, Closure|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    public function instance(string $abstract, object $instance): object
    {
        $this->instances[$abstract] = $instance;

        return $instance;
    }

    public function alias(string $alias, string $abstract): void
    {
        $this->aliases[$alias] = $abstract;
    }

    public function has(string $abstract): bool
    {
        $abstract = $this->aliases[$abstract] ?? $abstract;

        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    public function make(string $abstract, array $parameters = []): mixed
    {
        $abstract = $this->aliases[$abstract] ?? $abstract;

        if (isset($this->instances[$abstract]) && $parameters === []) {
            return $this->instances[$abstract];
        }

        [$concrete, $shared] = $this->bindings[$abstract] ?? [$abstract, false];

        $object = $concrete instanceof Closure
            ? $concrete($this, $parameters)
            : $this->build($concrete, $parameters);

        if ($shared && $parameters === []) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /** Sınıfı Reflection ile kurar; yapıcı parametrelerini kendisi çözer. */
    protected function build(string $class, array $parameters = []): object
    {
        if (isset($this->resolving[$class])) {
            throw new RuntimeException("Container: döngüsel bağımlılık algılandı ({$class}).");
        }

        if (!class_exists($class)) {
            throw new RuntimeException("Container: sınıf bulunamadı: {$class}");
        }

        $reflection = new \ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Container: {$class} örneklenemez (arayüz ya da soyut sınıf). Bir bağlama (bind) tanımlayın.");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $this->resolving[$class] = true;

        try {
            $arguments = $this->resolveParameters($constructor, $parameters);
        } finally {
            unset($this->resolving[$class]);
        }

        return $reflection->newInstanceArgs($arguments);
    }

    /**
     * Çağrılabilir olanı (Closure, [nesne, 'metot'], 'Sinif::metot') çalıştırır.
     *
     * Parametre çözümleme sırası:
     *  1. Sınıf tipli parametre: Model alt sınıfı ve rota parametresi varsa kayıt veritabanından getirilir,
     *     değilse container'dan üretilir (Request, Validator, kendi sınıflarınız...)
     *  2. Aynı isimli rota parametresi ({id} -> $id)
     *  3. $request adlı tipsiz parametre -> geçerli Request
     *  4. Kalan rota parametreleri sırayla (eski stil: function($request, $id))
     *  5. Varsayılan değer
     */
    public function call(callable|array $callback, array $routeParams = []): mixed
    {
        $reflection = $this->reflectCallable($callback);
        $arguments = $this->resolveParameters($reflection, $routeParams, true);

        return $callback(...$arguments);
    }

    protected function reflectCallable(callable|array $callback): ReflectionFunctionAbstract
    {
        if (is_array($callback)) {
            return new ReflectionMethod($callback[0], $callback[1]);
        }

        if (is_string($callback) && str_contains($callback, '::')) {
            return new ReflectionMethod($callback);
        }

        if (is_object($callback) && !$callback instanceof Closure) {
            return new ReflectionMethod($callback, '__invoke');
        }

        return new ReflectionFunction($callback);
    }

    protected function resolveParameters(ReflectionFunctionAbstract $function, array $values, bool $routeMode = false): array
    {
        $arguments = [];
        $used = [];

        foreach ($function->getParameters() as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();
            $class = $type instanceof ReflectionNamedType && !$type->isBuiltin() ? $type->getName() : null;

            // 1) Sınıf tipli
            if ($class !== null) {
                if ($routeMode && array_key_exists($name, $values) && is_subclass_of($class, Model::class)) {
                    $arguments[] = $class::findOrFail($values[$name]);
                    $used[$name] = true;
                    continue;
                }

                if (array_key_exists($name, $values) && $values[$name] instanceof $class) {
                    $arguments[] = $values[$name];
                    continue;
                }

                if ($this->has($class) || class_exists($class)) {
                    $arguments[] = $this->make($class);
                    continue;
                }
            }

            // 2) İsimle eşleşen değer
            if (array_key_exists($name, $values)) {
                $arguments[] = $this->castValue($values[$name], $type);
                $used[$name] = true;
                continue;
            }

            // 3) $request
            if ($routeMode && $name === 'request' && $class === null) {
                $arguments[] = $this->make(\Core\System\Request::class);
                continue;
            }

            // 4) Kalan rota parametreleri sırayla (eski stil)
            if ($routeMode && $class === null) {
                $next = $this->nextUnused($values, $used);
                if ($next !== null) {
                    $used[$next] = true;
                    $arguments[] = $this->castValue($values[$next], $type);
                    continue;
                }
            }

            // 5) Varsayılan
            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            if ($parameter->allowsNull()) {
                $arguments[] = null;
                continue;
            }

            throw new RuntimeException(sprintf(
                'Container: %s için $%s parametresi çözülemedi.',
                $function instanceof ReflectionMethod
                    ? $function->getDeclaringClass()->getName() . '::' . $function->getName()
                    : 'closure',
                $name
            ));
        }

        return $arguments;
    }

    protected function nextUnused(array $values, array $used): ?string
    {
        foreach ($values as $key => $_) {
            if (is_string($key) && !isset($used[$key])) {
                return $key;
            }
        }

        return null;
    }

    protected function castValue(mixed $value, ?\ReflectionType $type): mixed
    {
        if (!$type instanceof ReflectionNamedType || !$type->isBuiltin() || !is_string($value)) {
            return $value;
        }

        return match ($type->getName()) {
            'int'   => is_numeric($value) ? (int) $value : $value,
            'float' => is_numeric($value) ? (float) $value : $value,
            'bool'  => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => $value,
        };
    }
}
