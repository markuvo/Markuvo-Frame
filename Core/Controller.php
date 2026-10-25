<?php

namespace Core;

use Core\System\Response;

/**
 * Controller tabanı. Metotlar string/dizi/Response döndürebilir; hepsi yanıta çevrilir.
 * Bağımlılıklar yapıcıdan ya da metot parametrelerinden otomatik gelir:
 *
 *   public function show(Request $request, BlogModel $blog) { return $this->view('blog.show', compact('blog')); }
 */
abstract class Controller
{
    protected function view(string $view, array $data = [], int $status = 200): Response
    {
        return new Response(View::render($view, $data), $status);
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $url, int $status = 302): Response
    {
        return redirect($url, $status);
    }
}
