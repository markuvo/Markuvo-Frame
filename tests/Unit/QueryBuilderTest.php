<?php

namespace Tests\Unit;

use Core\Database\DB;
use Core\Database\Expression;
use Core\Database\QueryBuilder;
use Tests\TestCase;

class QueryBuilderTest extends TestCase
{
    protected function seedPosts(): void
    {
        DB::statement('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, status TEXT, views INTEGER, created_at TEXT)');
        DB::table('posts')->insert([
            ['title' => 'A', 'status' => 'aktif', 'views' => 10, 'created_at' => '2026-01-01'],
            ['title' => 'B', 'status' => 'aktif', 'views' => 30, 'created_at' => '2026-01-02'],
            ['title' => 'C', 'status' => 'taslak', 'views' => 20, 'created_at' => '2026-01-03'],
            ['title' => 'D', 'status' => 'aktif', 'views' => 5, 'created_at' => '2026-01-04'],
        ]);
    }

    // ------------------------------------------------------------------ SQL üretimi (3 sürücü)

    public function testBasicSelectSqlForEachDriver(): void
    {
        $build = fn(string $driver) => (new QueryBuilder('posts', null, $driver))
            ->where('status', 'aktif')->orderBy('id', 'DESC')->limit(5);

        $this->assertSame('SELECT * FROM `posts` WHERE `status` = ? ORDER BY `id` DESC LIMIT 5', $build('mysql')->toSql());
        $this->assertSame('SELECT * FROM "posts" WHERE "status" = ? ORDER BY "id" DESC LIMIT 5', $build('pgsql')->toSql());
        $this->assertSame('SELECT * FROM "posts" WHERE "status" = ? ORDER BY "id" DESC LIMIT 5', $build('sqlite')->toSql());
    }

    public function testOffsetWithoutLimitIsDriverSpecific(): void
    {
        $sql = fn(string $d) => (new QueryBuilder('t', null, $d))->offset(10)->toSql();

        $this->assertStringContainsString('LIMIT 18446744073709551615 OFFSET 10', $sql('mysql'));
        $this->assertStringContainsString('LIMIT -1 OFFSET 10', $sql('sqlite'));
        $this->assertSame('SELECT * FROM "t" OFFSET 10', $sql('pgsql'));
    }

    public function testWhereVariants(): void
    {
        $q = (new QueryBuilder('t', null, 'sqlite'))
            ->where('a', 1)
            ->where('b', '>', 2)
            ->orWhere('c', 3)
            ->whereIn('d', [1, 2])
            ->whereNotIn('e', [])
            ->whereNull('f')
            ->whereNotNull('g')
            ->whereBetween('h', [1, 9]);

        $this->assertSame(
            'SELECT * FROM "t" WHERE "a" = ? AND "b" > ? OR "c" = ? AND "d" IN (?, ?) AND 1 = 1 AND "f" IS NULL AND "g" IS NOT NULL AND "h" BETWEEN ? AND ?',
            $q->toSql()
        );
        $this->assertSame([1, 2, 3, 1, 2, 1, 9], $q->getBindings());
    }

    public function testNestedWhereGroup(): void
    {
        $q = (new QueryBuilder('t', null, 'sqlite'))
            ->where('a', 1)
            ->where(fn($q) => $q->where('b', 2)->orWhere('c', 3));

        $this->assertSame('SELECT * FROM "t" WHERE "a" = ? AND ("b" = ? OR "c" = ?)', $q->toSql());
        $this->assertSame([1, 2, 3], $q->getBindings());
    }

    public function testWhereNullValueBecomesIsNull(): void
    {
        $q = (new QueryBuilder('t', null, 'sqlite'))->where('a', null)->where('b', '!=', null);

        $this->assertSame('SELECT * FROM "t" WHERE "a" IS NULL AND "b" IS NOT NULL', $q->toSql());
    }

    public function testArrayWhere(): void
    {
        $q = (new QueryBuilder('t', null, 'sqlite'))->where(['a' => 1, 'b' => 2]);

        $this->assertSame('SELECT * FROM "t" WHERE ("a" = ? AND "b" = ?)', $q->toSql());
    }

    public function testSameColumnTwiceKeepsBothBindings(): void
    {
        $q = (new QueryBuilder('t', null, 'sqlite'))->where('age', '>', 18)->where('age', '<', 65);

        $this->assertSame([18, 65], $q->getBindings());
    }

    public function testJoinAndSelectAndGroup(): void
    {
        $q = (new QueryBuilder('posts', null, 'mysql'))
            ->select('posts.id', 'users.name as author')
            ->join('users', 'users.id', '=', 'posts.user_id')
            ->groupBy('posts.id');

        $this->assertSame(
            'SELECT `posts`.`id`, `users`.`name` AS `author` FROM `posts` INNER JOIN `users` ON `users`.`id` = `posts`.`user_id` GROUP BY `posts`.`id`',
            $q->toSql()
        );
    }

    public function testSelectRawIsNotValidated(): void
    {
        $q = (new QueryBuilder('t', null, 'sqlite'))->selectRaw('COUNT(*) AS toplam');

        $this->assertSame('SELECT COUNT(*) AS toplam FROM "t"', $q->toSql());
    }

    // ------------------------------------------------------------------ Güvenlik

    public function testMaliciousColumnNameIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new QueryBuilder('t', null, 'sqlite'))->where('id; DROP TABLE users', 1)->toSql();
    }

    public function testMaliciousOperatorIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new QueryBuilder('t', null, 'sqlite'))->where('id', '= 1 OR 1=1 --', 1)->toSql();
    }

    public function testMaliciousOrderDirectionIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new QueryBuilder('t', null, 'sqlite'))->orderBy('id', 'DESC; DROP TABLE t');
    }

    public function testMaliciousOrderColumnIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new QueryBuilder('t', null, 'sqlite'))->orderBy('id) --')->toSql();
    }

    public function testValuesNeverEnterSql(): void
    {
        $evil = "x' OR '1'='1";
        $q = (new QueryBuilder('t', null, 'sqlite'))->where('name', $evil);

        $this->assertStringNotContainsString('OR', $q->toSql());
        $this->assertSame([$evil], $q->getBindings());
    }

    public function testMaliciousValueDoesNotInjectAgainstRealDatabase(): void
    {
        $this->seedPosts();

        $rows = DB::table('posts')->where('title', "A' OR '1'='1")->get();

        $this->assertCount(0, $rows);
        $this->assertSame(4, DB::table('posts')->count());
    }

    // ------------------------------------------------------------------ Gerçek veritabanı (SQLite)

    public function testGetReturnsCollectionOfObjects(): void
    {
        $this->seedPosts();

        $rows = DB::table('posts')->where('status', 'aktif')->orderBy('id', 'DESC')->get();

        $this->assertCount(3, $rows);
        $this->assertSame('D', $rows->first()->title);
        $this->assertSame(['D', 'B', 'A'], $rows->pluck('title')->all());
    }

    public function testFirstFindAndValue(): void
    {
        $this->seedPosts();

        $this->assertSame('C', DB::table('posts')->where('status', 'taslak')->first()->title);
        $this->assertNull(DB::table('posts')->where('title', 'yok')->first());
        $this->assertSame('B', DB::table('posts')->find(2)->title);
        $this->assertSame('B', DB::table('posts')->where('id', 2)->value('title'));
    }

    public function testTwoQueriesDoNotInterfere(): void
    {
        $this->seedPosts();

        $a = DB::table('posts')->where('status', 'aktif');
        $b = DB::table('posts')->where('status', 'taslak');

        $this->assertSame(3, $a->count());
        $this->assertSame(1, $b->count());
        $this->assertSame(3, $a->count());
    }

    public function testAggregates(): void
    {
        $this->seedPosts();

        $this->assertSame(4, DB::table('posts')->count());
        $this->assertEquals(30, DB::table('posts')->max('views'));
        $this->assertEquals(5, DB::table('posts')->min('views'));
        $this->assertEquals(65, DB::table('posts')->sum('views'));
        $this->assertEquals(16.25, DB::table('posts')->avg('views'));
        $this->assertTrue(DB::table('posts')->where('status', 'taslak')->exists());
        $this->assertFalse(DB::table('posts')->where('status', 'yok')->exists());
    }

    public function testPluckWithKey(): void
    {
        $this->seedPosts();

        $this->assertSame([1 => 'A', 2 => 'B'], DB::table('posts')->where('id', '<=', 2)->pluck('title', 'id')->all());
    }

    public function testInsertGetIdUpdateDelete(): void
    {
        $this->seedPosts();

        $id = DB::table('posts')->insertGetId(['title' => 'E', 'status' => 'aktif', 'views' => 1, 'created_at' => 'x']);
        $this->assertSame(5, $id);

        $affected = DB::table('posts')->where('status', 'aktif')->update(['views' => 0]);
        $this->assertSame(4, $affected);
        $this->assertEquals(0, DB::table('posts')->sum('views') - 20);

        $this->assertSame(1, DB::table('posts')->where('id', $id)->delete());
        $this->assertSame(4, DB::table('posts')->count());
    }

    public function testLatestOrderingAndPaging(): void
    {
        $this->seedPosts();

        $page2 = DB::table('posts')->orderBy('id')->forPage(2, 3)->get();

        $this->assertSame(['D'], $page2->pluck('title')->all());
        $this->assertSame(['B', 'C'], DB::table('posts')->orderBy('id')->offset(1)->limit(2)->pluck('title')->all());
        $this->assertSame(['B', 'C', 'D'], DB::table('posts')->orderBy('id')->offset(1)->pluck('title')->all());
    }

    public function testWhenHelper(): void
    {
        $this->seedPosts();

        $filter = null;
        $count = DB::table('posts')->when($filter, fn($q, $v) => $q->where('status', $v))->count();
        $this->assertSame(4, $count);

        $filter = 'taslak';
        $count = DB::table('posts')->when($filter, fn($q, $v) => $q->where('status', $v))->count();
        $this->assertSame(1, $count);
    }

    public function testBooleanAndNullBindings(): void
    {
        DB::statement('CREATE TABLE flags (id INTEGER PRIMARY KEY AUTOINCREMENT, on_off INTEGER, note TEXT)');
        DB::table('flags')->insert(['on_off' => true, 'note' => null]);

        $row = DB::table('flags')->first();
        $this->assertEquals(1, $row->on_off);
        $this->assertNull($row->note);
    }

    public function testTransactionCommitAndRollback(): void
    {
        DB::statement('CREATE TABLE t (id INTEGER PRIMARY KEY, v TEXT)');

        DB::transaction(fn() => DB::table('t')->insert(['v' => 'ok']));
        $this->assertSame(1, DB::table('t')->count());

        try {
            DB::transaction(function () {
                DB::table('t')->insert(['v' => 'iptal']);
                throw new \RuntimeException('geri al');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(1, DB::table('t')->count());
    }

    public function testNestedTransactionRollsBackEverything(): void
    {
        DB::statement('CREATE TABLE t (id INTEGER PRIMARY KEY, v TEXT)');

        try {
            DB::transaction(function () {
                DB::table('t')->insert(['v' => 'dis']);
                DB::transaction(fn() => DB::table('t')->insert(['v' => 'ic']));
                throw new \RuntimeException('x');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, DB::table('t')->count());
    }

    public function testQueryErrorIsWrappedAndLoggedToDbErrorsLog(): void
    {
        try {
            DB::table('olmayan_tablo')->get();
            $this->fail('QueryException bekleniyordu');
        } catch (\Core\Database\QueryException $e) {
            $this->assertStringContainsString('olmayan_tablo', $e->getSql());
        }

        $log = $this->storage . '/logs/db_errors.log';
        $this->assertFileExists($log);
        $this->assertStringContainsString('olmayan_tablo', file_get_contents($log));
    }

    public function testQueryErrorLogDoesNotContainBindingValues(): void
    {
        try {
            DB::table('olmayan')->where('sifre', 'COK-GIZLI-DEGER')->get();
        } catch (\Core\Database\QueryException) {
        }

        $this->assertStringNotContainsString('COK-GIZLI-DEGER', file_get_contents($this->storage . '/logs/db_errors.log'));
    }

    public function testCloneKeepsQueriesIndependent(): void
    {
        $this->seedPosts();

        $base = DB::table('posts')->where('status', 'aktif');
        $limited = (clone $base)->limit(1);

        $this->assertSame(3, $base->count());
        $this->assertSame(1, $limited->get()->count());
    }

    public function testEmptyWhereInMatchesNothing(): void
    {
        $this->seedPosts();

        $this->assertSame(0, DB::table('posts')->whereIn('id', [])->count());
        $this->assertSame(4, DB::table('posts')->whereNotIn('id', [])->count());
    }

    public function testDriverSwitch(): void
    {
        $this->assertSame('sqlite', DB::driver());
    }

    public function testDriverErrorMessagesDoNotLeakValues(): void
    {
        $this->assertStringNotContainsString('gizli@x.com', \Core\Database\QueryException::redact("Duplicate entry 'gizli@x.com' for key 'users.email'"));
        $this->assertStringNotContainsString('gizli@x.com', \Core\Database\QueryException::redact("DETAIL:  Key (email)=(gizli@x.com) already exists."));
        $this->assertStringNotContainsString('abc-gizli', \Core\Database\QueryException::redact('invalid input syntax for type integer: "abc-gizli"'));
    }
}
