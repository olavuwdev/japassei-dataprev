<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        if (session()->get('user')) {
            // Nem todo usuário tem acesso a Estudos: manda para a primeira
            // área que ele realmente pode abrir.
            return redirect()->to(permission_home());
        }

        return redirect()->to('/login');
    }
}
