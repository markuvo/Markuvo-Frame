<?php

namespace Core\View;

/**
 * Vibe şablonlarını (.vibe.php) saf PHP'ye çevirir. Sonuç diske yazılır ve include edilir (eval yok).
 *
 *  {{ $x }}   kaçışlı yazdırır (XSS koruması)       {!! $x !!}  ham HTML      {{-- yorum --}}
 *  @{{ x }}   olduğu gibi bırakır                   @@          '@' karakteri
 *  @extends @section @endsection @yield @include @push @endpush @stack
 *  @if @elseif @else @endif @unless @isset @empty @foreach @forelse @for @while @break @continue
 *  @auth @guest @error @csrf @method @json @php ... @endphp @verbatim ... @endverbatim
 */
class Compiler
{
    public const VERSION = 2;

    /** Parantez alan yönergeler -> PHP karşılığı ({0} = parantez içi) */
    protected const WITH_ARGS = [
        'extends' => '<?php $__env->extend({0}); ?>',
        'section' => null, // özel
        'yield' => '<?php echo $__env->yieldContent({0}); ?>',
        'include' => '<?php echo $__env->make({0}, get_defined_vars()); ?>',
        'push' => '<?php $__env->startPush({0}); ?>',
        'stack' => '<?php echo $__env->yieldPush({0}); ?>',
        'if' => '<?php if ({0}): ?>',
        'elseif' => '<?php elseif ({0}): ?>',
        'unless' => '<?php if (!({0})): ?>',
        'isset' => '<?php if (isset({0})): ?>',
        'empty' => '<?php if (empty({0})): ?>',
        'foreach' => '<?php foreach ({0}): ?>',
        'for' => '<?php for ({0}): ?>',
        'while' => '<?php while ({0}): ?>',
        'csrf' => null,
        'method' => '<?php echo method_field({0}); ?>',
        'json' => '<?php echo json_encode({0}, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>',
        'error' => '<?php if (has_error({0})): $message = error({0}); ?>',
        'forelse' => null,
        'php' => '<?php {0}; ?>',
    ];

    protected const BARE = [
        'else' => '<?php else: ?>',
        'endif' => '<?php endif; ?>',
        'endunless' => '<?php endif; ?>',
        'endisset' => '<?php endif; ?>',
        'endforeach' => '<?php endforeach; ?>',
        'endfor' => '<?php endfor; ?>',
        'endwhile' => '<?php endwhile; ?>',
        'endsection' => '<?php $__env->stopSection(); ?>',
        'show' => '<?php echo $__env->stopSection(true); ?>',
        'endpush' => '<?php $__env->stopPush(); ?>',
        'auth' => '<?php if (auth()->check()): ?>',
        'endauth' => '<?php endif; ?>',
        'guest' => '<?php if (auth()->guest()): ?>',
        'endguest' => '<?php endif; ?>',
        'enderror' => '<?php unset($message); endif; ?>',
        'csrf' => '<?php echo csrf_field(); ?>',
        'break' => '<?php break; ?>',
        'continue' => '<?php continue; ?>',
    ];

    protected array $forelseStack = [];
    protected int $forelseCounter = 0;

    public function compile(string $source): string
    {
        $this->forelseStack = [];
        $this->forelseCounter = 0;

        $re = '/\{\{--|@\{\{|\{!!|\{\{|(?<![\w@])@@|(?<![\w@])@(\w+)/';
        $out = '';
        $pos = 0;
        $len = strlen($source);

        while ($pos < $len && preg_match($re, $source, $m, PREG_OFFSET_CAPTURE, $pos)) {
            [$token, $offset] = $m[0];
            $out .= substr($source, $pos, $offset - $pos);
            $pos = $offset + strlen($token);

            if ($token === '{{--') {
                $end = strpos($source, '--}}', $pos);
                $pos = $end === false ? $len : $end + 4;
            } elseif ($token === '@{{') {
                $end = strpos($source, '}}', $pos);
                $end = $end === false ? $len : $end + 2;
                $out .= '{{' . substr($source, $pos, $end - $pos);
                $pos = $end;
            } elseif ($token === '{!!') {
                $end = strpos($source, '!!}', $pos);
                if ($end === false) {
                    $out .= $token;
                    continue;
                }
                $out .= '<?php echo ' . trim(substr($source, $pos, $end - $pos)) . '; ?>';
                $pos = $end + 3;
            } elseif ($token === '{{') {
                $end = strpos($source, '}}', $pos);
                if ($end === false) {
                    $out .= $token;
                    continue;
                }
                $out .= $this->echoTag(trim(substr($source, $pos, $end - $pos)));
                $pos = $end + 2;
            } elseif ($token === '@@') {
                $out .= '@';
            } else {
                $out .= $this->directive($m[1][0], $source, $pos, $token);
            }
        }

        return $out . substr($source, $pos);
    }

    /** {{ $x }} -> e($x ?? '')  (basit değişken/dizi/özellik zincirleri tanımsız olsa da uyarı vermez) */
    protected function echoTag(string $expr): string
    {
        if (preg_match('/^\$\w+(?:\[[^\]]+\]|->\w+|\?->\w+)*$/', $expr) === 1) {
            return '<?php echo e(' . $expr . " ?? ''); ?>";
        }

        return '<?php echo e(' . $expr . '); ?>';
    }

    protected function directive(string $name, string $src, int &$pos, string $literal): string
    {
        // @php ... @endphp  ve  @verbatim ... @endverbatim
        if ($name === 'php' && !preg_match('/\G\s*\(/', $src, $_, 0, $pos)) {
            $end = strpos($src, '@endphp', $pos);
            $code = $end === false ? substr($src, $pos) : substr($src, $pos, $end - $pos);
            $pos = $end === false ? strlen($src) : $end + 7;

            return '<?php ' . trim($code) . ' ?>';
        }
        if ($name === 'verbatim') {
            $end = strpos($src, '@endverbatim', $pos);
            $raw = $end === false ? substr($src, $pos) : substr($src, $pos, $end - $pos);
            $pos = $end === false ? strlen($src) : $end + 12;

            return $raw;
        }

        // forelse parçaları
        if ($name === 'endforelse') {
            array_pop($this->forelseStack);

            return '<?php endif; ?>';
        }
        if ($name === 'empty' && !preg_match('/\G\s*\(/', $src, $_, 0, $pos)) {
            $var = end($this->forelseStack);

            return $var === false ? $literal : "<?php endforeach; if (\${$var}): ?>";
        }
        if ($name === 'endempty') {
            return '<?php endif; ?>';
        }

        $hasParens = preg_match('/\G[ \t]*\(/', $src, $_, 0, $pos) === 1;

        if ($hasParens && array_key_exists($name, self::WITH_ARGS)) {
            $args = $this->readParens($src, $pos);
            if ($args === null) {
                return $literal;
            }

            return $this->withArgs($name, $args);
        }

        if (isset(self::BARE[$name])) {
            return self::BARE[$name];
        }

        return $literal; // bilinmeyen: olduğu gibi bırak (ör. @media, e-posta adresleri)
    }

    protected function withArgs(string $name, string $args): string
    {
        switch ($name) {
            case 'section':
                // @section('ad', 'kısa içerik') tek satırlıktır; @section('ad') ... @endsection bloktur
                if ($this->topLevelComma($args) !== null) {
                    return '<?php $__env->inlineSection(' . $args . '); ?>';
                }

                return '<?php $__env->startSection(' . $args . '); ?>';

            case 'csrf':
                return '<?php echo csrf_field(); ?>';

            case 'forelse':
                $var = '__empty' . (++$this->forelseCounter);
                $this->forelseStack[] = $var;

                return "<?php \${$var} = true; foreach ({$args}): \${$var} = false; ?>";

            case 'error':
                return str_replace('{0}', $args, self::WITH_ARGS['error']);
        }

        return str_replace('{0}', $args, self::WITH_ARGS[$name]);
    }

    /** "(" ile başlayan dengeli parantez içeriğini okur (tırnak içindeki parantezler sayılmaz). */
    protected function readParens(string $src, int &$pos): ?string
    {
        $open = strpos($src, '(', $pos);
        $len = strlen($src);
        $depth = 0;
        $quote = null;

        for ($i = $open; $i < $len; $i++) {
            $c = $src[$i];

            if ($quote !== null) {
                if ($c === '\\') {
                    $i++;
                } elseif ($c === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($c === '"' || $c === "'") {
                $quote = $c;
            } elseif ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
                if ($depth === 0) {
                    $inner = substr($src, $open + 1, $i - $open - 1);
                    $pos = $i + 1;

                    return trim($inner);
                }
            }
        }

        return null;
    }

    protected function topLevelComma(string $args): ?int
    {
        $depth = 0;
        $quote = null;

        for ($i = 0, $n = strlen($args); $i < $n; $i++) {
            $c = $args[$i];
            if ($quote !== null) {
                if ($c === '\\') {
                    $i++;
                } elseif ($c === $quote) {
                    $quote = null;
                }
            } elseif ($c === '"' || $c === "'") {
                $quote = $c;
            } elseif ($c === '(' || $c === '[') {
                $depth++;
            } elseif ($c === ')' || $c === ']') {
                $depth--;
            } elseif ($c === ',' && $depth === 0) {
                return $i;
            }
        }

        return null;
    }
}
