<?php

namespace App\Controllers;

use Luminus\Request;
use Luminus\View;

class HomeController
{
    public function index(Request $req, View $view): string
    {
        return $view->render('welcome', ['name' => 'Luminus']);
    }
}
