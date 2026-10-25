<?php

namespace App\Controllers;

use Core\Controller;

class AnneController extends Controller
{
    public function index()
    {
        return $this->view('deneme');
    }
}
