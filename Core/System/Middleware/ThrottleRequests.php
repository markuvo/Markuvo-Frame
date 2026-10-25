<?php

namespace Core\System\Middleware;

use Closure;
use Core\System\HttpException;
use Core\System\RateLimiter;
use Core\System\Request;
use Core\System\Response;

/** Kullanım: ->middleware('throttle:60,1')  => dakikada en fazla 60 istek (IP + adres başına). */
class ThrottleRequests
{
    public function __construct(protected RateLimiter $limiter)
    {
    }

    public function handle(Request $request, Closure $next, $max = 60, $minutes = 1)
    {
        $max = max(1, (int) $max);
        $seconds = max(1, (int) $minutes) * 60;
        $key = $request->ip() . '|' . $request->method() . '|' . $request->getPath();

        $attempts = $this->limiter->hit($key, $seconds);

        if ($attempts > $max) {
            throw new HttpException(429, 'Çok fazla istek gönderdiniz. Lütfen biraz bekleyin.', ['Retry-After' => (string) $seconds]);
        }

        $response = Response::make($next($request));
        $response->header('X-RateLimit-Limit', (string) $max);
        $response->header('X-RateLimit-Remaining', (string) max(0, $max - $attempts));

        return $response;
    }
}
