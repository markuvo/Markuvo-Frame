<?php

namespace Tests\Unit;

use App\Models\User;
use Core\Database\DB;
use Core\Database\Migrator;
use Core\Database\Schema;
use Core\Database\Schema\Blueprint;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    protected function blueprint(): \Closure
    {
        return function (Blueprint $t) {
            $t->id();
            $t->string('ad', 100)->unique();
            $t->decimal('fiyat', 8, 2)->default(0);
            $t->boolean('aktif')->default(true);
            $t->text('not')->nullable();
            $t->foreignId('kategori_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
        };
    }

    public function testMysqlDdl(): void
    {
        $sql = Schema::toSql('mysql', $this->blueprint(), 'urunler');

        $this->assertStringContainsString('`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', $sql[0]);
        $this->assertStringContainsString('`ad` VARCHAR(100) NOT NULL', $sql[0]);
        $this->assertStringContainsString('`aktif` TINYINT(1) NOT NULL DEFAULT 1', $sql[0]);
        $this->assertStringContainsString('`not` TEXT NULL', $sql[0]);
        $this->assertStringContainsString('CONSTRAINT `fk_kategori_id_foreign` FOREIGN KEY (`kategori_id`) REFERENCES `kategoris` (`id`) ON DELETE CASCADE', $sql[0]);
        $this->assertStringContainsString('utf8mb4', $sql[0]);
        $this->assertSame('CREATE UNIQUE INDEX `urunler_ad_unique` ON `urunler` (`ad`)', $sql[1]);
    }

    public function testPgsqlDdl(): void
    {
        $sql = Schema::toSql('pgsql', $this->blueprint(), 'urunler');

        $this->assertStringContainsString('"id" BIGSERIAL PRIMARY KEY', $sql[0]);
        $this->assertStringContainsString('"aktif" BOOLEAN NOT NULL DEFAULT TRUE', $sql[0]);
        $this->assertStringContainsString('"created_at" TIMESTAMP(0) NULL', $sql[0]);
        $this->assertStringNotContainsString('UNSIGNED', $sql[0]);
        $this->assertStringNotContainsString('utf8mb4', $sql[0]);
    }

    public function testSqliteDdl(): void
    {
        $sql = Schema::toSql('sqlite', $this->blueprint(), 'urunler');

        $this->assertStringContainsString('"id" INTEGER PRIMARY KEY AUTOINCREMENT', $sql[0]);
        $this->assertStringContainsString('"aktif" INTEGER NOT NULL DEFAULT 1', $sql[0]);
    }

    public function testMysqlRenameColumnUsesChangeWithExistingDefinition(): void
    {
        $blueprint = new Blueprint('t');
        $blueprint->renameColumn('eski', 'yeni');
        $grammar = new \Core\Database\Schema\SchemaGrammar('mysql');
        $grammar->columnDefinitions = ['eski' => 'varchar(255) DEFAULT NULL'];

        $this->assertSame(['ALTER TABLE `t` CHANGE `eski` `yeni` varchar(255) DEFAULT NULL'], $grammar->compileAlter($blueprint));

        $pg = (new \Core\Database\Schema\SchemaGrammar('pgsql'))->compileAlter($blueprint);
        $this->assertSame(['ALTER TABLE "t" RENAME COLUMN "eski" TO "yeni"'], $pg);
    }

    public function testDefaultStringsAreEscaped(): void
    {
        $sql = Schema::toSql('mysql', fn(Blueprint $t) => $t->string('durum')->default("o'neil"));

        $this->assertStringContainsString("DEFAULT 'o''neil'", $sql[0]);
    }

    public function testInjectionInIdentifiersIsRejected(): void
    {
        $this->expectException(\Throwable::class);
        Schema::toSql('mysql', fn(Blueprint $t) => $t->string('a`; DROP TABLE users; --'));
    }

    public function testInvalidForeignKeyActionIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Schema::toSql('mysql', fn(Blueprint $t) => $t->foreignId('x')->constrained('y')->onDelete('cascade; DROP TABLE y'));
    }

    public function testCreateAlterAndDropOnRealSqlite(): void
    {
        Schema::create('notlar', function (Blueprint $t) {
            $t->id();
            $t->string('baslik')->unique();
            $t->timestamps();
        });

        $this->assertTrue(Schema::hasTable('notlar'));
        $this->assertTrue(Schema::hasColumn('notlar', 'baslik'));
        $this->assertFalse(Schema::hasColumn('notlar', 'icerik'));

        Schema::table('notlar', function (Blueprint $t) {
            $t->text('icerik')->nullable();
            $t->index('created_at');
        });
        $this->assertTrue(Schema::hasColumn('notlar', 'icerik'));

        Schema::table('notlar', fn(Blueprint $t) => $t->renameColumn('icerik', 'metin'));
        $this->assertTrue(Schema::hasColumn('notlar', 'metin'));

        Schema::table('notlar', fn(Blueprint $t) => $t->dropColumn('metin'));
        $this->assertFalse(Schema::hasColumn('notlar', 'metin'));

        // unique gerçekten çalışıyor mu
        DB::table('notlar')->insert(['baslik' => 'a']);
        $this->expectException(\Throwable::class);
        DB::table('notlar')->insert(['baslik' => 'a']);
    }

    public function testInsertingARowWithOnlyDefaults(): void
    {
        Schema::create('varsayilanlar', function (Blueprint $t) {
            $t->id();
            $t->integer('n')->default(5);
        });

        $this->assertSame(1, DB::table('varsayilanlar')->insertGetId([]));
        DB::table('varsayilanlar')->insert([]);
        $this->assertSame(2, DB::table('varsayilanlar')->count());
        $this->assertEquals(5, DB::table('varsayilanlar')->first()->n);
    }

    public function testDropAndRename(): void
    {
        Schema::create('a_tablo', fn(Blueprint $t) => $t->id());
        Schema::rename('a_tablo', 'b_tablo');
        $this->assertTrue(Schema::hasTable('b_tablo'));
        $this->assertFalse(Schema::hasTable('a_tablo'));

        Schema::dropIfExists('b_tablo');
        Schema::dropIfExists('b_tablo');
        $this->assertFalse(Schema::hasTable('b_tablo'));
    }

    // -------------------------------------------------------------- Migrator

    protected function migrationDir(): string
    {
        $dir = $this->storage . '/migrations_' . uniqid();
        @mkdir($dir, 0777, true);

        return $dir;
    }

    public function testMigratorRunRollbackStatus(): void
    {
        $dir = $this->migrationDir();
        $id = uniqid();

        file_put_contents("{$dir}/20260101000001_create_a_{$id}.php", "<?php
return new class {
    public function up() { \\Core\\Database\\Schema::create('m_a', fn(\\Core\\Database\\Schema\\Blueprint \$t) => \$t->id()); }
    public function down() { \\Core\\Database\\Schema::dropIfExists('m_a'); }
};");
        file_put_contents("{$dir}/20260101000002_create_b_{$id}.php", "<?php
return new class {
    public function up() { \\Core\\Database\\Schema::create('m_b', fn(\\Core\\Database\\Schema\\Blueprint \$t) => \$t->id()); }
    public function down() { \\Core\\Database\\Schema::dropIfExists('m_b'); }
};");

        $m = new Migrator($dir);

        $this->assertCount(2, $m->run());
        $this->assertTrue(Schema::hasTable('m_a') && Schema::hasTable('m_b'));
        $this->assertSame([], $m->run(), 'ikinci çalıştırma bir şey yapmamalı');
        $this->assertTrue($m->status()[0]['ran']);
        $this->assertSame(1, $m->status()[0]['batch']);

        file_put_contents("{$dir}/20260101000003_create_c_{$id}.php", "<?php
return new class {
    public function up() { \\Core\\Database\\Schema::create('m_c', fn(\\Core\\Database\\Schema\\Blueprint \$t) => \$t->id()); }
    public function down() { \\Core\\Database\\Schema::dropIfExists('m_c'); }
};");
        $this->assertCount(1, $m->run());                 // batch 2
        $this->assertCount(1, $m->rollback());            // yalnızca son batch
        $this->assertFalse(Schema::hasTable('m_c'));
        $this->assertTrue(Schema::hasTable('m_a'));

        $this->assertCount(2, $m->reset());
        $this->assertFalse(Schema::hasTable('m_a'));
    }

    public function testFailedMigrationRollsBackAndIsNotRecorded(): void
    {
        $dir = $this->migrationDir();
        $id = uniqid();
        file_put_contents("{$dir}/20260101000009_bozuk_{$id}.php", "<?php
return new class {
    public function up() {
        \\Core\\Database\\Schema::create('m_bozuk', fn(\\Core\\Database\\Schema\\Blueprint \$t) => \$t->id());
        throw new \\RuntimeException('patladı');
    }
    public function down() {}
};");
        $m = new Migrator($dir);

        try {
            $m->run();
            $this->fail('istisna bekleniyordu');
        } catch (\RuntimeException $e) {
            $this->assertSame('patladı', $e->getMessage());
        }

        $this->assertFalse(Schema::hasTable('m_bozuk'));
        $this->assertSame([], $m->ran());
    }

    public function testNamedClassMigrationAndRealProjectMigrationsWithSeeder(): void
    {
        $m = new Migrator(dirname(__DIR__, 2) . '/database/migrations');
        $m->run();

        $this->assertTrue(Schema::hasTable('users'));

        (new DatabaseSeeder())->run();
        (new DatabaseSeeder())->run();   // idempotent

        $this->assertSame(11, User::count());
        $admin = User::where('email', 'admin@markuvo.com')->first();
        $this->assertTrue(password_verify('123456', $admin->password));
        $this->assertStringNotContainsString('password', $admin->toJson());
    }
}
