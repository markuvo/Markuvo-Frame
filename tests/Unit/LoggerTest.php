<?php

namespace Tests\Unit;

use Core\System\Logger;
use Tests\TestCase;

class LoggerTest extends TestCase
{
    public function testWritesLevelsChannelsAndMasksSecrets(): void
    {
        $log = new Logger($this->storage . '/logs');
        $log->info('giriş', ['password' => 'GIZLI', 'user' => 'ali', 'nested' => ['api_key' => 'K', 'ok' => 1]]);
        $log->channel('db_errors')->error('boom');

        $app = file_get_contents($this->storage . '/logs/app.log');
        $this->assertStringContainsString('app.INFO: giriş', $app);
        $this->assertStringNotContainsString('GIZLI', $app);
        $this->assertStringNotContainsString('"api_key":"K"', $app);
        $this->assertStringContainsString('"user":"ali"', $app);
        $this->assertStringContainsString('db_errors.ERROR: boom', file_get_contents($this->storage . '/logs/db_errors.log'));
    }

    public function testMinimumLevelAndDaily(): void
    {
        $log = new Logger($this->storage . '/logs', 'x', true, 'warning');
        $log->info('görünmez');
        $log->error('görünür');

        $file = $this->storage . '/logs/x-' . date('Y-m-d') . '.log';
        $this->assertStringNotContainsString('görünmez', file_get_contents($file));
        $this->assertStringContainsString('görünür', file_get_contents($file));
    }

    public function testNewlinesCannotForgeLogLines(): void
    {
        (new Logger($this->storage . '/logs'))->error("a\n[2000-01-01 00:00:00] app.ERROR: sahte");

        $lines = file($this->storage . '/logs/app.log');
        $this->assertCount(1, $lines);
    }

    public function testChannelNameCannotEscapeDirectory(): void
    {
        (new Logger($this->storage . '/logs'))->channel('../../evil')->error('x');

        $this->assertTrue(is_file($this->storage . '/logs/evil.log'));
    }
}
