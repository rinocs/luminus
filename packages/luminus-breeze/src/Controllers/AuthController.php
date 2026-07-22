<?php

namespace Luminus\Breeze\Controllers;

use Luminus\App;
use Luminus\Database;
use Luminus\Request;
use Luminus\Response;
use Luminus\Session;
use Luminus\View;

class AuthController
{
    private App $app;
    private View $view;
    private Database $db;

    public function __construct(App $app, View $view, Database $db)
    {
        $this->app = $app;
        $this->view = $view;
        $this->db = $db;
    }

    public function create(Request $request): Response
    {
        if ($this->check()) {
            return (new Response())->redirect('/');
        }

        $html = $this->view->render('breeze::auth.login', [
            'errors' => Session::getFlash('errors', []),
            'old' => Session::getFlash('old', []),
        ]);

        return (new Response())->body($html);
    }

    public function store(Request $request): Response
    {
        if ($this->check()) {
            return (new Response())->redirect('/');
        }

        $email = trim((string) $request->post('email'));
        $password = (string) $request->post('password');
        $remember = (bool) $request->post('remember');

        $errors = [];

        if ($email === '') {
            $errors['email'] = 'The email field is required.';
        }

        if ($password === '') {
            $errors['password'] = 'The password field is required.';
        }

        if (empty($errors)) {
            $user = $this->db->query(
                'SELECT * FROM users WHERE email = ? LIMIT 1',
                [$email]
            );

            if (!empty($user) && password_verify($password, $user[0]['password'])) {
                Session::regenerate();
                Session::regenerateToken();
                Session::put('user_id', $user[0]['id']);
                Session::put('user_email', $user[0]['email']);
                Session::put('user_name', $user[0]['name']);

                if ($remember) {
                    // Remember-me logic can be added by the consuming app
                }

                return (new Response())->redirect(Session::getFlash('intended', '/'));
            }

            $errors['email'] = 'These credentials do not match our records.';
        }

        Session::flash('errors', $errors);
        Session::flash('old', compact('email', 'remember'));

        return (new Response())->redirect('/login');
    }

    public function destroy(Request $request): Response
    {
        Session::forget('user_id');
        Session::forget('user_email');
        Session::forget('user_name');
        Session::regenerateToken();
        Session::regenerate();

        return (new Response())->redirect('/');
    }

    public function registerCreate(Request $request): Response
    {
        if ($this->check()) {
            return (new Response())->redirect('/');
        }

        $html = $this->view->render('breeze::auth.register', [
            'errors' => Session::getFlash('errors', []),
            'old' => Session::getFlash('old', []),
        ]);

        return (new Response())->body($html);
    }

    public function registerStore(Request $request): Response
    {
        if ($this->check()) {
            return (new Response())->redirect('/');
        }

        $name = trim((string) $request->post('name'));
        $email = trim((string) $request->post('email'));
        $password = (string) $request->post('password');
        $passwordConfirmation = (string) $request->post('password_confirmation');

        $errors = [];

        if ($name === '' || strlen($name) > 255) {
            $errors['name'] = 'The name field is required and must not exceed 255 characters.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $errors['email'] = 'Please provide a valid email address.';
        }

        if (strlen($password) < 8) {
            $errors['password'] = 'The password must be at least 8 characters.';
        }

        if ($password !== $passwordConfirmation) {
            $errors['password_confirmation'] = 'The passwords do not match.';
        }

        if (empty($errors)) {
            $existing = $this->db->query(
                'SELECT id FROM users WHERE email = ? LIMIT 1',
                [$email]
            );

            if (!empty($existing)) {
                $errors['email'] = 'This email is already registered.';
            }
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', compact('name', 'email'));
            return (new Response())->redirect('/register');
        }

        $this->db->insert('users', [
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $user = $this->db->query(
            'SELECT * FROM users WHERE email = ? LIMIT 1',
            [$email]
        );

        if (!empty($user)) {
            Session::regenerate();
            Session::regenerateToken();
            Session::put('user_id', $user[0]['id']);
            Session::put('user_email', $user[0]['email']);
            Session::put('user_name', $user[0]['name']);
        }

        return (new Response())->redirect('/');
    }

    private function check(): bool
    {
        return Session::has('user_id');
    }
}
