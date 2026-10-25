<?php

namespace Core\System\Middleware;

use Closure;
use Core\System\Request;
use Core\System\Session;

class StartSession
{
    public function __construct(protected Session $session)
    {
    }

    public function handle(Request $request, Closure $next, ...$args)
    {
        $this->session->start();

        return $next($request);
    }
}
