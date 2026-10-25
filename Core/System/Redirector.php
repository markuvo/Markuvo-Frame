<?php

namespace Core\System;

class Redirector
{
    public function __construct(protected Session $session)
    {
    }

    protected function request(): Request
    {
        return app(Request::class);
    }

    /** İç yollar alt klasör eklenerek, tam adresler olduğu gibi yönlendirilir. */
    public function to(string $url, int $status = 302): Response
    {
        if (!preg_match('#^([a-z][a-z0-9+.\-]*:|//)#i', $url)) {
            $url = $this->request()->getBasePath() . '/' . ltrim($url, '/');
        }

        return Response::redirect($url, $status);
    }

    /** Önceki sayfaya; yalnızca aynı siteden gelen Referer kabul edilir (açık yönlendirme koruması). */
    public function back(string $fallback = '/'): Response
    {
        $referer = (string) $this->request()->header('Referer', '');
        $host = (string) $this->request()->header('Host', '');

        if ($referer !== '' && $host !== '' && parse_url($referer, PHP_URL_HOST) === parse_url('//' . $host, PHP_URL_HOST)) {
            return Response::redirect($referer);
        }

        return $this->to($fallback);
    }

    public function with(string $key, mixed $value): static
    {
        $this->session->flash($key, $value);

        return $this;
    }
}
