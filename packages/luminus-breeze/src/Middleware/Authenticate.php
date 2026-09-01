<?php

namespace Luminus\Breeze\Middleware;

use Luminus\Middleware;
use Luminus\Request;
use Luminus\Response;
use Luminus\Session;

class Authenticate implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!Session::has('user_id')) {
            Session::flash('intended', $request->path());
            return (new Response())->redirect('/login');
        }

        return $next($request);
    }
}
