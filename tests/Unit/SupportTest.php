<?php

namespace Tests\Unit;

use Core\Config;
use Core\Container;
use Core\Support\Arr;
use Core\Support\Collection;
use Core\Support\Paginator;
use Core\Support\Str;
use PHPUnit\Framework\TestCase;

class SupportTest extends TestCase
{
    public function testStrPluralAndSnake(): void
    {
        $this->assertSame('users', Str::plural('user'));
        $this->assertSame('categories', Str::plural('category'));
        $this->assertSame('boxes', Str::plural('box'));
        $this->assertSame('people', Str::plural('person'));
        $this->assertSame('blog_posts', Str::plural('blog_post'));
        $this->assertSame('news', Str::plural('news'));
        $this->assertSame('user_profile', Str::snake('UserProfile'));
        $this->assertSame('UserProfile', Str::studly('user_profile'));
    }

    public function testStrSlugHandlesTurkishLetters(): void
    {
        $this->assertSame('cok-guzel-bir-baslik', Str::slug('Çok Güzel Bir Başlık!'));
    }

    public function testArrDotNotation(): void
    {
        $a = ['a' => ['b' => ['c' => 1]], 'x.y' => 2];

        $this->assertSame(1, Arr::get($a, 'a.b.c'));
        $this->assertSame(2, Arr::get($a, 'x.y'));
        $this->assertSame('d', Arr::get($a, 'a.z', 'd'));
        $this->assertTrue(Arr::has($a, 'a.b'));
        $this->assertFalse(Arr::has($a, 'a.q'));

        Arr::set($a, 'n.m', 5);
        $this->assertSame(5, $a['n']['m']);
        $this->assertSame(['a' => 1], Arr::only(['a' => 1, 'b' => 2], ['a']));
        $this->assertSame(['b' => 2], Arr::except(['a' => 1, 'b' => 2], ['a']));
    }

    public function testCollectionBasics(): void
    {
        $c = new Collection([['n' => 'a', 'v' => 3], ['n' => 'b', 'v' => 1], ['n' => 'c', 'v' => 2]]);

        $this->assertCount(3, $c);
        $this->assertSame(['a', 'b', 'c'], $c->pluck('n')->all());
        $this->assertSame(['b', 'c', 'a'], $c->sortBy('v')->pluck('n')->values()->all());
        $this->assertSame('a', $c->first()['n']);
        $this->assertSame('c', $c->last()['n']);
        $this->assertSame(6, $c->reduce(fn($carry, $i) => $carry + $i['v'], 0));
        $this->assertSame(['a', 'c'], $c->filter(fn($i) => $i['v'] > 1)->pluck('n')->values()->all());
        $this->assertSame([6, 2, 4], $c->map(fn($i) => $i['v'] * 2)->all());
        $this->assertTrue($c->isNotEmpty());
        $this->assertSame('a, b, c', $c->implode(', ', 'n'));
        $this->assertSame('[1,2]', (new Collection([1, 2]))->toJson());
    }

    public function testCollectionIsIterableAndArrayAccessible(): void
    {
        $c = new Collection(['x', 'y']);
        $seen = [];
        foreach ($c as $k => $v) {
            $seen[] = "$k$v";
        }

        $this->assertSame(['0x', '1y'], $seen);
        $this->assertSame('y', $c[1]);
        $c[] = 'z';
        $this->assertCount(3, $c);
    }

    public function testConfigDotAccess(): void
    {
        $config = new Config();
        $config->set('db.host', 'x');

        $this->assertSame('x', $config->get('db.host'));
        $this->assertSame('d', $config->get('db.port', 'd'));
        $this->assertTrue($config->has('db.host'));
    }

    public function testPaginatorLinksAreEscapedAndWindowed(): void
    {
        $p = new Paginator(new Collection([1, 2]), 100, 10, 5, ['path' => '/blog', 'query' => ['q' => '"><script>']]);

        $html = $p->links();

        $this->assertSame(10, $p->lastPage());
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('page=4', $html);
        $this->assertStringContainsString('class="active"', $html);
        $this->assertStringContainsString('&hellip;', $html);
        $this->assertSame('', (new Paginator(new Collection(), 3, 10, 1))->links());
    }

    public function testPaginatorUrlsPreserveQuery(): void
    {
        $p = new Paginator(new Collection(), 50, 10, 2, ['path' => '/x', 'query' => ['ara' => 'php']]);

        $this->assertSame('/x?ara=php&page=3', $p->url(3));
        $this->assertTrue($p->hasMorePages());
        $this->assertFalse($p->onFirstPage());
    }

    // ------------------------------------------------------------------ Container

    public function testContainerAutowiresConstructorDependencies(): void
    {
        $c = new Container();
        $obj = $c->make(NeedsCollection::class);

        $this->assertInstanceOf(Collection::class, $obj->collection);
        $this->assertSame(5, $obj->limit);
    }

    public function testContainerSingletonAndInstance(): void
    {
        $c = new Container();
        $c->singleton('x', fn() => new \stdClass());

        $this->assertSame($c->make('x'), $c->make('x'));

        $obj = new \stdClass();
        $c->instance('y', $obj);
        $this->assertSame($obj, $c->make('y'));
    }

    public function testContainerBindInterface(): void
    {
        $c = new Container();
        $c->bind(\Countable::class, Collection::class);

        $this->assertInstanceOf(Collection::class, $c->make(\Countable::class));
    }

    public function testContainerUnresolvableParameterThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        (new Container())->make(NeedsScalar::class);
    }

    public function testContainerDetectsCircularDependency(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('döngüsel');
        (new Container())->make(CircularA::class);
    }

    public function testContainerCallResolvesByNameTypeAndPosition(): void
    {
        $c = new Container();

        // isimle + tür dönüştürme
        $r = $c->call(fn(int $id, Collection $c) => [$id, get_class($c)], ['id' => '42']);
        $this->assertSame([42, Collection::class], $r);

        // eski stil: konumsal (parametre adı rota adıyla aynı değil)
        $c->singleton(\Core\System\Request::class, fn() => new \Core\System\Request([], [], [], ['REQUEST_METHOD' => 'GET']));
        $r = $c->call(fn($request, $kullaniciId) => [get_class($request), $kullaniciId], ['id' => '7']);
        $this->assertSame([\Core\System\Request::class, '7'], $r);

        // varsayılan değer
        $this->assertSame('d', $c->call(fn($x = 'd') => $x, []));
    }
}

class NeedsCollection
{
    public function __construct(public Collection $collection, public int $limit = 5)
    {
    }
}

class NeedsScalar
{
    public function __construct(public string $mustBeGiven)
    {
    }
}

class CircularA
{
    public function __construct(public CircularB $b)
    {
    }
}

class CircularB
{
    public function __construct(public CircularA $a)
    {
    }
}
