<?php

namespace Luminus;

class StartSessionMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        Session::start();

        $response = $next($request);

        Session::ageFlashData();

        return $response;
    }
}
