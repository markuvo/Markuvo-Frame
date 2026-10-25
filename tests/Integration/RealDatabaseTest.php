<?php

namespace Tests\Integration;

use Core\Database\DB;
use Core\Database\Migrator;
use Core\Database\Schema;
use Core\Database\Schema\Blueprint;
use Core\Model;
use Core\System\Validator;
use Tests\TestCase;

class MkKayit extends Model
{
    protected $table = 'mk_kayitlar';
    protected $fillable = ['ad', 'puan', 'aktif'];
}

/**
 * Gerçek MySQL / PostgreSQL sunucusuna karşı çalışır. .env içinde TEST_DB tanımlı değilse ATLANIR.
 *
 * .env dosyasına ekleyin (asıl DB_DATABASE'den FARKLI, boş bir test veritabanı):
 *
 *   TEST_DB=mysql                    # mysql | pgsql
 *   TEST_DB_DATABASE=markuvo_test
 *   TEST_DB_HOST=127.0.0.1           # isteğe bağlı (yoksa DB_HOST)
 *   TEST_DB_PORT=3306                # isteğe bağlı (yoksa DB_PORT)
 *   TEST_DB_USERNAME=root            # isteğe bağlı (yoksa DB_USERNAME)
 *   TEST_DB_PASSWORD=                # isteğe bağlı (yoksa DB_PASSWORD)
 *
 * Çalıştırma:  php markuvo test Real
 *
 * UYARI: Test veritabanında "mk_" önekli tablolar ile "migrations" ve "users" tablolarını
 * oluşturur ve SİLER. Asıl veritabanınızla aynı olmaması zorunludur (aynıysa test durur).
 */
class RealDatabaseTest extends TestCase
{
    /** Yalnızca güvenlik denetimlerini geçip test veritabanına bağlandıktan sonra true olur. */
    protected bool $ready = false;

    protected function env(string $key, ?string $fallbackKey = null, ?string $default = null): ?string
    {
        $v = getenv($key);
        if (($v === false || $v === '') && $fallbackKey !== null) {
            $v = getenv($fallbackKey);
        }

        return ($v === false || $v === '') ? $default : $v;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $envFile = dirname(__DIR__, 2) . '/.env';
        if (is_file($envFile)) {
            (new \Core\DotEnv($envFile))->load();   // sistemde tanımlı değişkenleri ezmez
        }

        $driver = $this->env('TEST_DB');
        if (!in_array($driver, ['mysql', 'pgsql'], true)) {
            $this->markTestSkipped('.env içinde TEST_DB=mysql|pgsql tanımlı olmadığı için atlandı.');
        }

        $database = $this->env('TEST_DB_DATABASE', 'DB_DATABASE');
        $real = $this->env('DB_DATABASE');
        if ($database === null || $database === '') {
            $this->markTestSkipped('TEST_DB_DATABASE tanımlı değil.');
        }
        if ($database === $real && $this->env('TEST_DB_DATABASE') === null) {
            $this->markTestSkipped('Güvenlik: TEST_DB_DATABASE, asıl DB_DATABASE ile aynı olamaz. Ayrı bir test veritabanı tanımlayın.');
        }
        if ($database === $real) {
            $this->fail("Güvenlik: TEST_DB_DATABASE ({$database}) asıl DB_DATABASE ile aynı; test durduruldu, hiçbir tablo silinmedi.");
        }

        config([
            'database.default' => $driver,
            "database.connections.{$driver}.host" => $this->env('TEST_DB_HOST', 'DB_HOST', '127.0.0.1'),
            "database.connections.{$driver}.port" => $this->env('TEST_DB_PORT', 'DB_PORT', $driver === 'pgsql' ? '5432' : '3306'),
            "database.connections.{$driver}.database" => $database,
            "database.connections.{$driver}.username" => $this->env('TEST_DB_USERNAME', 'DB_USERNAME', $driver === 'pgsql' ? 'postgres' : 'root'),
            "database.connections.{$driver}.password" => $this->env('TEST_DB_PASSWORD', 'DB_PASSWORD', ''),
        ]);

        DB::close();
        DB::connect();
        $this->ready = true;
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        if ($this->ready) {
            $this->cleanup();
        }
        DB::close();
        parent::tearDown();
    }

    protected function cleanup(): void
    {
        foreach (['mk_cocuk', 'mk_kayitlar', 'mk_eski', 'mk_yeni', 'migrations', 'users'] as $t) {
            Schema::dropIfExists($t);
        }
    }

    public function testSchemaCreateAlterRenameDrop(): void
    {
        Schema::create('mk_kayitlar', function (Blueprint $t) {
            $t->id();
            $t->string('ad', 100)->unique();
            $t->integer('puan')->default(0);
            $t->boolean('aktif')->default(true);
            $t->decimal('tutar', 8, 2)->nullable();
            $t->text('not')->nullable();
            $t->json('ek')->nullable();
            $t->timestamps();
        });

        $this->assertTrue(Schema::hasTable('mk_kayitlar'));
        $this->assertTrue(Schema::hasColumn('mk_kayitlar', 'puan'));
        $this->assertFalse(Schema::hasColumn('mk_kayitlar', 'yok'));

        Schema::table('mk_kayitlar', fn(Blueprint $t) => $t->string('etiket')->nullable());
        Schema::table('mk_kayitlar', fn(Blueprint $t) => $t->renameColumn('etiket', 'tag'));
        $this->assertTrue(Schema::hasColumn('mk_kayitlar', 'tag'));
        Schema::table('mk_kayitlar', fn(Blueprint $t) => $t->dropColumn('tag'));
        $this->assertFalse(Schema::hasColumn('mk_kayitlar', 'tag'));

        Schema::rename('mk_kayitlar', 'mk_yeni');
        $this->assertTrue(Schema::hasTable('mk_yeni'));
        Schema::drop('mk_yeni');
        $this->assertFalse(Schema::hasTable('mk_yeni'));
    }

    public function testOrmCrudPaginationAndAggregates(): void
    {
        Schema::create('mk_kayitlar', function (Blueprint $t) {
            $t->id();
            $t->string('ad')->unique();
            $t->integer('puan')->default(0);
            $t->boolean('aktif')->default(true);
            $t->timestamps();
        });

        for ($i = 1; $i <= 25; $i++) {
            MkKayit::create(['ad' => "kayıt $i", 'puan' => $i, 'aktif' => $i % 2 === 0]);
        }

        $first = MkKayit::find(1);
        $this->assertInstanceOf(MkKayit::class, $first);
        $this->assertSame('kayıt 1', $first->ad, 'UTF-8 gidiş-dönüş');
        $this->assertSame(1, (int) $first->id, 'insertGetId doğru id döndürmeli');

        $this->assertSame(25, MkKayit::count());
        $this->assertSame(12, MkKayit::where('aktif', true)->count());
        $this->assertEquals(325, MkKayit::sum('puan'));
        $this->assertEquals(25, MkKayit::max('puan'));

        $page = MkKayit::orderBy('puan', 'desc')->paginate(10, 3);
        $this->assertCount(5, $page);
        $this->assertSame(3, $page->lastPage());
        $this->assertEquals(5, $page->items()->first()->puan);

        $this->assertSame(1, MkKayit::where('ad', 'kayıt 2')->update(['puan' => 99]));
        $this->assertEquals(99, MkKayit::where('ad', 'kayıt 2')->first()->puan);
        $this->assertSame(1, MkKayit::where('ad', 'kayıt 3')->delete());
        $this->assertSame(24, MkKayit::count());

        $this->assertCount(2, MkKayit::whereIn('puan', [1, 2, 99, 4000])->get());   // 2 numaralı kaydın puanı 99 oldu
        $this->assertSame(['kayıt 1'], MkKayit::whereBetween('puan', [0, 1])->pluck('ad')->all());
    }

    public function testUniqueViolationRollsBackTransactionAndIsLogged(): void
    {
        Schema::create('mk_kayitlar', function (Blueprint $t) {
            $t->id();
            $t->string('ad')->unique();
            $t->integer('puan')->default(0);
        });

        try {
            DB::transaction(function () {
                DB::table('mk_kayitlar')->insert(['ad' => 'gizli-deger-77', 'puan' => 1]);
                DB::table('mk_kayitlar')->insert(['ad' => 'gizli-deger-77', 'puan' => 2]);   // benzersizlik ihlali
            });
            $this->fail('istisna bekleniyordu');
        } catch (\Throwable $e) {
            $this->assertNotInstanceOf(\AssertionError::class, $e);
        }

        $this->assertSame(0, DB::table('mk_kayitlar')->count(), 'transaction geri alınmalı');
        $log = file_get_contents($this->storage . '/logs/db_errors.log');
        $this->assertStringContainsString('mk_kayitlar', $log);
        $this->assertStringNotContainsString('gizli-deger-77', $log, 'değer sürücü mesajından da sızmamalı');
        $appLog = $this->storage . '/logs/app.log';
        $this->assertStringNotContainsString('gizli-deger-77', is_file($appLog) ? file_get_contents($appLog) : '');
    }

    public function testForeignKeysAndCascade(): void
    {
        Schema::create('mk_kayitlar', fn(Blueprint $t) => $t->id());
        Schema::create('mk_cocuk', function (Blueprint $t) {
            $t->id();
            $t->foreignId('kayit_id')->constrained('mk_kayitlar')->cascadeOnDelete();
        });

        $id = DB::table('mk_kayitlar')->insertGetId([]);
        DB::table('mk_cocuk')->insert(['kayit_id' => $id]);
        $this->assertSame(1, DB::table('mk_cocuk')->count());

        DB::table('mk_kayitlar')->where('id', $id)->delete();
        $this->assertSame(0, DB::table('mk_cocuk')->count(), 'ON DELETE CASCADE çalışmalı');
    }

    public function testMigratorAndValidatorUniqueOnRealServer(): void
    {
        (new Migrator(dirname(__DIR__, 2) . '/database/migrations'))->run();
        $this->assertTrue(Schema::hasTable('users'));

        \App\Models\User::create(['username' => 'u1', 'email' => 'u1@x.com', 'password' => 'x']);

        $this->assertArrayHasKey('email', Validator::make(['email' => 'u1@x.com'], ['email' => 'unique:users,email'])->errors());
        $this->assertSame([], Validator::make(['email' => 'u2@x.com'], ['email' => 'unique:users,email'])->errors());

        (new Migrator(dirname(__DIR__, 2) . '/database/migrations'))->reset();
        $this->assertFalse(Schema::hasTable('users'));
    }

    public function testInjectionAttemptsStayHarmless(): void
    {
        Schema::create('mk_kayitlar', function (Blueprint $t) {
            $t->id();
            $t->string('ad');
        });
        MkKayit::query()->insert(['ad' => "x' OR '1'='1"]);

        $this->assertSame(0, DB::table('mk_kayitlar')->where('ad', 'x')->count());
        $this->assertSame(1, DB::table('mk_kayitlar')->where('ad', "x' OR '1'='1")->count());

        $this->expectException(\Throwable::class);
        DB::table('mk_kayitlar')->orderBy('ad; DROP TABLE mk_kayitlar')->get();
    }
}
