<?php

namespace Core\System;

class Route
{
    public ?string $name = null;
    public array $wheres = [];
    protected ?string $regex = null;

    public function __construct(
        public array $methods,
        public string $uri,
        public mixed $action,
        public array $middleware = []
    ) {
    }

    public function name(string $name): static
    {
        $this->name = $name;
        Router::registerName($name, $this);

        return $this;
    }

    public function middleware(string|array ...$middleware): static
    {
        foreach ($middleware as $m) {
            foreach ((array) $m as $one) {
                $this->middleware[] = $one;
            }
        }

        return $this;
    }

    /** where('id', '\d+') ya da where(['id' => '\d+']) */
    public function where(string|array $name, ?string $pattern = null): static
    {
        $this->wheres = array_merge($this->wheres, is_array($name) ? $name : [$name => $pattern]);
        $this->regex = null;

        return $this;
    }

    /** @return array<string,string>|null eşleşirse rota parametreleri */
    public function match(string $path): ?array
    {
        if (preg_match($this->regex(), $path, $m) !== 1) {
            return null;
        }

        $params = [];
        foreach ($m as $key => $value) {
            if (is_string($key) && $value !== '') {
                $params[$key] = rawurldecode($value);
            }
        }

        return $params;
    }

    protected function regex(): string
    {
        if ($this->regex !== null) {
            return $this->regex;
        }

        // /a/{id}/b/:slug  ->  /a/(?P<id>...)/b/(?P<slug>...)
        $pattern = preg_replace_callback('/\{(\w+)(\?)?\}|:(\w+)/', function ($m) {
            $name = $m[1] !== '' ? $m[1] : $m[3];
            $constraint = $this->wheres[$name] ?? '[^/]+';
            $optional = ($m[2] ?? '') === '?';

            return $optional ? "(?:(?P<{$name}>{$constraint}))?" : "(?P<{$name}>{$constraint})";
        }, preg_quote_keep($this->uri));

        return $this->regex = '#^' . $pattern . '$#u';
    }

    public function parameterNames(): array
    {
        preg_match_all('/\{(\w+)\??\}|:(\w+)/', $this->uri, $m, PREG_SET_ORDER);

        return array_map(fn($x) => $x[1] !== '' ? $x[1] : $x[2], $m);
    }
}

/** Yol içindeki { } : ? karakterlerini koruyarak diğer regex özel karakterlerini kaçırır. */
function preg_quote_keep(string $uri): string
{
    return preg_replace_callback('/[.\\\\+*\[\]^$()=!<>|#\-]/', fn($m) => '\\' . $m[0], $uri);
}
