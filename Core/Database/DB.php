<?php

namespace Core\Database;

use Closure;
use Core\Application;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Veritabanı bağlantı yöneticisi. Tek bir PDO bağlantısı tutar (istek boyunca kalıcı).
 * Desteklenen sürücüler: mysql (MariaDB dahil), pgsql, sqlite.
 *
 *   DB::select('SELECT * FROM users WHERE id = ?', [1]);
 *   DB::table('users')->where('role', 'admin')->get();
 *   DB::transaction(function () { ... });
 */
class DB
{
    protected static ?PDO $pdo = null;
    protected static ?string $driver = null;
    protected static int $transactionLevel = 0;

    // ---------------------------------------------------------------------
    // Bağlantı
    // ---------------------------------------------------------------------

    public static function connect(): void
    {
        if (self::$pdo !== null) {
            return;
        }

        $name = trim((string) config('database.default', 'mysql'));
        $name = $name === 'mariadb' ? 'mysql' : $name;
        $cfg = config("database.connections.{$name}");

        if (!is_array($cfg)) {
            throw new \Exception("Markuvo Veritabanı Hatası: '{$name}' sürücüsü desteklenmiyor (mysql, mariadb, pgsql ya da sqlite kullanın).");
        }

        $driver = $cfg['driver'];
        $database = trim((string) ($cfg['database'] ?? ''));

        if ($database === '') {
            throw new \Exception('Markuvo Veritabanı Hatası: .env içinde DB_DATABASE tanımlı değil.');
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_PERSISTENT => false,
        ];

        if ($driver !== 'sqlite') {
            $options[PDO::ATTR_EMULATE_PREPARES] = false;
        }

        try {
            $pdo = new PDO(
                self::dsn($driver, $cfg),
                $driver === 'sqlite' ? null : ($cfg['username'] ?? null),
                $driver === 'sqlite' ? null : ($cfg['password'] ?? null),
                $options
            );
        } catch (PDOException $e) {
            self::report('Bağlantı hatası (' . $driver . '): ' . $e->getMessage());
            throw new \Exception('Markuvo Veritabanı Bağlantı Hatası (' . $driver . '): ' . $e->getMessage(), 0, $e);
        }

        if ($driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }

        self::$pdo = $pdo;
        self::$driver = $driver;
    }

    protected static function dsn(string $driver, array $cfg): string
    {
        $host = trim((string) ($cfg['host'] ?? '127.0.0.1'));
        $port = trim((string) ($cfg['port'] ?? ''));
        $database = trim((string) $cfg['database']);

        return match ($driver) {
            'mysql' => sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $host, $port !== '' ? $port : '3306', $database, $cfg['charset'] ?? 'utf8mb4'
            ),
            'pgsql' => sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $host, $port !== '' ? $port : '5432', $database
            ),
            'sqlite' => 'sqlite:' . self::sqlitePath($database),
            default => throw new \Exception("Markuvo Veritabanı Hatası: '{$driver}' sürücüsü desteklenmiyor."),
        };
    }

    /** ":memory:" ve mutlak yollar olduğu gibi kalır; göreli yol proje köküne göre çözülür. */
    protected static function sqlitePath(string $path): string
    {
        if ($path === ':memory:' || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            return $path;
        }

        return base_path($path);
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::connect();
        }

        return self::$pdo;
    }

    public static function driver(): string
    {
        self::pdo();

        return self::$driver;
    }

    /** Hazır bir PDO bağlantısını kullan (test ve özel kurulumlar için). */
    public static function setConnection(PDO $pdo, string $driver): void
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

        self::$pdo = $pdo;
        self::$driver = $driver;
        self::$transactionLevel = 0;
    }

    public static function close(): void
    {
        self::$pdo = null;
        self::$driver = null;
        self::$transactionLevel = 0;
    }

    // ---------------------------------------------------------------------
    // Sorgu çalıştırma
    // ---------------------------------------------------------------------

    public static function table(string $table): QueryBuilder
    {
        return new QueryBuilder($table);
    }

    /** SELECT çalıştırır, stdClass nesnelerinden oluşan dizi döner. */
    public static function select(string $sql, array $bindings = []): array
    {
        return self::run($sql, $bindings)->fetchAll(PDO::FETCH_OBJ);
    }

    /** INSERT/CREATE/DROP gibi komutlar. */
    public static function statement(string $sql, array $bindings = []): bool
    {
        self::run($sql, $bindings);

        return true;
    }

    /** UPDATE/DELETE: etkilenen satır sayısını döner. */
    public static function affectingStatement(string $sql, array $bindings = []): int
    {
        return self::run($sql, $bindings)->rowCount();
    }

    /**
     * Tek merkezden sorgu çalıştırır: hazırlar, değerleri doğru tiple bağlar, hata olursa
     * db_errors.log'a yazar ve QueryException fırlatır.
     */
    public static function run(string $sql, array $bindings = []): PDOStatement
    {
        $pdo = self::pdo();

        try {
            $statement = $pdo->prepare($sql);

            foreach (array_values($bindings) as $i => $value) {
                $statement->bindValue($i + 1, $value, self::paramType($value));
            }

            $statement->execute();

            return $statement;
        } catch (PDOException $e) {
            self::report($e->getMessage() . ' | SQL: ' . $sql);
            throw new QueryException($sql, $e);
        }
    }

    protected static function paramType(mixed $value): int
    {
        return match (true) {
            is_int($value)  => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            is_null($value) => PDO::PARAM_NULL,
            default         => PDO::PARAM_STR,
        };
    }

    // ---------------------------------------------------------------------
    // Transaction (iç içe çağrılabilir: yalnızca en dıştaki gerçek transaction açar)
    // ---------------------------------------------------------------------

    public static function beginTransaction(): void
    {
        if (self::$transactionLevel === 0) {
            self::pdo()->beginTransaction();
        }

        self::$transactionLevel++;
    }

    public static function commit(): void
    {
        if (self::$transactionLevel === 1) {
            self::pdo()->commit();
        }

        self::$transactionLevel = max(0, self::$transactionLevel - 1);
    }

    public static function rollBack(): void
    {
        if (self::$transactionLevel === 1 && self::pdo()->inTransaction()) {
            self::pdo()->rollBack();
        }

        self::$transactionLevel = max(0, self::$transactionLevel - 1);
    }

    public static function transaction(Closure $callback): mixed
    {
        self::beginTransaction();

        try {
            $result = $callback();
        } catch (\Throwable $e) {
            self::rollBack();
            throw $e;
        }

        self::commit();

        return $result;
    }

    /** Veritabanı hatalarını logs/db_errors.log dosyasına yazar (varsa). */
    protected static function report(string $message): void
    {
        try {
            if (Application::getInstance() !== null) {
                logger('db_errors')->error($message);
            }
        } catch (\Throwable) {
            // Log yazılamıyorsa asıl hatayı gölgelememek için sessizce geç.
        }
    }
}
