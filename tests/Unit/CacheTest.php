<?php

namespace Tests\Unit;

use Core\System\Cache\ArrayStore;
use Core\System\Cache\CacheManager;
use Core\System\Cache\FileStore;
use Core\System\Cache\RedisStore;
use Core\System\Cache\Store;
use Core\System\RateLimiter;
use Tests\TestCase;

class CacheTest extends TestCase
{
    /** @return Store[] */
    protected function stores(): array
    {
        $stores = [
            'array' => new ArrayStore(),
            'file' => new FileStore($this->storage . '/cache/t_' . uniqid()),
        ];

        $port = (int) (getenv('TEST_REDIS_PORT') ?: 6390);
        if (extension_loaded('redis') && @fsockopen('127.0.0.1', $port)) {
            $stores['redis'] = new RedisStore(['host' => '127.0.0.1', 'port' => $port, 'database' => 15], 'test' . uniqid());
        }

        return $stores;
    }

    public function testBasicOperationsOnEveryStore(): void
    {
        foreach ($this->stores() as $name => $s) {
            $s->put('a', ['x' => 1, 'y' => 'ş'], 60);
            $this->assertSame(['x' => 1, 'y' => 'ş'], $s->get('a'), $name);
            $this->assertTrue($s->has('a'), $name);
            $this->assertNull($s->get('yok'), $name);

            $s->put('f', 0, 0);                      // 0 değeri ve süresiz
            $this->assertSame(0, $s->get('f'), $name);

            $s->forget('a');
            $this->assertFalse($s->has('a'), $name);

            $s->flush();
            $this->assertNull($s->get('f'), $name);
        }
    }

    public function testExpiry(): void
    {
        foreach ($this->stores() as $name => $s) {
            $s->put('k', 'v', 1);
            $this->assertSame('v', $s->get('k'), $name);
            sleep(2);
            $this->assertNull($s->get('k'), "$name süresi dolmalı");
        }
    }

    public function testIncrement(): void
    {
        foreach ($this->stores() as $name => $s) {
            $this->assertSame(1, $s->increment('c', 1, 60), $name);
            $this->assertSame(2, $s->increment('c', 1, 60), $name);
            $this->assertSame(7, $s->increment('c', 5, 60), $name);
        }
    }

    public function testManagerRememberAndAdd(): void
    {
        $c = new CacheManager(['default' => 'array'], $this->storage);
        $calls = 0;
        $make = function () use (&$calls) { $calls++; return 'deger'; };

        $this->assertSame('deger', $c->remember('r', 60, $make));
        $this->assertSame('deger', $c->remember('r', 60, $make));
        $this->assertSame(1, $calls);

        $this->assertTrue($c->add('n', 1));
        $this->assertFalse($c->add('n', 2));
        $this->assertSame('varsayılan', $c->get('yok', 'varsayılan'));
        $this->assertSame('lazy', $c->get('yok', fn() => 'lazy'));
    }

    public function testManagerRejectsUnknownDriver(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CacheManager([], $this->storage))->store('memcached');
    }

    public function testFileStoreDoesNotInstantiateObjects(): void
    {
        $s = new FileStore($this->storage . '/cache/obj');
        $s->put('o', new \ArrayObject([1]), 60);

        $this->assertNotInstanceOf(\ArrayObject::class, $s->get('o'));
    }

    public function testFileStoreSurvivesCorruptFile(): void
    {
        $s = new FileStore($this->storage . '/cache/bozuk');
        $s->put('k', 'v', 60);
        foreach (glob($this->storage . '/cache/bozuk/*/*.cache') as $f) {
            file_put_contents($f, 'çöp');
        }

        $this->assertNull($s->get('k'));
    }

    public function testRateLimiter(): void
    {
        $l = new RateLimiter(new CacheManager(['default' => 'array'], $this->storage));

        for ($i = 0; $i < 3; $i++) {
            $l->hit('ip:1', 60);
        }

        $this->assertTrue($l->tooManyAttempts('ip:1', 3));
        $this->assertFalse($l->tooManyAttempts('ip:2', 3));

        $l->clear('ip:1');
        $this->assertFalse($l->tooManyAttempts('ip:1', 3));
    }
}
