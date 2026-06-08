<?php

namespace Luminus;

class CsrfMiddleware implements Middleware
{
    /**
     * URIs that should be excluded from CSRF verification.
     * Supports wildcards, e.g. '/api/*'
     */
    protected array $except;

    public function __construct(array $except = [])
    {
        $this->except = $except;
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->shouldValidate($request)) {
            $token = $this->getTokenFromRequest($request);

            if (!$token || !hash_equals(Session::token(), $token)) {
                return (new Response())
                    ->status(403)
                    ->body('403 Forbidden: CSRF token mismatch');
            }
        }

        $response = $next($request);

        // Set the XSRF-TOKEN cookie. It must not be HttpOnly so JavaScript libraries (like Axios) can read it.
        $secure = $request->isSecure();
        if (method_exists($response, 'cookie')) {
            $response->cookie('XSRF-TOKEN', Session::token(), 0, '/', '', $secure, false, 'Lax');
        } else {
            if (php_sapi_name() !== 'cli' && !headers_sent()) {
                setcookie('XSRF-TOKEN', Session::token(), [
                    'expires' => 0,
                    'path' => '/',
                    'domain' => '',
                    'secure' => $secure,
                    'httponly' => false,
                    'samesite' => 'Lax'
                ]);
            }
        }

        return $response;
    }

    /**
     * Determine if the request requires CSRF validation.
     */
    protected function shouldValidate(Request $request): bool
    {
        $method = $request->method();

        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return false;
        }

        return !$this->inExceptArray($request);
    }

    /**
     * Check if the request path matches any of the excluded patterns.
     */
    protected function inExceptArray(Request $request): bool
    {
        $path = $request->path();

        foreach ($this->except as $except) {
            if ($except === '/') {
                if ($path === '/') {
                    return true;
                }
                continue;
            }

            // Convert wildcard to regex pattern
            $pattern = '#^' . str_replace('\*', '.*', preg_quote($except, '#')) . '$#';
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieve the CSRF token from the request inputs or headers.
     */
    protected function getTokenFromRequest(Request $request): ?string
    {
        $token = $request->post('_token');

        if (!$token) {
            $token = $request->header('X-CSRF-TOKEN');
        }

        if (!$token) {
            $token = $request->header('X-XSRF-TOKEN');
        }

        return $token;
    }
}
