<?php

namespace Core\System\Middleware;

use Closure;
use Core\System\Auth;
use Core\System\Redirector;
use Core\System\Request;

class RedirectIfAuthenticated
{
    public function __construct(protected Auth $auth, protected Redirector $redirector)
    {
    }

    public function handle(Request $request, Closure $next, ...$args)
    {
        if ($this->auth->check()) {
            return $this->redirector->to((string) config('auth.home_url', '/'));
        }

        return $next($request);
    }
}
