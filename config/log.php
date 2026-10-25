<?php

return [
    // true: her gün ayrı dosya (app-2026-10-24.log)
    'daily' => filter_var(env('LOG_DAILY', false), FILTER_VALIDATE_BOOLEAN),

    // debug | info | notice | warning | error | critical
    'level' => env('LOG_LEVEL', 'debug'),
];
