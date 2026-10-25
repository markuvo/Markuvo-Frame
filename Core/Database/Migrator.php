<?php

namespace Core\Database;

use Core\Database\Schema\Blueprint;
use Core\Support\Str;

/**
 * Migration çalıştırıcı. Çalışan migration'lar "migrations" tablosunda batch numarasıyla tutulur.
 *
 * Migration dosyası iki biçimde yazılabilir:
 *   return new class { public function up() {...} public function down() {...} };
 *   class CreateUsersTable { ... }       // dosya adındaki zaman damgasından sonraki kısım sınıf adıdır
 */
class Migrator
{
    protected static array $loaded = [];

    public function __construct(protected string $path)
    {
    }

    protected function ensureTable(): void
    {
        if (!Schema::hasTable('migrations')) {
            Schema::create('migrations', function (Blueprint $t) {
                $t->id();
                $t->string('migration')->unique();
                $t->integer('batch');
            });
        }
    }

    /** @return string[] dizindeki migration adları (zaman damgasına göre sıralı) */
    public function files(): array
    {
        $files = glob(rtrim($this->path, '/\\') . '/*.php') ?: [];
        sort($files, SORT_STRING);

        return array_map(fn($f) => basename($f, '.php'), $files);
    }

    public function ran(): array
    {
        $this->ensureTable();

        return array_map(fn($r) => $r->migration, DB::table('migrations')->orderBy('id')->get()->all());
    }

    /** Bekleyen migration'ları çalıştırır; çalışanların adlarını döner. */
    public function run(): array
    {
        $this->ensureTable();
        $done = $this->ran();
        $pending = array_values(array_diff($this->files(), $done));

        if ($pending === []) {
            return [];
        }

        $batch = (int) DB::table('migrations')->max('batch') + 1;

        foreach ($pending as $name) {
            $this->execute($name, 'up');
            DB::table('migrations')->insert(['migration' => $name, 'batch' => $batch]);
        }

        return $pending;
    }

    /** Son batch'i (ya da $steps batch) geri alır. */
    public function rollback(int $steps = 1): array
    {
        $this->ensureTable();
        $rolled = [];

        for ($i = 0; $i < $steps; $i++) {
            $batch = (int) DB::table('migrations')->max('batch');
            if ($batch === 0) {
                break;
            }

            $rows = DB::table('migrations')->where('batch', $batch)->orderByDesc('id')->get();
            foreach ($rows as $row) {
                $this->execute($row->migration, 'down');
                DB::table('migrations')->where('id', $row->id)->delete();
                $rolled[] = $row->migration;
            }
        }

        return $rolled;
    }

    public function reset(): array
    {
        $this->ensureTable();

        return $this->rollback((int) DB::table('migrations')->max('batch'));
    }

    /** @return array<int, array{migration:string, ran:bool, batch:?int}> */
    public function status(): array
    {
        $this->ensureTable();
        $rows = [];
        foreach (DB::table('migrations')->get() as $r) {
            $rows[$r->migration] = (int) $r->batch;
        }

        return array_map(fn($name) => [
            'migration' => $name,
            'ran' => isset($rows[$name]),
            'batch' => $rows[$name] ?? null,
        ], $this->files());
    }

    protected function execute(string $name, string $method): void
    {
        $instance = $this->resolve($name);

        if (!method_exists($instance, $method)) {
            throw new \RuntimeException("Migration '{$name}' içinde {$method}() metodu yok.");
        }

        // PostgreSQL ve SQLite'ta DDL işlem içinde geri alınabilir; MySQL'de DDL otomatik commit eder.
        if (DB::driver() === 'mysql') {
            $instance->{$method}();

            return;
        }

        DB::transaction(fn() => $instance->{$method}());
    }

    protected function resolve(string $name): object
    {
        $file = rtrim($this->path, '/\\') . '/' . $name . '.php';

        if (!is_file($file)) {
            throw new \RuntimeException("Migration dosyası bulunamadı: {$name}");
        }

        if (isset(static::$loaded[$file])) {
            return static::$loaded[$file] instanceof \Closure ? (static::$loaded[$file])() : static::$loaded[$file];
        }

        $result = require_once $file;

        if (is_object($result)) {
            // Anonim sınıf tek seferlik oluşur; yeniden kullanmak için dosya yolunda önbelleğe alınır
            return static::$loaded[$file] = $result;
        }

        $class = Str::studly(preg_replace('/^\d+_/', '', $name));

        if (!class_exists($class)) {
            throw new \RuntimeException("Migration '{$name}' dosyasında {$class} sınıfı bulunamadı.");
        }

        return new $class();
    }

    public static function forget(): void
    {
        static::$loaded = [];
    }
}
