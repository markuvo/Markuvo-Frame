<?php

use App\Controllers\AnneController;
use App\Controllers\AuthController;
use Core\System\Request;
use Core\System\Router;

/*
|--------------------------------------------------------------------------
| Web rotaları
|--------------------------------------------------------------------------
| Router::get('/blog/{id}', [BlogController::class, 'show'])->name('blog.show')->where('id', '\d+');
| Router::post('/login', 'AuthController@login')->middleware('throttle:5,1');
| Router::prefix('/admin')->middleware('auth')->group(function () { ... });
|
| Closure ve controller metotları string, dizi (JSON), view() sonucu ya da redirect() döndürebilir.
*/

Router::get('/', function () {
    return view('home', [
        'title' => 'Markuvo ENES',
        'welcome' => 'Markuvo Ekosistemine Hoş Geldiniz!',
        'description' => 'DEĞERLER BURADAN GELİYOR',
    ]);
})->name('home');

// Controller testi
Router::get('/deneme', [AnneController::class, 'index']);

// Dinamik rota testleri ({id} ve :id biçimlerinin ikisi de çalışır)
Router::get('/user/{id}', function ($request, $id) {
    return 'Aranan Kullanıcı ID: ' . e($id);
});

Router::get('/kullanici/:id', function ($request, $id) {
    return '<h1>Markuvo CLI & Rota Testi</h1><p>Yakaladığımız Dinamik Kullanıcı ID: <b>' . e($id) . '</b></p>';
});

// Giriş / çıkış
Router::get('/login', 'AuthController@showLogin')->middleware('guest')->name('login');
Router::post('/login', 'AuthController@login')->middleware(['guest', 'throttle:10,1']);
Router::post('/logout', 'AuthController@logout')->middleware('auth')->name('logout');

// Giriş yapmış kullanıcıya özel sayfa
Router::get('/panel', function () {
    return view('panel', ['title' => 'Markuvo | Panel', 'user' => auth()->user()]);
})->middleware('auth')->name('panel');

// Yönetim alanı: giriş zorunlu
Router::prefix('/admin')->middleware('auth')->group(function () {
    Router::get('/dashboard', function () {
        return '<h1>🛡️ Markuvo Yönetim Paneli</h1><p>Grup rota mimarisi başarıyla prefix çözümlendi.</p>';
    });

    Router::get('/user/:id', function ($request, $id) {
        return '<h1>Yönetici Modülü</h1><p>Admin paneli üzerinden aranan üye ID: <b>' . e($id) . '</b></p>';
    });
});
