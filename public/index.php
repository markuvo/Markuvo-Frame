<?php

// 1. Composer autoload
require_once __DIR__ . '/../vendor/autoload.php';

use Core\Application;

// 2. Uygulamayı başlat: hata yakalayıcıyı kurar, .env + config/ dosyalarını yükler, servisleri hazırlar.
//    (Veritabanı bağlantısı ilk sorguda, gerektiğinde kurulur.)
$app = Application::boot(dirname(__DIR__));

// 3. Rotalar
require_once __DIR__ . '/../routes/web.php';

// 4. İsteği işle ve yanıtı gönder
$app->run();
