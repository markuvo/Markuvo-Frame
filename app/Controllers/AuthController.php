<?php

namespace App\Controllers;

use Core\Controller;
use Core\System\Request;
use Core\System\Session;

class AuthController extends Controller
{
    public function showLogin()
    {
        return $this->view('form', ['title' => 'Markuvo | Giriş Yap']);
    }

    public function login(Request $request, Session $session)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        if (!auth()->attempt($credentials)) {
            // Hangi alanın yanlış olduğunu söylemeyiz (kullanıcı keşfini engeller)
            $session->flash('errors', ['email' => ['E-posta veya şifre hatalı.']]);
            $session->flash('old', ['email' => $credentials['email']]);

            return back('/login');
        }

        // Giriş öncesi gidilmek istenen sayfa varsa oraya (yalnızca site içi yol kabul edilir)
        $intended = (string) $session->get('url.intended', '/panel');
        $session->forget('url.intended');
        if (!str_starts_with($intended, '/') || str_starts_with($intended, '//')) {
            $intended = '/panel';
        }

        return redirect($intended);
    }

    public function logout()
    {
        auth()->logout();

        return redirect('/login');
    }
}
