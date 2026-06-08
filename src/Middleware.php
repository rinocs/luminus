<?php

namespace Luminus;

interface Middleware
{
    public function handle(Request $request, callable $next): Response;
}
