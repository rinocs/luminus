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

        $password = (string) $request->post('password');
        $userId = Session::get('user_id');

        $user = $this->db->query(
            'SELECT * FROM users WHERE id = ? LIMIT 1',
            [$userId]
        );

        if (empty($user) || !password_verify($password, $user[0]['password'])) {
            Session::flash('errors', ['password' => 'The provided password does not match our records.']);
            return (new Response())->redirect('/confirm-password');
        }

        Session::put('auth_password_confirmed_at', time());

        return (new Response())->redirect(Session::getFlash('intended', '/'));
    }
}
