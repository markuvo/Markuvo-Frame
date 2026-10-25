<?php

return [
    'name' => env('APP_NAME', 'Markuvo'),

    // true: hata ayrıntıları ekranda görünür (YALNIZCA yerel geliştirmede). Canlıda false olmalı.
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN),

    // Tarih/saat işlemleri (created_at vb.) bu saat dilimine göre yazılır
    'timezone' => env('APP_TIMEZONE', 'Europe/Istanbul'),

    // CSRF koruması (POST/PUT/PATCH/DELETE isteklerinde _token doğrulanır)
    'csrf' => true,

    // CSRF dışında tutulacak adresler (örn. harici webhook): ['webhook/*']
    'csrf_except' => [],

    // Her sayfada çalışan middleware'ler
    'middleware' => [
        \Core\System\Middleware\StartSession::class,
        \Core\System\Middleware\VerifyCsrfToken::class,
    ],

    // Rotalarda kullanılan kısa adlar: ->middleware('auth')
    'middleware_aliases' => [
        'auth' => \Core\System\Middleware\Authenticate::class,
        'guest' => \Core\System\Middleware\RedirectIfAuthenticated::class,
        'throttle' => \Core\System\Middleware\ThrottleRequests::class,
    ],

    // Global kısa adlar: use gerekmeden Auth::check(), Cache::get() ... (rotalar ve şablonlarda)
    'aliases' => [
        'Auth' => \Core\Facades\Auth::class,
        'Session' => \Core\Facades\Session::class,
        'Cache' => \Core\Facades\Cache::class,
        'Log' => \Core\Facades\Log::class,
        'Redirect' => \Core\Facades\Redirect::class,
        'Route' => \Core\Facades\Route::class,
        'DB' => \Core\Database\DB::class,
    ],
];
