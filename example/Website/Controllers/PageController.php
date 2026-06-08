<?php

namespace Example\Website\Controllers;

use Luminus\Request;
use Luminus\Response;
use Luminus\View;

class PageController
{
    public function home(View $view): string
    {
        return $view->render('home', [
            'title' => 'Luminus Demo',
            'features' => [
                'Zero external dependencies',
                'DI container with autowiring',
                'Router with parameter matching',
                'Template engine with layouts',
                'PSR-4 autoloading',
            ],
        ]);
    }

    public function about(View $view): string
    {
        return $view->render('about', [
            'title' => 'About',
            'description' => 'Luminus is a minimal PHP framework built for clarity and control. No magic. No facades. Just PHP.',
        ]);
    }

    public function contact(View $view): string
    {
        return $view->render('contact', [
            'title' => 'Contact us',
        ]);
    }

    public function sendContact(Request $req, View $view): string
    {
        $name = $req->post('name', '');
        $email = $req->post('email', '');
        $message = $req->post('message', '');

        $errors = [];

        if (!trim($name)) $errors[] = 'Name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if (!trim($message)) $errors[] = 'Message is required.';

        if ($errors) {
            return $view->render('contact', [
                'title' => 'Contact us',
                'errors' => $errors,
                'old' => compact('name', 'email', 'message'),
            ]);
        }

        return $view->render('contact', [
            'title' => 'Contact us',
            'success' => "Thanks {$name}, we'll get back to you soon!",
        ]);
    }
}
