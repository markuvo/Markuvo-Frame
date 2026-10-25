<?php

namespace Tests\Feature;

use App\Models\User;
use Core\Database\DB;
use Core\Database\Migrator;
use Core\System\Request;
use Core\System\Response;
use Core\System\Router;
use Core\System\Session;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

class HttpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (new Migrator(dirname(__DIR__, 2) . '/database/migrations'))->run();
        User::create(['username' => 'admin', 'email' => 'admin@markuvo.com', 'password' => password_hash('123456', PASSWORD_DEFAULT), 'role' => 'admin']);

        require dirname(__DIR__, 2) . '/routes/web.php';
    }

    /** Tek süreçte bir HTTP isteği çalıştırır. */
    protected function call(string $method, string $uri, array $post = [], array $headers = [], bool $csrf = true): Response
    {
        $server = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'SCRIPT_NAME' => '/index.php', 'REMOTE_ADDR' => '10.0.0.1', 'HTTP_HOST' => 'localhost'];
        foreach ($headers as $k => $v) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $k))] = $v;
        }

        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);

        if ($csrf && $method !== 'GET' && !isset($post['_token'])) {
            $post['_token'] = $this->app->make(Session::class)->token();
        }

        return $this->app->handle(new Request($query, $post, [], $server));
    }

    protected function login(string $email = 'admin@markuvo.com', string $password = '123456'): Response
    {
        return $this->call('POST', '/login', ['email' => $email, 'password' => $password]);
    }

    // ------------------------------------------------------------ yönlendirme

    public function testHomePageRendersWithLayout(): void
    {
        $r = $this->call('GET', '/');

        $this->assertSame(200, $r->status());
        $this->assertStringContainsString('<title>Markuvo ENES</title>', $r->content());
        $this->assertStringContainsString('Rota Motoru', $r->content());
        $this->assertStringContainsString('PHP ' . phpversion(), $r->content());
    }

    public function testUnknownPathIs404(): void
    {
        $r = $this->call('GET', '/yok-boyle-bir-sayfa');

        $this->assertSame(404, $r->status());
        $this->assertStringContainsString('404', $r->content());
    }

    public function testWrongMethodIs405WithAllowHeader(): void
    {
        $r = $this->call('DELETE', '/login');

        $this->assertSame(405, $r->status());
        $this->assertStringContainsString('POST', $r->getHeader('Allow'));
    }

    public function testBothParameterSyntaxes(): void
    {
        $this->assertStringContainsString('Kullanıcı ID: 42', $this->call('GET', '/user/42')->content());
        $this->assertStringContainsString('<b>77</b>', $this->call('GET', '/kullanici/77')->content());
    }

    public function testRouteParametersAreEscapedInOutput(): void
    {
        $r = $this->call('GET', '/user/' . rawurlencode('<img src=x onerror=alert(1)>'));

        $this->assertStringNotContainsString('<img src=x', $r->content());
        $this->assertStringContainsString('&lt;img src=x', $r->content());
    }

    public function testControllerRouteFromTheOldDenemeExample(): void
    {
        $r = $this->call('GET', '/deneme');

        $this->assertSame(200, $r->status());
        $this->assertStringContainsString('Deneme View', $r->content());
    }

    public function testWhereConstraintNamedRoutesAndGroups(): void
    {
        Router::get('/sayi/{n}', fn($n) => "n=$n")->where('n', '\d+')->name('sayi');
        Router::prefix('/api')->name('api.')->group(function () {
            Router::get('/ping', fn() => ['pong' => true])->name('ping');
        });

        $this->assertSame('n=5', $this->call('GET', '/sayi/5')->content());
        $this->assertSame(404, $this->call('GET', '/sayi/abc')->status());

        $this->assertSame('/sayi/9?x=1', $this->app->make(Router::class)->url('sayi', ['n' => 9, 'x' => 1]));
        $this->assertSame('/api/ping', $this->app->make(Router::class)->url('api.ping'));

        $json = $this->call('GET', '/api/ping');
        $this->assertSame('{"pong":true}', $json->content());
        $this->assertStringContainsString('application/json', $json->getHeader('Content-Type'));
    }

    public function testMissingRouteParameterForUrlThrows(): void
    {
        Router::get('/x/{id}', fn() => '')->name('x');
        $this->expectException(\InvalidArgumentException::class);
        $this->app->make(Router::class)->url('x');
    }

    public function testModelRouteBindingAndDependencyInjection(): void
    {
        Router::get('/uye/{user}', fn(User $user, Request $request) => $user->username . '|' . $request->getPath());

        $this->assertSame('admin|/uye/1', $this->call('GET', '/uye/1')->content());
        $this->assertSame(404, $this->call('GET', '/uye/9999')->status());
    }

    public function testSubdirectoryInstallation(): void
    {
        $server = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/proje/user/5', 'SCRIPT_NAME' => '/proje/public/index.php'];
        $r = $this->app->handle(new Request([], [], [], $server));

        $this->assertSame(200, $r->status());
        $this->assertStringContainsString('ID: 5', $r->content());
        $this->assertSame('/proje', (new Request([], [], [], $server))->getBasePath());
    }

    public function testMethodSpoofingOnlyFromPost(): void
    {
        Router::delete('/sil/{id}', fn($id) => "silindi $id");

        $this->assertSame('silindi 3', $this->call('POST', '/sil/3', ['_method' => 'DELETE'])->content());
        $this->assertSame(405, $this->call('GET', '/sil/3?_method=DELETE')->status());
    }

    // ------------------------------------------------------------ CSRF

    public function testPostWithoutCsrfTokenIs419(): void
    {
        $r = $this->call('POST', '/login', ['email' => 'a@b.co', 'password' => '123456'], [], false);

        $this->assertSame(419, $r->status());
    }

    public function testCsrfTokenInHeaderIsAccepted(): void
    {
        Router::post('/api-yaz', fn() => 'ok');
        $token = $this->app->make(Session::class)->token();

        $this->assertSame('ok', $this->call('POST', '/api-yaz', [], ['X-CSRF-TOKEN' => $token], false)->content());
        $this->assertSame(419, $this->call('POST', '/api-yaz', [], ['X-CSRF-TOKEN' => 'yanlis'], false)->status());
    }

    public function testCsrfExceptPatterns(): void
    {
        config(['app.csrf_except' => ['webhook/*']]);
        Router::post('/webhook/odeme', fn() => 'alindi');

        $this->assertSame('alindi', $this->call('POST', '/webhook/odeme', [], [], false)->content());
    }

    // ------------------------------------------------------------ giriş / yetki

    public function testGuestIsRedirectedToLoginAndBackAfterLogin(): void
    {
        $r = $this->call('GET', '/admin/dashboard');
        $this->assertSame(302, $r->status());
        $this->assertSame('/login', $r->getHeader('Location'));

        $r = $this->login();
        $this->assertSame(302, $r->status());
        $this->assertSame('/admin/dashboard', $r->getHeader('Location'));

        $this->assertStringContainsString('Yönetim Paneli', $this->call('GET', '/admin/dashboard')->content());
    }

    public function testLoginShowsPanelAndLogoutEndsSession(): void
    {
        $this->assertSame('/panel', $this->login()->getHeader('Location'));

        $panel = $this->call('GET', '/panel');
        $this->assertSame(200, $panel->status());
        $this->assertStringContainsString('Hoş geldin, admin', $panel->content());
        $this->assertStringContainsString('name="_token"', $panel->content());

        $this->assertSame('/login', $this->call('POST', '/logout')->getHeader('Location'));
        $this->assertSame(302, $this->call('GET', '/panel')->status());
    }

    public function testWrongPasswordShowsGenericErrorAndKeepsOnlyEmail(): void
    {
        $r = $this->call('POST', '/login', ['email' => 'admin@markuvo.com', 'password' => 'yanlis-sifre'], ['Referer' => 'http://localhost/login']);
        $this->assertSame(302, $r->status());

        $page = $this->call('GET', '/login');
        $this->assertStringContainsString('E-posta veya şifre hatalı.', $page->content());
        $this->assertStringContainsString('value="admin@markuvo.com"', $page->content());
        $this->assertStringNotContainsString('yanlis-sifre', $page->content());

        // flash bir kez gösterilir
        $this->assertStringNotContainsString('E-posta veya şifre hatalı.', $this->call('GET', '/login')->content());
    }

    public function testUnknownEmailGivesSameMessageAsWrongPassword(): void
    {
        $this->call('POST', '/login', ['email' => 'yok@yok.com', 'password' => 'herhangi1'], ['Referer' => 'http://localhost/login']);

        $this->assertStringContainsString('E-posta veya şifre hatalı.', $this->call('GET', '/login')->content());
    }

    public function testValidationErrorsAreFlashedWithOldInputButNeverPasswords(): void
    {
        $this->call('POST', '/login', ['email' => 'gecersiz', 'password' => '123'], ['Referer' => 'http://localhost/login']);
        $page = $this->call('GET', '/login')->content();

        $this->assertStringContainsString('geçerli bir e-posta', $page);
        $this->assertStringContainsString('en az 6 karakter', $page);
        $this->assertStringContainsString('value="gecersiz"', $page);
        $this->assertStringNotContainsString('value="123"', $page);
    }

    public function testValidationErrorOnJsonRequestReturns422(): void
    {
        $r = $this->call('POST', '/login', ['email' => 'x'], ['Accept' => 'application/json']);

        $this->assertSame(422, $r->status());
        $body = json_decode($r->content(), true);
        $this->assertArrayHasKey('email', $body['errors']);
        $this->assertArrayHasKey('password', $body['errors']);
    }

    public function testLoggedInUserIsRedirectedAwayFromLoginPage(): void
    {
        $this->login();

        $this->assertSame('/', $this->call('GET', '/login')->getHeader('Location'));
    }

    public function testSessionIdAndCsrfTokenChangeAfterLogin(): void
    {
        $before = $this->app->make(Session::class)->token();
        $this->login();

        $this->assertNotSame($before, $this->app->make(Session::class)->token());
    }

    public function testLoginIsThrottled(): void
    {
        $last = null;
        for ($i = 0; $i < 12; $i++) {
            $last = $this->call('POST', '/login', ['email' => 'admin@markuvo.com', 'password' => 'yanlis' . $i]);
        }

        $this->assertSame(429, $last->status());
        $this->assertNotNull($last->getHeader('Retry-After'));
    }

    public function testOpenRedirectIsBlockedAfterLogin(): void
    {
        $this->app->make(Session::class)->put('url.intended', '//evil.example');

        $this->assertSame('/panel', $this->login()->getHeader('Location'));
    }

    public function testUnauthenticatedJsonRequestGets401(): void
    {
        $r = $this->call('GET', '/panel', [], ['Accept' => 'application/json']);

        $this->assertSame(401, $r->status());
    }

    // ------------------------------------------------------------ hata yönetimi

    public function testExceptionsAreHiddenInProductionAndLogged(): void
    {
        config(['app.debug' => false]);
        Router::get('/patla', function () { throw new \RuntimeException('GIZLI-DETAY'); });

        $r = $this->call('GET', '/patla');

        $this->assertSame(500, $r->status());
        $this->assertStringNotContainsString('GIZLI-DETAY', $r->content());
        $this->assertStringContainsString('app.ERROR', file_get_contents($this->storage . '/logs/app.log'));
        $this->assertStringContainsString('GIZLI-DETAY', file_get_contents($this->storage . '/logs/app.log'));
    }

    public function testExceptionDetailsShownOnlyInDebug(): void
    {
        config(['app.debug' => true]);
        Router::get('/patla', function () { throw new \RuntimeException('GORUNUR-DETAY'); });

        $this->assertStringContainsString('GORUNUR-DETAY', $this->call('GET', '/patla')->content());
    }

    public function testDebugPageEscapesExceptionMessage(): void
    {
        config(['app.debug' => true]);
        Router::get('/patla', function () { throw new \RuntimeException('<img src=x onerror=alert(1)>'); });

        $this->assertStringNotContainsString('<img src=x', $this->call('GET', '/patla')->content());
    }

    public function testJsonErrorsAreJson(): void
    {
        config(['app.debug' => false]);
        Router::get('/patla', function () { throw new \RuntimeException('x'); });

        $r = $this->call('GET', '/patla', [], ['Accept' => 'application/json']);

        $this->assertSame(500, $r->status());
        $this->assertSame('Sunucu hatası.', json_decode($r->content(), true)['message']);
    }

    public function testDatabaseErrorsGoToDbLogWithoutBindingValues(): void
    {
        config(['app.debug' => false]);
        Router::get('/db', fn() => DB::select('SELECT * FROM olmayan_tablo WHERE x = ?', ['SIR-DEGER-123']));

        $r = $this->call('GET', '/db');

        $this->assertSame(500, $r->status());
        $log = file_get_contents($this->storage . '/logs/db_errors.log');
        $this->assertStringContainsString('olmayan_tablo', $log);
        $this->assertStringNotContainsString('SIR-DEGER-123', $log);
        $this->assertStringNotContainsString('olmayan_tablo', $r->content());
    }

    public function testAbortHelper(): void
    {
        Router::get('/yasak', fn() => abort(403, 'Bu alana giremezsin.'));

        $r = $this->call('GET', '/yasak');
        $this->assertSame(403, $r->status());
        $this->assertStringContainsString('Bu alana giremezsin.', $r->content());
    }
}
