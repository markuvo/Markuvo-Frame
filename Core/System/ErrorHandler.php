<?php

namespace Core\System;

use Core\Application;

/**
 * Tüm hataların tek çıkışı.
 *
 *  - HttpException      -> ilgili durum kodu ve sayfa (404, 405, 419, 429 ...)
 *  - ValidationException-> forma geri yönlendirme (hatalar + eski girdi flash) ya da JSON 422
 *  - Diğer hatalar      -> storage/logs/app.log'a yazılır; APP_DEBUG=true ise ayrıntı gösterilir,
 *                          değilse ziyaretçi yalnızca genel bir 500 sayfası görür.
 *
 * Veritabanı hataları ayrıca storage/logs/db_errors.log'a (bağlama değerleri olmadan) yazılır.
 */
class ErrorHandler
{
    protected const TITLES = [
        400 => 'Geçersiz İstek', 401 => 'Giriş Gerekli', 403 => 'Erişim Engellendi', 404 => 'Sayfa Bulunamadı',
        405 => 'Yöntem Desteklenmiyor', 419 => 'Sayfanın Süresi Doldu', 422 => 'İşlenemeyen İçerik',
        429 => 'Çok Fazla İstek', 500 => 'Sunucu Hatası', 503 => 'Hizmet Kullanılamıyor',
    ];

    /** Uygulama kurulmadan önce oluşan hataları da yakalamak için. */
    public static function register(): void
    {
        set_exception_handler([self::class, 'handle']);

        set_error_handler(function (int $level, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $level)) {
                return false;
            }
            // Kullanımdan kaldırma uyarıları sayfayı bozmasın; günlüğe yazılır
            if (in_array($level, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
                error_log("[Markuvo] Deprecated: {$message} in {$file}:{$line}");

                return true;
            }

            throw new \ErrorException($message, 0, $level, $file, $line);
        });

        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                static::handle(new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
            }
        });
    }

    /** Global yakalayıcı: yanıtı kendisi gönderir. */
    public static function handle(\Throwable $e): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        try {
            static::render($e, null)->send();
        } catch (\Throwable $inner) {
            error_log('[Markuvo] Hata yakalayıcı da hata verdi: ' . $inner->getMessage());
            if (!headers_sent()) {
                http_response_code(500);
            }
            echo '500 - Sunucu Hatası';
        }
    }

    public static function render(\Throwable $e, ?Request $request): Response
    {
        $request ??= static::currentRequest();
        $json = $request?->expectsJson() ?? false;

        if ($e instanceof ValidationException) {
            return static::validationResponse($e, $request, $json);
        }

        if ($e instanceof \Core\Database\ModelNotFoundException) {
            $e = new HttpException(404, 'Kayıt bulunamadı.');
        }

        if ($e instanceof HttpException) {
            return static::httpResponse($e->getStatusCode(), $e->getMessage(), $e->getHeaders(), $json);
        }

        static::report($e);

        $debug = static::isDebug();

        if ($json) {
            $body = ['message' => $debug ? $e->getMessage() : 'Sunucu hatası.'];
            if ($debug) {
                $body['exception'] = get_class($e);
                $body['file'] = $e->getFile() . ':' . $e->getLine();
            }

            return Response::json($body, 500);
        }

        return new Response($debug ? static::debugPage($e) : static::page(500, 'Bir sorun oluştu. Lütfen daha sonra tekrar deneyin.'), 500);
    }

    protected static function validationResponse(ValidationException $e, ?Request $request, bool $json): Response
    {
        if ($json || $request === null) {
            return Response::json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        // Şifre/token alanları eski girdi olarak saklanmaz
        $old = array_filter(
            $e->input(),
            fn($key) => !preg_match('/pass|token|secret|card|cvv/i', (string) $key),
            ARRAY_FILTER_USE_KEY
        );

        try {
            $session = app(Session::class);
            $session->flash('errors', $e->errors());
            $session->flash('old', $old);
        } catch (\Throwable) {
            // oturum yoksa yalnızca yönlendir
        }

        return app(Redirector::class)->back('/');
    }

    protected static function httpResponse(int $status, string $message, array $headers, bool $json): Response
    {
        $title = self::TITLES[$status] ?? 'Hata';

        if ($json) {
            return Response::json(['message' => $message !== '' ? $message : $title], $status, $headers);
        }

        $html = static::customPage($status) ?? static::page($status, $message !== '' ? $message : $title);

        return new Response($html, $status, $headers);
    }

    /** app/Views/errors/404.vibe.php gibi özel sayfa varsa onu kullanır. */
    protected static function customPage(int $status): ?string
    {
        try {
            if (Application::getInstance() && \Core\View::exists('errors.' . $status)) {
                return \Core\View::render('errors.' . $status, ['status' => $status]);
            }
        } catch (\Throwable) {
            // özel sayfa bozuksa yerleşik sayfaya düş
        }

        return null;
    }

    protected static function page(int $status, string $message): string
    {
        $title = htmlspecialchars(self::TITLES[$status] ?? 'Hata', ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        return '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . "<title>{$status} - {$title}</title>"
            . '<style>body{font-family:system-ui,sans-serif;background:#090d16;color:#f8fafc;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}'
            . '.b{text-align:center;padding:24px}h1{font-size:4rem;margin:0;color:#e95b0d}p{color:#94a3b8;font-size:1.1rem}</style></head>'
            . "<body><div class=\"b\"><h1>{$status}</h1><h2>{$title}</h2><p>{$message}</p></div></body></html>";
    }

    protected static function debugPage(\Throwable $e): string
    {
        $h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        return '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><title>Markuvo Hata</title>'
            . '<style>body{font-family:system-ui,sans-serif;background:#fff7f0;color:#222;margin:0;padding:32px}code,pre{background:#fff;border:1px solid #f0c9ad;border-radius:6px;padding:12px;display:block;overflow:auto}'
            . 'h1{color:#c2410c}small{color:#777}</style></head><body>'
            . '<h1>' . $h(get_class($e)) . '</h1><p><b>' . $h($e->getMessage()) . '</b></p>'
            . '<p><small>' . $h($e->getFile()) . ':' . (int) $e->getLine() . '</small></p>'
            . '<pre>' . $h($e->getTraceAsString()) . '</pre>'
            . '<p><small>Bu ayrıntılı sayfa yalnızca APP_DEBUG=true iken görünür. Canlıda kapatın.</small></p></body></html>';
    }

    /** Günlüğe yaz (uygulama kurulu değilse PHP error_log'a). */
    protected static function report(\Throwable $e): void
    {
        try {
            if (Application::getInstance()) {
                app(Logger::class)->exception($e);

                return;
            }
        } catch (\Throwable) {
            // günlük yazılamıyorsa aşağıdaki yedeğe düş
        }

        error_log(sprintf('[Markuvo] %s: %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
    }

    protected static function isDebug(): bool
    {
        if (Application::getInstance()) {
            return (bool) config('app.debug', false);
        }

        return in_array(strtolower((string) (getenv('APP_DEBUG') ?: '')), ['1', 'true', 'on', 'yes'], true);
    }

    protected static function currentRequest(): ?Request
    {
        try {
            return Application::getInstance() ? app(Request::class) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
