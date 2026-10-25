<?php

namespace Core;

use Core\View\Compiler;
use Core\View\Environment;

/**
 * Şablon motoru. app/Views/<ad>.vibe.php dosyalarını derler; derlenmiş PHP storage/cache/views
 * altına yazılır, kaynak değişmedikçe yeniden derlenmez.
 *
 *   view('home', ['title' => 'Merhaba']);       // nokta alt klasör demektir: view('admin.panel')
 */
class View
{
    public static function path(string $view): string
    {
        // Yol gezme (../) ve beklenmeyen karakterlere karşı: yalnızca harf, rakam, _ - . /
        if ($view === '' || !preg_match('#^[A-Za-z0-9_\-./]+$#', $view) || str_contains($view, '..') || str_contains($view, '//') || $view[0] === '/') {
            throw new \InvalidArgumentException("Geçersiz görünüm adı: {$view}");
        }

        return app()->basePath('app/Views/' . str_replace('.', '/', $view) . '.vibe.php');
    }

    public static function exists(string $view): bool
    {
        try {
            return is_file(static::path($view));
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    public static function render(string $view, array $data = []): string
    {
        $env = null;
        $env = new Environment(fn(string $v, array $d, Environment $e) => static::renderWith($v, $d, $e));

        return static::renderWith($view, $data, $env);
    }

    protected static function renderWith(string $view, array $data, Environment $env): string
    {
        $file = static::path($view);

        if (!is_file($file)) {
            throw new \RuntimeException("Görünüm bulunamadı: {$view} (aranan: app/Views/" . str_replace('.', '/', $view) . '.vibe.php)');
        }

        $compiled = static::compiled($file);
        $level = ob_get_level();

        // Bu şablonu içeren (henüz bitmemiş) üst şablonun @extends bilgisi karışmasın
        $outerExtends = $env->pullExtends();

        try {
            $output = static::evaluate($compiled, $data, $env);

            // Bu şablon @extends kullandıysa bölümleri toplandı; çıktı layout'tan gelir
            $layout = $env->pullExtends();
            if ($layout !== null) {
                $output = static::renderWith($layout, $data, $env);
            }

            return $output;
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        } finally {
            if ($outerExtends !== null) {
                $env->extend($outerExtends);
            }
        }
    }

    protected static function evaluate(string $compiledFile, array $__data, Environment $__env): string
    {
        $render = static function () use ($compiledFile, $__data, $__env) {
            extract($__data, EXTR_SKIP);
            ob_start();
            include $compiledFile;

            return (string) ob_get_clean();
        };

        return $render();
    }

    protected static function compiled(string $source): string
    {
        $dir = app()->storagePath('cache/views');
        $target = $dir . '/' . sha1($source . '|' . Compiler::VERSION) . '.php';

        if (is_file($target) && filemtime($target) >= filemtime($source)) {
            return $target;
        }

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Görünüm önbellek klasörü oluşturulamadı: {$dir}");
        }

        $code = (new Compiler())->compile((string) file_get_contents($source));
        $tmp = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';

        if (@file_put_contents($tmp, $code) === false) {
            throw new \RuntimeException("Derlenmiş görünüm yazılamadı: {$target}");
        }
        rename($tmp, $target);

        return $target;
    }
}
