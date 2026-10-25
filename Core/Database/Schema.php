<?php

namespace Core\Database;

use Closure;
use Core\Database\Schema\Blueprint;
use Core\Database\Schema\SchemaGrammar;

/**
 * Tablo oluşturma/değiştirme (migration'larda kullanılır). mysql, pgsql ve sqlite'ta çalışır.
 *
 *   Schema::create('urunler', function (Blueprint $t) {
 *       $t->id();
 *       $t->string('ad');
 *       $t->decimal('fiyat', 10, 2)->default(0);
 *       $t->timestamps();
 *   });
 */
class Schema
{
    public static function create(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        self::run((new SchemaGrammar(DB::driver()))->compileCreate($blueprint));
    }

    public static function createIfNotExists(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        self::run((new SchemaGrammar(DB::driver()))->compileCreate($blueprint, true));
    }

    public static function table(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        $grammar = new SchemaGrammar(DB::driver());
        if (DB::driver() === 'mysql' && $blueprint->renameColumns !== []) {
            $grammar->columnDefinitions = self::mysqlColumnDefinitions($table, array_keys($blueprint->renameColumns));
        }

        self::run($grammar->compileAlter($blueprint));
    }

    /** SHOW CREATE TABLE çıktısından istenen sütunların tanım kısmını ("`ad` " sonrası) çıkarır. */
    protected static function mysqlColumnDefinitions(string $table, array $columns): array
    {
        $row = DB::select('SHOW CREATE TABLE ' . Grammar::wrap($table, 'mysql'))[0] ?? null;
        $ddl = $row ? (string) (((array) $row)['Create Table'] ?? '') : '';
        $found = [];

        foreach (preg_split('/\R/', $ddl) as $line) {
            $line = trim($line);
            foreach ($columns as $column) {
                $prefix = '`' . str_replace('`', '``', $column) . '` ';
                if (str_starts_with($line, $prefix)) {
                    $found[$column] = rtrim(substr($line, strlen($prefix)), ',');
                }
            }
        }

        return $found;
    }

    public static function drop(string $table): void
    {
        DB::statement((new SchemaGrammar(DB::driver()))->compileDrop($table));
    }

    public static function dropIfExists(string $table): void
    {
        DB::statement((new SchemaGrammar(DB::driver()))->compileDrop($table, true));
    }

    public static function rename(string $from, string $to): void
    {
        DB::statement((new SchemaGrammar(DB::driver()))->compileRename($from, $to));
    }

    public static function hasTable(string $table): bool
    {
        $sql = match (DB::driver()) {
            'sqlite' => "SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?",
            'pgsql' => 'SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = ?',
            default => 'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        };

        return DB::select($sql, [$table]) !== [];
    }

    public static function hasColumn(string $table, string $column): bool
    {
        if (DB::driver() === 'sqlite') {
            foreach (DB::select('PRAGMA table_info(' . Grammar::wrap($table, 'sqlite') . ')') as $row) {
                if (strcasecmp((string) $row->name, $column) === 0) {
                    return true;
                }
            }

            return false;
        }

        $sql = DB::driver() === 'pgsql'
            ? 'SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?'
            : 'SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?';

        return DB::select($sql, [$table, $column]) !== [];
    }

    /** DDL cümlelerini çalıştırır (mysql'de DDL otomatik commit eder; bu yüzden transaction'a sarılmaz). */
    protected static function run(array $statements): void
    {
        foreach ($statements as $sql) {
            DB::statement($sql);
        }
    }

    /** Üretilen SQL'i çalıştırmadan görmek için (test/debug). */
    public static function toSql(string $driver, Closure $callback, string $table = 'tablo', bool $alter = false): array
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        $grammar = new SchemaGrammar($driver);

        return $alter ? $grammar->compileAlter($blueprint) : $grammar->compileCreate($blueprint);
    }
}
