<?php

namespace Core\System\Middleware;

use Closure;
use Core\System\HttpException;
use Core\System\Request;
use Core\System\Session;

/** POST/PUT/PATCH/DELETE isteklerinde _token alanını (ya da X-CSRF-TOKEN başlığını) doğrular. */
class VerifyCsrfToken
{
    public function __construct(protected Session $session)
    {
    }

    public function handle(Request $request, Closure $next, ...$args)
    {
        if (config('app.csrf', true) && !in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) && !$this->excluded($request)) {
            $sent = (string) ($request->post('_token') ?? $request->header('X-CSRF-TOKEN', ''));
            $real = $this->session->token();

            if ($sent === '' || !hash_equals($real, $sent)) {
                throw new HttpException(419, 'Sayfanın süresi doldu. Lütfen sayfayı yenileyip tekrar deneyin.');
            }
        }

        return $next($request);
    }

    protected function excluded(Request $request): bool
    {
        $path = trim($request->getPath(), '/');

        foreach ((array) config('app.csrf_except', []) as $pattern) {
            if (fnmatch(trim((string) $pattern, '/'), $path)) {
                return true;
            }
        }

        return false;
    }
}
