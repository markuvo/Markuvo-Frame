<?php

namespace Tests\Unit;

use Core\System\Request;
use Core\System\Response;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    protected function req(array $server = [], array $query = [], array $post = [], ?string $body = null): Request
    {
        return new Request($query, $post, [], $server + ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'SCRIPT_NAME' => '/index.php'], [], $body);
    }

    public function testPathAndBasePathVariants(): void
    {
        $r = $this->req(['REQUEST_URI' => '/a/b?x=1', 'SCRIPT_NAME' => '/index.php']);
        $this->assertSame('/a/b', $r->getPath());
        $this->assertSame('', $r->getBasePath());

        $r = $this->req(['REQUEST_URI' => '/proje/public/blog/5', 'SCRIPT_NAME' => '/proje/public/index.php']);
        $this->assertSame('/blog/5', $r->getPath());

        $r = $this->req(['REQUEST_URI' => '/proje/blog/5', 'SCRIPT_NAME' => '/proje/public/index.php']);
        $this->assertSame('/proje', $r->getBasePath());
        $this->assertSame('/blog/5', $r->getPath());

        $this->assertSame('/', $this->req(['REQUEST_URI' => '/proje/', 'SCRIPT_NAME' => '/proje/public/index.php'])->getPath());
        $this->assertSame('/a b', $this->req(['REQUEST_URI' => '/a%20b'])->getPath());
        $this->assertSame('/x', $this->req(['REQUEST_URI' => '/x/'])->getPath());
    }

    public function testInputKeepsRawValuesAndMergesSources(): void
    {
        $r = $this->req([], ['q' => '<b>'], ['name' => "O'Neil", 'q' => 'post kazanır']);

        $this->assertSame("O'Neil", $r->input('name'));
        $this->assertSame('post kazanır', $r->input('q'));
        $this->assertSame('<b>', $r->query('q'));
        $this->assertSame(['name' => "O'Neil"], $r->only('name'));
        $this->assertSame(['q' => 'post kazanır'], $r->except('name'));
        $this->assertTrue($r->has('name', 'q'));
        $this->assertFalse($r->has('yok'));
        $this->assertSame('d', $r->input('yok', 'd'));
    }

    public function testFilled(): void
    {
        $r = $this->req([], [], ['a' => '', 'b' => '0', 'c' => []]);

        $this->assertFalse($r->filled('a'));
        $this->assertTrue($r->filled('b'));
        $this->assertFalse($r->filled('c'));
    }

    public function testJsonBody(): void
    {
        $r = $this->req(['CONTENT_TYPE' => 'application/json'], [], [], '{"user":{"name":"Ali"},"n":3}');

        $this->assertTrue($r->isJson());
        $this->assertSame('Ali', $r->input('user.name'));
        $this->assertSame(3, $r->json('n'));
        $this->assertSame([], $this->req(['CONTENT_TYPE' => 'application/json'], [], [], 'bozuk{')->json());
        $this->assertSame([], $this->req([], [], [], '{"a":1}')->json(), 'JSON başlığı yoksa gövde okunmaz');
    }

    public function testMethodSpoofing(): void
    {
        $this->assertSame('DELETE', $this->req(['REQUEST_METHOD' => 'POST'], [], ['_method' => 'delete'])->method());
        $this->assertSame('POST', $this->req(['REQUEST_METHOD' => 'POST'], [], ['_method' => 'GET'])->method());
        $this->assertSame('GET', $this->req(['REQUEST_METHOD' => 'GET'], ['_method' => 'DELETE'])->method());
    }

    public function testHeadersIpAndJsonExpectation(): void
    {
        $r = $this->req(['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', 'HTTP_ACCEPT' => 'text/html', 'REMOTE_ADDR' => '1.2.3.4', 'HTTP_USER_AGENT' => 'T']);

        $this->assertTrue($r->expectsJson());
        $this->assertSame('1.2.3.4', $r->ip());
        $this->assertSame('XMLHttpRequest', $r->header('x-requested-with'));
        $this->assertFalse($this->req()->expectsJson());
        $this->assertTrue($this->req(['HTTP_ACCEPT' => 'application/json'])->expectsJson());
        $this->assertTrue($this->req(['REQUEST_URI' => '/api/x'])->expectsJson());
    }

    public function testResponseHelpers(): void
    {
        $j = Response::json(['ş' => 1]);
        $this->assertSame('{"ş":1}', $j->content());
        $this->assertSame(302, Response::redirect('/x')->status());
        $this->assertSame('/x', Response::redirect("/x\r\nSet-Cookie: a=b")->getHeader('Location') === '/xSet-Cookie: a=b' ? '/x' : 'enjekte', 'CRLF temizlenmeli');
        $this->assertSame('bok', Response::make('bok')->content());
        $this->assertSame('[1]', Response::make([1])->content());
        $this->assertSame('', Response::make(null)->content());
    }
}
