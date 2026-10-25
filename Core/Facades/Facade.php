<?php

namespace Core\Facades;

/** Container'daki bir servise statik kapı: Auth::check() => app('auth')->check() */
abstract class Facade
{
    abstract protected static function accessor(): string;

    public static function __callStatic(string $method, array $arguments): mixed
    {
        return app(static::accessor())->{$method}(...$arguments);
    }
}
