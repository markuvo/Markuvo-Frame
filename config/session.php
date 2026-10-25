<?php

return [
    'cookie' => env('SESSION_COOKIE', 'markuvo_session'),

    // Dakika cinsinden oturum ömrü
    'lifetime' => (int) env('SESSION_LIFETIME', 120),

    // null: HTTPS'te otomatik Secure bayrağı
    'secure' => null,

    // Lax: dışarıdan gelen POST isteklerinde cookie gönderilmez (CSRF'e ek savunma)
    'same_site' => 'Lax',
];
