<?php

namespace Luminus\Breeze\Controllers;

use Luminus\Request;
use Luminus\Response;
use Luminus\Session;
use Luminus\View;
use Luminus\Database;

class ConfirmablePasswordController
{
    private View $view;
    private Database $db;

    public function __construct(View $view, Database $db)
    {
        $this->view = $view;
        $this->db = $db;
    }

    public function show(Request $request): Response
    {
        if (!Session::has('user_id')) {
            return (new Response())->redirect('/login');
        }

        $html = $this->view->render('breeze::auth.confirm-password', [
            'errors' => Session::getFlash('errors', []),
        ]);

        return (new Response())->body($html);
    }

    public function store(Request $request): Response
    {
        if (!Session::has('user_id')) {
            return (new Response())->redirect('/login');
        }

        $userId = Session::get('user_id');
        $throttleKey = 'confirm_password_throttle_' . md5((string)$userId);
        $lockedAt = Session::get($throttleKey . '_locked_at', 0);
        $attempts = Session::get($throttleKey . '_attempts', 0);

        if ($attempts >= 5) {
            if ((time() - $lockedAt) < 60) {
                $seconds = 60 - (time() - $lockedAt);
                Session::flash('errors', ['password' => "Too many confirmation attempts. Please try again in {$seconds} seconds."]);
                return (new Response())->redirect('/confirm-password');
            } else {
                Session::forget($throttleKey . '_attempts');
                Session::forget($throttleKey . '_locked_at');
            }
        }

        $password = (string) $request->post('password');

        if ($password === '') {
            Session::flash('errors', ['password' => 'The password field is required.']);
            return (new Response())->redirect('/confirm-password');
        }

        // To prevent CPU-exhaustion Denial of Service (DoS) attacks via expensive password-hashing, enforce limit of 255 characters
        if (strlen($password) > 255) {
            Session::flash('errors', ['password' => 'The password must not exceed 255 characters.']);
            return (new Response())->redirect('/confirm-password');
        }

        $user = $this->db->query(
            'SELECT * FROM users WHERE id = ? LIMIT 1',
            [$userId]
        );

        $userExists = !empty($user);
        // Use a dummy hash if the user does not exist to prevent timing side-channels
        $hash = $userExists ? $user[0]['password'] : '$2y$10$HgiXnOCgSFHhDj7FyWP3nugaiDRAoLOx/a1Uqem1BNGitTj78DuTG';

        if (password_verify($password, $hash) && $userExists) {
            Session::forget($throttleKey . '_attempts');
            Session::forget($throttleKey . '_locked_at');
            Session::put('auth_password_confirmed_at', time());

            $intended = Session::getFlash('intended', '/');
            $response = new Response();
            if (method_exists($response, 'safeRedirect')) {
                return $response->safeRedirect($intended);
            }
            return $response->redirect($intended);
        }

        $attempts = Session::get($throttleKey . '_attempts', 0) + 1;
        Session::put($throttleKey . '_attempts', $attempts);
        if ($attempts >= 5) {
            Session::put($throttleKey . '_locked_at', time());
        }

        Session::flash('errors', ['password' => 'The provided password does not match our records.']);
        return (new Response())->redirect('/confirm-password');
    }
}
