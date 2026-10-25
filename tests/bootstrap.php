<?php

// Test başlangıcı: autoload + (PHPUnit yoksa) uyumluluk katmanı
require dirname(__DIR__) . '/vendor/autoload.php';

if (!class_exists(\PHPUnit\Framework\TestCase::class)) {
    require __DIR__ . '/support/phpunit_shim.php';
}

date_default_timezone_set('UTC');
