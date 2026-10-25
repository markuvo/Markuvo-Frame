<?php

/**
 * PHPUnit kurulu değilse (composer require --dev phpunit/phpunit yapılmadıysa) testlerin yine
 * de çalışması için küçük bir uyumluluk katmanı. PHPUnit kuruluysa bu dosya hiçbir şey yapmaz.
 * Yalnızca bu projenin testlerinin kullandığı assert metotlarını içerir.
 */

namespace PHPUnit\Framework {

    if (!class_exists(TestCase::class, false)) {

        class AssertionFailedError extends \Exception {}

        class SkippedTestError extends \Exception {}

        abstract class TestCase
        {
            public static int $assertions = 0;
            private ?string $expectedException = null;
            private ?string $expectedMessage = null;

            protected function setUp(): void {}
            protected function tearDown(): void {}

            /** Çalıştırıcı için dışarıdan erişim */
            public function runSetUp(): void { $this->setUp(); }
            public function runTearDown(): void { $this->tearDown(); }
            public function expectedException(): ?string { return $this->expectedException; }
            public function expectedMessage(): ?string { return $this->expectedMessage; }

            public function expectException(string $class): void { $this->expectedException = $class; }
            public function expectExceptionMessage(string $message): void { $this->expectedMessage = $message; }

            public function markTestSkipped(string $message = ''): never { throw new SkippedTestError($message); }
            public static function fail(string $message = ''): never { throw new AssertionFailedError($message ?: 'fail() çağrıldı'); }

            private static function ok(bool $condition, string $message): void
            {
                self::$assertions++;
                if (!$condition) {
                    throw new AssertionFailedError($message);
                }
            }

            private static function d(mixed $v): string
            {
                return is_scalar($v) || $v === null ? var_export($v, true) : (is_object($v) ? get_class($v) : 'array(' . count((array) $v) . ')');
            }

            public static function assertTrue(mixed $v, string $m = ''): void { self::ok($v === true, $m ?: 'true bekleniyordu, gelen: ' . self::d($v)); }
            public static function assertFalse(mixed $v, string $m = ''): void { self::ok($v === false, $m ?: 'false bekleniyordu, gelen: ' . self::d($v)); }
            public static function assertNull(mixed $v, string $m = ''): void { self::ok($v === null, $m ?: 'null bekleniyordu, gelen: ' . self::d($v)); }
            public static function assertNotNull(mixed $v, string $m = ''): void { self::ok($v !== null, $m ?: 'null olmaması bekleniyordu'); }
            public static function assertSame(mixed $e, mixed $a, string $m = ''): void { self::ok($e === $a, $m ?: 'Aynı olması bekleniyordu. Beklenen: ' . self::d($e) . ' | Gelen: ' . self::d($a) . (is_array($e) || is_array($a) ? "\n  " . json_encode($e) . "\n  " . json_encode($a) : '')); }
            public static function assertNotSame(mixed $e, mixed $a, string $m = ''): void { self::ok($e !== $a, $m ?: 'Farklı olması bekleniyordu'); }
            public static function assertEquals(mixed $e, mixed $a, string $m = ''): void { self::ok($e == $a, $m ?: 'Eşit olması bekleniyordu. Beklenen: ' . self::d($e) . ' | Gelen: ' . self::d($a) . (is_array($e) || is_array($a) ? "\n  " . json_encode($e) . "\n  " . json_encode($a) : '')); }
            public static function assertNotEquals(mixed $e, mixed $a, string $m = ''): void { self::ok($e != $a, $m ?: 'Eşit olmaması bekleniyordu'); }
            public static function assertCount(int $n, \Countable|iterable $c, string $m = ''): void { $count = is_countable($c) ? count($c) : iterator_count($c); self::ok($count === $n, $m ?: "Eleman sayısı {$n} bekleniyordu, gelen: {$count}"); }
            public static function assertEmpty(mixed $v, string $m = ''): void { self::ok(empty($v), $m ?: 'Boş olması bekleniyordu'); }
            public static function assertNotEmpty(mixed $v, string $m = ''): void { self::ok(!empty($v), $m ?: 'Boş olmaması bekleniyordu'); }
            public static function assertInstanceOf(string $class, mixed $v, string $m = ''): void { self::ok($v instanceof $class, $m ?: "{$class} örneği bekleniyordu, gelen: " . self::d($v)); }
            public static function assertNotInstanceOf(string $class, mixed $v, string $m = ""): void { self::ok(!($v instanceof $class), $m ?: "{$class} örneği olmaması bekleniyordu"); }
            public static function assertIsArray(mixed $v, string $m = ''): void { self::ok(is_array($v), $m ?: 'dizi bekleniyordu'); }
            public static function assertIsString(mixed $v, string $m = ''): void { self::ok(is_string($v), $m ?: 'metin bekleniyordu'); }
            public static function assertIsInt(mixed $v, string $m = ''): void { self::ok(is_int($v), $m ?: 'int bekleniyordu, gelen: ' . self::d($v)); }
            public static function assertStringContainsString(string $needle, string $hay, string $m = ''): void { self::ok(str_contains($hay, $needle), $m ?: "'{$needle}' içermesi bekleniyordu. Gelen: " . substr($hay, 0, 300)); }
            public static function assertStringNotContainsString(string $needle, string $hay, string $m = ''): void { self::ok(!str_contains($hay, $needle), $m ?: "'{$needle}' içermemesi bekleniyordu"); }
            public static function assertStringStartsWith(string $p, string $s, string $m = ''): void { self::ok(str_starts_with($s, $p), $m ?: "'{$p}' ile başlaması bekleniyordu. Gelen: " . substr($s, 0, 100)); }
            public static function assertStringEndsWith(string $p, string $s, string $m = ''): void { self::ok(str_ends_with($s, $p), $m ?: "'{$p}' ile bitmesi bekleniyordu"); }
            public static function assertMatchesRegularExpression(string $re, string $s, string $m = ''): void { self::ok(preg_match($re, $s) === 1, $m ?: "{$re} eşleşmesi bekleniyordu. Gelen: " . substr($s, 0, 200)); }
            public static function assertArrayHasKey(int|string $k, array|\ArrayAccess $a, string $m = ''): void { self::ok(is_array($a) ? array_key_exists($k, $a) : isset($a[$k]), $m ?: "'{$k}' anahtarı bekleniyordu"); }
            public static function assertArrayNotHasKey(int|string $k, array|\ArrayAccess $a, string $m = ''): void { self::ok(is_array($a) ? !array_key_exists($k, $a) : !isset($a[$k]), $m ?: "'{$k}' anahtarı olmaması bekleniyordu"); }
            public static function assertContains(mixed $n, iterable $h, string $m = ''): void { $f = false; foreach ($h as $x) { if ($x === $n) { $f = true; break; } } self::ok($f, $m ?: 'Değer dizide bekleniyordu: ' . self::d($n)); }
            public static function assertNotContains(mixed $n, iterable $h, string $m = ''): void { $f = false; foreach ($h as $x) { if ($x === $n) { $f = true; break; } } self::ok(!$f, $m ?: 'Değer dizide olmaması bekleniyordu: ' . self::d($n)); }
            public static function assertGreaterThan(mixed $e, mixed $a, string $m = ''): void { self::ok($a > $e, $m ?: self::d($a) . ' > ' . self::d($e) . ' bekleniyordu'); }
            public static function assertGreaterThanOrEqual(mixed $e, mixed $a, string $m = ''): void { self::ok($a >= $e, $m ?: self::d($a) . ' >= ' . self::d($e) . ' bekleniyordu'); }
            public static function assertLessThan(mixed $e, mixed $a, string $m = ''): void { self::ok($a < $e, $m ?: self::d($a) . ' < ' . self::d($e) . ' bekleniyordu'); }
            public static function assertFileExists(string $f, string $m = ''): void { self::ok(file_exists($f), $m ?: "Dosya bekleniyordu: {$f}"); }
            public static function assertFileDoesNotExist(string $f, string $m = ''): void { self::ok(!file_exists($f), $m ?: "Dosya olmaması bekleniyordu: {$f}"); }
            public static function assertDirectoryExists(string $f, string $m = ''): void { self::ok(is_dir($f), $m ?: "Klasör bekleniyordu: {$f}"); }
            public static function assertJson(string $s, string $m = ''): void { json_decode($s); self::ok(json_last_error() === JSON_ERROR_NONE, $m ?: 'Geçerli JSON bekleniyordu'); }
        }
    }
}
