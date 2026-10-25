<?php

namespace Tests\Unit;

use Core\View;
use Core\View\Compiler;
use Tests\TestCase;

class ViewTest extends TestCase
{
    protected function render(string $template, array $data = []): string
    {
        $name = 'tmp_' . bin2hex(random_bytes(4));
        $file = dirname(__DIR__, 2) . "/app/Views/{$name}.vibe.php";
        file_put_contents($file, $template);

        try {
            return View::render($name, $data);
        } finally {
            @unlink($file);
        }
    }

    /** Boşluk farklarını yok sayarak karşılaştırmak için. */
    protected function norm(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    public function testEchoIsEscapedAndRawEchoIsNot(): void
    {
        $out = $this->render('{{ $x }}|{!! $x !!}', ['x' => '<b>"hi"</b>']);

        $this->assertSame('&lt;b&gt;&quot;hi&quot;&lt;/b&gt;|<b>"hi"</b>', $out);
    }

    public function testUndefinedVariablesRenderEmptyInsteadOfCrashing(): void
    {
        $this->assertSame('[]', $this->render('[{{ $yok }}]'));
        $this->assertSame('[]', $this->render('[{{ $a["b"] }}]', ['a' => []]));
    }

    public function testCommentsEscapesAndVerbatim(): void
    {
        $out = $this->render("a{{-- gizli --}}b @{{ ham }} @@if ali@site.com @media @verbatim{{ x }}@endverbatim");

        $this->assertSame('ab {{ ham }} @if ali@site.com @media {{ x }}', $out);
    }

    public function testIfElseUnlessIssetEmpty(): void
    {
        $t = "@if(\$n > 5) büyük @elseif(\$n == 5) beş @else küçük @endif | @unless(\$n) yok @endunless | @isset(\$n) var @endisset | @empty(\$z) boş @endempty";

        $this->assertSame('büyük | | var | boş', $this->norm($this->render($t, ['n' => 9, 'z' => []])));
        $this->assertSame('beş | | var | boş', $this->norm($this->render($t, ['n' => 5, 'z' => 0])));
        $this->assertSame('küçük | yok | var | boş', $this->norm($this->render($t, ['n' => 0, 'z' => ''])));
    }

    public function testLoopsAndForelse(): void
    {
        $this->assertSame('123', $this->render('@foreach($l as $i){{ $i }}@endforeach', ['l' => [1, 2, 3]]));
        $this->assertSame('012', $this->render('@for($i=0;$i<3;$i++){{ $i }}@endfor'));
        $this->assertSame('boş', $this->norm($this->render('@forelse($l as $i){{ $i }}@empty boş @endforelse', ['l' => []])));
        $this->assertSame('ab', $this->norm($this->render('@forelse($l as $i){{ $i }}@empty boş @endforelse', ['l' => ['a', 'b']])));
    }

    public function testNestedForelseKeepsSeparateState(): void
    {
        $t = '@forelse($a as $x)[@forelse($x as $y){{ $y }}@empty - @endforelse]@empty none @endforelse';

        $this->assertSame('[1][ - ]', $this->render($t, ['a' => [[1], []]]));
    }

    public function testParenthesesInsideStringsDoNotBreakDirectives(): void
    {
        $this->assertSame('ok', $this->norm($this->render("@if(strlen(')(') == 2) ok @endif")));
    }

    public function testExtendsSectionYieldAndInclude(): void
    {
        $dir = dirname(__DIR__, 2) . '/app/Views/_t' . bin2hex(random_bytes(3));
        mkdir($dir);
        $base = basename($dir);
        file_put_contents("{$dir}/layout.vibe.php", "<h1>@yield('title', 'varsayılan')</h1><main>@yield('content')</main>@stack('js')");
        file_put_contents("{$dir}/part.vibe.php", 'parça:{{ $x }}');
        file_put_contents("{$dir}/page.vibe.php", "@extends('{$base}.layout')\n@section('title', 'Başlık')\n@section('content')İçerik @include('{$base}.part') {{ \$x }}@endsection\n@push('js')<s>1</s>@endpush");

        try {
            $out = View::render("{$base}.page", ['x' => '<i>']);
        } finally {
            array_map('unlink', glob("{$dir}/*"));
            rmdir($dir);
        }

        $this->assertStringContainsString('<h1>Başlık</h1>', $out);
        $this->assertStringContainsString('<main>İçerik parça:&lt;i&gt; &lt;i&gt;</main>', $out);
        $this->assertStringContainsString('<s>1</s>', $out);
    }

    public function testDefaultYieldValue(): void
    {
        $dir = dirname(__DIR__, 2) . '/app/Views/_d' . bin2hex(random_bytes(3));
        mkdir($dir);
        $base = basename($dir);
        file_put_contents("{$dir}/l.vibe.php", "[@yield('x', 'varsayılan')]");
        file_put_contents("{$dir}/p.vibe.php", "@extends('{$base}.l')");

        try {
            $out = View::render("{$base}.p");
        } finally {
            array_map('unlink', glob("{$dir}/*"));
            rmdir($dir);
        }

        $this->assertSame('[varsayılan]', $out);
    }

    public function testCsrfMethodJsonAndPhpBlock(): void
    {
        $out = $this->render("@csrf @method('PUT') @json(['a' => '</script>']) @php \$q = 4; @endphp {{ \$q }}");

        $this->assertStringContainsString('name="_token"', $out);
        $this->assertStringContainsString('value="PUT"', $out);
        $this->assertStringContainsString('\u003C', $out, '@json etiket kapatmaya karşı kaçışlı olmalı');
        $this->assertStringNotContainsString('</script>', $out);
        $this->assertStringEndsWith('4', trim($out));
    }

    public function testAuthAndGuestDirectives(): void
    {
        $this->assertSame('misafir', $this->norm($this->render('@auth üye @endauth @guest misafir @endguest')));
    }

    public function testErrorDirective(): void
    {
        app('session')->flash('errors', ['ad' => ['Zorunlu']]);
        app('session')->restart();
        app('session')->start();

        $out = $this->render("@error('ad') !{{ \$message }} @enderror @error('x') YOK @enderror");

        $this->assertStringContainsString('!Zorunlu', str_replace(' ', '', $out));
        $this->assertStringNotContainsString('YOK', $out);
    }

    public function testCompiledFilesAreCachedAndInvalidated(): void
    {
        $name = 'tmp_cache_' . bin2hex(random_bytes(3));
        $file = dirname(__DIR__, 2) . "/app/Views/{$name}.vibe.php";
        file_put_contents($file, 'v1');

        try {
            $this->assertSame('v1', View::render($name));
            $compiled = glob($this->storage . '/cache/views/*.php');
            $this->assertCount(1, $compiled);
            $this->assertStringNotContainsString('eval', file_get_contents($compiled[0]));

            $mtime = filemtime($compiled[0]);
            View::render($name);
            clearstatcache();
            $this->assertSame($mtime, filemtime($compiled[0]), 'değişmeyen şablon yeniden derlenmemeli');

            file_put_contents($file, 'v2');
            touch($file, time() + 5);
            $this->assertSame('v2', View::render($name));
        } finally {
            @unlink($file);
        }
    }

    public function testViewNameTraversalIsRejected(): void
    {
        foreach (['../../.env', '..\\x', '/etc/passwd', "a\0b", 'a b'] as $bad) {
            try {
                View::render($bad);
                $this->fail("{$bad} reddedilmeliydi");
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function testMissingViewThrowsClearError(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Görünüm bulunamadı');
        View::render('yok.boyle.bir');
    }

    public function testExceptionInsideTemplateDoesNotLeakOutputBuffers(): void
    {
        $level = ob_get_level();

        try {
            $this->render("@section('a') x @php throw new \\RuntimeException('patla'); @endphp @endsection");
        } catch (\RuntimeException) {
        }

        $this->assertSame($level, ob_get_level());
    }

    public function testCompilerOutputForEmailLikeText(): void
    {
        $this->assertSame('mail: ali@site.com', (new Compiler())->compile('mail: ali@site.com'));
    }
}
