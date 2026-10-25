<?php

namespace Tests;

use Core\Application;
use Core\Database\DB;
use Core\System\Router;
use PDO;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Her test: temiz bir Application (geçici storage klasörü) ve bellek içi SQLite veritabanı.
 */
abstract class TestCase extends BaseTestCase
{
    protected Application $app;
    protected string $storage;

    protected function setUp(): void
    {
        $this->storage = sys_get_temp_dir() . '/markuvo_test_' . bin2hex(random_bytes(6));
        mkdir($this->storage . '/logs', 0777, true);
        mkdir($this->storage . '/cache/data', 0777, true);
        mkdir($this->storage . '/cache/views', 0777, true);

        $this->app = Application::boot(dirname(__DIR__), [
            'console' => true,
            'load_env' => false,
            'storage_path' => $this->storage,
        ]);

        DB::setConnection(new PDO('sqlite::memory:'), 'sqlite');
        if (class_exists(Router::class)) { Router::flush(); }

        $_SESSION = [];
        $_GET = $_POST = $_FILES = $_COOKIE = [];
    }

    protected function tearDown(): void
    {
        DB::close();
        $this->removeDirectory($this->storage);
    }

    protected function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        ) as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }
}
