<?php

namespace Luminus;

class SecurityHeadersMiddleware implements Middleware
{
    /**
     * Sensible default security headers.
     */
    protected array $headers = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    /**
     * Accept custom or overridden headers.
     */
    public function __construct(array $customHeaders = [])
    {
        $this->headers = array_merge($this->headers, $customHeaders);
    }

    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        foreach ($this->headers as $name => $value) {
            $response->header($name, $value);
        }

        return $response;
    }
}
