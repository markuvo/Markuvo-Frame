<?php

namespace Core\System;

use Closure;

/** Router::prefix('/admin')->middleware('auth')->group(function () { ... }); */
class RouteGroup
{
    public function __construct(protected array $attributes = [])
    {
    }

    public function prefix(string $prefix): static
    {
        $this->attributes['prefix'] = ($this->attributes['prefix'] ?? '') . '/' . trim($prefix, '/');

        return $this;
    }

    public function middleware(string|array ...$middleware): static
    {
        $list = $this->attributes['middleware'] ?? [];
        foreach ($middleware as $m) {
            foreach ((array) $m as $one) {
                $list[] = $one;
            }
        }
        $this->attributes['middleware'] = $list;

        return $this;
    }

    /** Grup içindeki rota adlarının önüne eklenir: name('admin.') */
    public function name(string $prefix): static
    {
        $this->attributes['name'] = ($this->attributes['name'] ?? '') . $prefix;

        return $this;
    }

    public function group(Closure $routes): void
    {
        Router::group($this->attributes, $routes);
    }
}
