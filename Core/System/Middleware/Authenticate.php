<?php

namespace Core\System\Middleware;

use Closure;
use Core\System\Auth;
use Core\System\HttpException;
use Core\System\Redirector;
use Core\System\Request;
use Core\System\Session;

class Authenticate
{
    public function __construct(protected Auth $auth, protected Redirector $redirector, protected Session $session)
    {
    }

    public function handle(Request $request, Closure $next, ...$args)
    {
        if ($this->auth->check()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            throw new HttpException(401, 'Giriş yapmanız gerekiyor.');
        }

        if ($request->isMethod('GET')) {
            $this->session->put('url.intended', $request->getPath());
        }

        return $this->redirector->to((string) config('auth.login_url', '/login'));
    }
}
