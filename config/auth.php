<?php

return [
    // Kullanıcıların tutulduğu model
    'model' => \App\Models\User::class,

    // Giriş kimliği ve şifre kolonu (users tablosu)
    'identifier' => 'email',
    'password' => 'password',

    // Giriş yapmamış kullanıcının yönlendirileceği adres; girişli kullanıcının "guest" sayfalarından gideceği adres
    'login_url' => '/login',
    'home_url' => '/',
];
