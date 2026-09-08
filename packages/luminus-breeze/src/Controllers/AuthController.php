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

        $throttleKey = '';
        if ($email !== '') {
            $throttleKey = 'login_throttle_' . md5($email);
            $lockedAt = Session::get($throttleKey . '_locked_at', 0);
            $attempts = Session::get($throttleKey . '_attempts', 0);

            if ($attempts >= 5) {
                if ((time() - $lockedAt) < 60) {
                    $seconds = 60 - (time() - $lockedAt);
                    Session::flash('errors', ['email' => "Too many login attempts. Please try again in {$seconds} seconds."]);
                    Session::flash('old', compact('email', 'remember'));
                    return (new Response())->redirect('/login');
                } else {
                    Session::forget($throttleKey . '_attempts');
                    Session::forget($throttleKey . '_locked_at');
                }
            }
        }

        if ($email === '') {
            $errors['email'] = 'The email field is required.';
        } elseif (strlen($email) > 255) {
            $errors['email'] = 'The email must not exceed 255 characters.';
        }

        if ($password === '') {
            $errors['password'] = 'The password field is required.';
        } elseif (strlen($password) > 255) {
            $errors['password'] = 'The password must not exceed 255 characters.';
        }

        if (empty($errors)) {
            $user = $this->db->query(
                'SELECT * FROM users WHERE email = ? LIMIT 1',
                [$email]
            );

            $userExists = !empty($user);
            // Use a dummy hash if the user does not exist to prevent username enumeration via timing side-channels
            $hash = $userExists ? $user[0]['password'] : '$2y$10$HgiXnOCgSFHhDj7FyWP3nugaiDRAoLOx/a1Uqem1BNGitTj78DuTG';

            if (password_verify($password, $hash) && $userExists) {
                if ($throttleKey !== '') {
                    Session::forget($throttleKey . '_attempts');
                    Session::forget($throttleKey . '_locked_at');
                }
                Session::regenerate();
                Session::regenerateToken();
                Session::put('user_id', $user[0]['id']);
                Session::put('user_email', $user[0]['email']);
                Session::put('user_name', $user[0]['name']);

                if ($remember) {
                    // Remember-me logic can be added by the consuming app
                }

                $intended = Session::getFlash('intended', '/');
                $response = new Response();
                if (method_exists($response, 'safeRedirect')) {
                    return $response->safeRedirect($intended);
                }
                return $response->redirect($intended);
            }

            if ($throttleKey !== '') {
                $attempts = Session::get($throttleKey . '_attempts', 0) + 1;
                Session::put($throttleKey . '_attempts', $attempts);
                if ($attempts >= 5) {
                    Session::put($throttleKey . '_locked_at', time());
                }
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

        $throttleKey = 'register_throttle_' . md5($email !== '' ? $email : Session::token());
        $lockedAt = Session::get($throttleKey . '_locked_at', 0);
        $attempts = Session::get($throttleKey . '_attempts', 0);

        if ($attempts >= 5) {
            if ((time() - $lockedAt) < 60) {
                $seconds = 60 - (time() - $lockedAt);
                Session::flash('errors', ['email' => "Too many registration attempts. Please try again in {$seconds} seconds."]);
                Session::flash('old', compact('name', 'email'));
                return (new Response())->redirect('/register');
            } else {
                Session::forget($throttleKey . '_attempts');
                Session::forget($throttleKey . '_locked_at');
            }
        }

        $errors = [];

        if ($name === '' || strlen($name) > 255) {
            $errors['name'] = 'The name field is required and must not exceed 255 characters.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $errors['email'] = 'Please provide a valid email address.';
        }

        if (strlen($password) < 8 || strlen($password) > 255) {
            $errors['password'] = 'The password must be between 8 and 255 characters.';
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
            $attempts = Session::get($throttleKey . '_attempts', 0) + 1;
            Session::put($throttleKey . '_attempts', $attempts);
            if ($attempts >= 5) {
                Session::put($throttleKey . '_locked_at', time());
            }
            Session::flash('errors', $errors);
            Session::flash('old', compact('name', 'email'));
            return (new Response())->redirect('/register');
        }

        Session::forget($throttleKey . '_attempts');
        Session::forget($throttleKey . '_locked_at');

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
