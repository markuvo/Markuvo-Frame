<?php

return [
    // file | array | redis
    'default' => env('CACHE_DRIVER', 'file'),

    // Anahtar öneki (aynı Redis'i paylaşan projeler çakışmasın)
    'prefix' => env('CACHE_PREFIX', 'markuvo'),

    'stores' => [
        'file' => [],
        'array' => [],
        'redis' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => (int) env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
            'database' => (int) env('REDIS_DB', 0),
        ],
    ],
];
