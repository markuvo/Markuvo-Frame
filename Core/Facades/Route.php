<?php

namespace Core\Facades;

use Core\System\Router;

/** Route::get(...) tanımlar, Route::url('ad') adres üretir. */
class Route
{
    public static function __callStatic(string $method, array $arguments): mixed
    {
        $reflection = new \ReflectionMethod(Router::class, $method);

        return $reflection->isStatic() ? Router::{$method}(...$arguments) : app(Router::class)->{$method}(...$arguments);
    }
}
