<?php

/**
 * Bağımlılıksız test çalıştırıcı.
 *   php tests/runner.php            tüm testler
 *   php tests/runner.php Query      adında "Query" geçen test sınıfları
 *   php tests/runner.php Model::testFind   tek test
 *
 * PHPUnit kuruluysa (composer require --dev phpunit/phpunit) ondan da çalıştırılabilir:
 *   vendor/bin/phpunit
 */

require __DIR__ . '/bootstrap.php';

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\SkippedTestError;
use PHPUnit\Framework\TestCase;

if (!is_subclass_of(TestCase::class, TestCase::class) && !method_exists(TestCase::class, 'runSetUp')) {
    fwrite(STDERR, "Bu çalıştırıcı yalnızca dahili uyumluluk katmanıyla çalışır. PHPUnit kuruluysa: vendor/bin/phpunit\n");
    exit(2);
}

$filter = $argv[1] ?? null;
[$classFilter, $methodFilter] = array_pad(explode('::', (string) $filter, 2), 2, null);

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS));
$testFiles = [];
foreach ($files as $file) {
    if (str_ends_with($file->getFilename(), 'Test.php')) {
        $testFiles[] = $file->getPathname();
    }
}
sort($testFiles);

$passed = $failed = $skipped = 0;
$failures = [];
$started = microtime(true);

foreach ($testFiles as $path) {
    require_once $path;
}

foreach (get_declared_classes() as $class) {
    $ref = new ReflectionClass($class);

    if ($ref->isAbstract() || !$ref->isSubclassOf(TestCase::class) || !str_ends_with($class, 'Test')) {
        continue;
    }

    if ($classFilter && stripos($class, $classFilter) === false) {
        continue;
    }

    foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if (!str_starts_with($method->name, 'test')) {
            continue;
        }

        if ($methodFilter && $method->name !== $methodFilter) {
            continue;
        }

        $label = $ref->getShortName() . '::' . $method->name;
        $test = $ref->newInstance();

        $error = null;
        $expected = null;

        try {
            $test->runSetUp();

            try {
                $test->{$method->name}();

                if ($test->expectedException() !== null) {
                    throw new AssertionFailedError('Beklenen istisna fırlatılmadı: ' . $test->expectedException());
                }
            } catch (SkippedTestError | AssertionFailedError $e) {
                throw $e;
            } catch (Throwable $e) {
                $expected = $test->expectedException();
                if ($expected === null || !($e instanceof $expected)) {
                    throw $e;
                }
                if ($test->expectedMessage() !== null && !str_contains($e->getMessage(), $test->expectedMessage())) {
                    throw new AssertionFailedError("İstisna mesajı '{$test->expectedMessage()}' içermiyor: " . $e->getMessage());
                }
            }

            $passed++;
            echo '.';
        } catch (SkippedTestError $e) {
            $skipped++;
            echo 'S';
        } catch (Throwable $e) {
            $failed++;
            echo 'F';
            $failures[] = [$label, $e];
        } finally {
            try { $test->runTearDown(); } catch (Throwable) {}
        }
    }
}

echo PHP_EOL . PHP_EOL;

foreach ($failures as $i => [$label, $e]) {
    echo ($i + 1) . ") {$label}" . PHP_EOL;
    echo '   ' . get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
    echo '   ' . str_replace(dirname(__DIR__) . '/', '', $e->getFile()) . ':' . $e->getLine() . PHP_EOL . PHP_EOL;
}

printf("%d geçti, %d başarısız, %d atlandı (%d doğrulama, %.2f sn)%s", $passed, $failed, $skipped, TestCase::$assertions, microtime(true) - $started, PHP_EOL);
exit($failed === 0 ? 0 : 1);
