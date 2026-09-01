<?php

namespace Luminus\Breeze\Middleware;

use Luminus\Middleware;
use Luminus\Request;
use Luminus\Response;
use Luminus\Session;

class RedirectIfAuthenticated implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (Session::has('user_id')) {
            return (new Response())->redirect('/');
        }

        return $next($request);
    }
}
