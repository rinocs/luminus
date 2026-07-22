<?php

namespace Luminus\Providers;

use Luminus\App;

abstract class ServiceProvider
{
    public function __construct(protected App $app) {}

    // Bind things into the container
    abstract public function register(): void;

    // Run after all providers are registered (add routes, middleware, etc.)
    abstract public function boot(): void;
}
