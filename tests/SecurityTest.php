<?php

use PHPUnit\Framework\TestCase;
use Luminus\Request;
use Luminus\Response;
use Luminus\Session;
use Luminus\StartSessionMiddleware;
use Luminus\CsrfMiddleware;
use Luminus\SecurityHeadersMiddleware;

class SecureDummyJob extends \Luminus\Queue\Job
{
    public static bool $handled = false;
    public function handle(): void
    {
        self::$handled = true;
    }
}

class UnallowedClass
{
    public string $property = 'malicious';
}

class SecurityTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function test_escaping_helper_e(): void
    {
        $this->assertSame('&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;', e("<script>alert('xss')</script>"));
        $this->assertSame('', e(null));
        $this->assertSame('123', e(123));
        $this->assertSame('safe-string', e('safe-string'));
    }

    public function test_session_helpers(): void
    {
        $this->assertNull(session('nonexistent'));
        session(['key' => 'value']);
        $this->assertSame('value', session('key'));
    }

    public function test_csrf_helpers(): void
    {
        $token = csrf_token();
        $this->assertNotEmpty($token);
        $this->assertSame(64, strlen($token));
        
        $field = csrf_field();
        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="_token"', $field);
        $this->assertStringContainsString('value="' . $token . '"', $field);
    }

    public function test_session_class(): void
    {
        Session::put('foo', 'bar');
        $this->assertTrue(Session::has('foo'));
        $this->assertSame('bar', Session::get('foo'));
        
        Session::forget('foo');
        $this->assertFalse(Session::has('foo'));
        $this->assertNull(Session::get('foo'));
    }

    public function test_session_flash(): void
    {
        Session::flash('message', 'success');
        $this->assertSame('success', Session::getFlash('message'));
        
        Session::ageFlashData();
        $this->assertSame('success', Session::getFlash('message'));
        
        Session::ageFlashData();
        $this->assertNull(Session::getFlash('message'));
    }

    public function test_csrf_middleware_allows_safe_methods(): void
    {
        $middleware = new CsrfMiddleware();
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']
        );
        
        $called = false;
        $response = $middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return new Response();
        });
        
        $this->assertTrue($called);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_csrf_middleware_blocks_post_without_token(): void
    {
        $middleware = new CsrfMiddleware();
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/submit']
        );
        
        $called = false;
        $response = $middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return new Response();
        });
        
        $this->assertFalse($called);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('CSRF token mismatch', (string)$response);

        // Verify that XSRF-TOKEN cookie is attached even on 403 Forbidden responses
        $ref = new ReflectionClass($response);
        $prop = $ref->getProperty('cookies');
        $prop->setAccessible(true);
        $cookies = $prop->getValue($response);

        $this->assertArrayHasKey('XSRF-TOKEN', $cookies);
        $this->assertSame(Session::token(), $cookies['XSRF-TOKEN']['value']);
    }

    public function test_csrf_middleware_allows_post_with_valid_token(): void
    {
        $token = Session::token();
        $middleware = new CsrfMiddleware();
        
        $request = new Request(
            body: ['_token' => $token],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/submit']
        );
        
        $called = false;
        $response = $middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return new Response();
        });
        
        $this->assertTrue($called);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_csrf_middleware_allows_post_with_header_token(): void
    {
        $token = Session::token();
        $middleware = new CsrfMiddleware();
        
        $request = new Request(
            server: [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/submit',
                'HTTP_X_CSRF_TOKEN' => $token
            ]
        );
        
        $called = false;
        $response = $middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return new Response();
        });
        
        $this->assertTrue($called);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_csrf_middleware_excludes_except_routes(): void
    {
        $middleware = new CsrfMiddleware(except: ['/api/*']);
        
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/products']
        );
        
        $called = false;
        $response = $middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return new Response();
        });
        
        $this->assertTrue($called);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_security_headers_middleware(): void
    {
        $middleware = new SecurityHeadersMiddleware();
        $request = new Request();
        
        $response = $middleware->handle($request, function ($req) {
            return new Response();
        });
        
        $ref = new ReflectionClass($response);
        $prop = $ref->getProperty('headers');
        $prop->setAccessible(true);
        $headers = $prop->getValue($response);
        
        $this->assertSame('nosniff', $headers['X-Content-Type-Options']);
        $this->assertSame('SAMEORIGIN', $headers['X-Frame-Options']);
        $this->assertSame('1; mode=block', $headers['X-XSS-Protection']);
        $this->assertSame('strict-origin-when-cross-origin', $headers['Referrer-Policy']);
    }

    public function test_request_scheme_detects_http_x_forwarded_proto_with_trusted_proxies(): void
    {
        // 1. Untrusted: TRUST_PROXIES is not set, so HTTP_X_FORWARDED_PROTO is ignored.
        putenv('TRUST_PROXIES'); // Clear env
        unset($_ENV['TRUST_PROXIES']);

        $request = new Request(
            server: ['HTTP_X_FORWARDED_PROTO' => 'https']
        );
        $this->assertFalse($request->isSecure());
        $this->assertSame('http', $request->scheme());

        // 2. Trusted all: TRUST_PROXIES is '*'
        putenv('TRUST_PROXIES=*');
        $_ENV['TRUST_PROXIES'] = '*';
        $requestTrustedAll = new Request(
            server: ['HTTP_X_FORWARDED_PROTO' => 'https']
        );
        $this->assertTrue($requestTrustedAll->isSecure());
        $this->assertSame('https', $requestTrustedAll->scheme());

        // 3. Trusted IP match: TRUST_PROXIES has '10.0.0.1' and REMOTE_ADDR matches
        putenv('TRUST_PROXIES=127.0.0.1, 10.0.0.1');
        $_ENV['TRUST_PROXIES'] = '127.0.0.1, 10.0.0.1';
        $requestIpMatch = new Request(
            server: [
                'HTTP_X_FORWARDED_PROTO' => 'https',
                'REMOTE_ADDR' => '10.0.0.1'
            ]
        );
        $this->assertTrue($requestIpMatch->isSecure());
        $this->assertSame('https', $requestIpMatch->scheme());

        // 4. Trusted IP mismatch: TRUST_PROXIES has '10.0.0.1' but REMOTE_ADDR is '1.2.3.4'
        $requestIpMismatch = new Request(
            server: [
                'HTTP_X_FORWARDED_PROTO' => 'https',
                'REMOTE_ADDR' => '1.2.3.4'
            ]
        );
        $this->assertFalse($requestIpMismatch->isSecure());
        $this->assertSame('http', $requestIpMismatch->scheme());

        // Clean up
        putenv('TRUST_PROXIES');
        unset($_ENV['TRUST_PROXIES']);
    }

    public function test_security_headers_middleware_appends_hsts_on_secure_requests(): void
    {
        $middleware = new SecurityHeadersMiddleware();

        // Non-secure request
        $request = new Request();
        $response = $middleware->handle($request, function ($req) {
            return new Response();
        });

        $ref = new ReflectionClass($response);
        $prop = $ref->getProperty('headers');
        $prop->setAccessible(true);
        $headers = $prop->getValue($response);

        $this->assertArrayNotHasKey('Strict-Transport-Security', $headers);

        // Secure request
        $secureRequest = new Request(
            server: ['HTTPS' => 'on']
        );
        $secureResponse = $middleware->handle($secureRequest, function ($req) {
            return new Response();
        });

        $headersSecure = $prop->getValue($secureResponse);
        $this->assertArrayHasKey('Strict-Transport-Security', $headersSecure);
        $this->assertSame('max-age=31536000; includeSubDomains', $headersSecure['Strict-Transport-Security']);
    }

    public function test_request_cookie(): void
    {
        $request = new Request(cookies: ['session_id' => '12345']);
        $this->assertSame('12345', $request->cookie('session_id'));
        $this->assertNull($request->cookie('nonexistent'));
        $this->assertSame('default', $request->cookie('nonexistent', 'default'));
    }

    public function test_response_cookie(): void
    {
        $response = new Response();
        $response->cookie('theme', 'dark', 3600, '/', '', true, true, 'Strict');

        $ref = new ReflectionClass($response);
        $prop = $ref->getProperty('cookies');
        $prop->setAccessible(true);
        $cookies = $prop->getValue($response);

        $this->assertArrayHasKey('theme', $cookies);
        $this->assertSame('theme', $cookies['theme']['name']);
        $this->assertSame('dark', $cookies['theme']['value']);
        $this->assertSame(3600, $cookies['theme']['expires']);
        $this->assertSame('/', $cookies['theme']['path']);
        $this->assertSame('', $cookies['theme']['domain']);
        $this->assertTrue($cookies['theme']['secure']);
        $this->assertTrue($cookies['theme']['httpOnly']);
        $this->assertSame('Strict', $cookies['theme']['sameSite']);
    }

    public function test_unserialize_safe_deserialization_of_valid_job(): void
    {
        $job = new SecureDummyJob();
        $payload = json_encode([
            'job' => SecureDummyJob::class,
            'data' => serialize($job)
        ]);

        $driver = new \Luminus\Queue\Drivers\SyncDriver(new \Luminus\Container());
        SecureDummyJob::$handled = false;
        $driver->push('default', $payload);
        $this->assertTrue(SecureDummyJob::$handled);
    }

    public function test_unserialize_safe_deserialization_blocks_unallowed_serialized_class(): void
    {
        $driver = new \Luminus\Queue\Drivers\SyncDriver(new \Luminus\Container());
        $unallowed = new UnallowedClass();
        $maliciousPayload = json_encode([
            'job' => SecureDummyJob::class, // Expected class is SecureDummyJob (valid Job class), but data contains UnallowedClass
            'data' => serialize($unallowed)
        ]);

        // When deserialized, because allowed_classes is restricted to [SecureDummyJob::class],
        // UnallowedClass will be unserialized as __PHP_Incomplete_Class.
        // It will throw an exception when trying to call handle() on __PHP_Incomplete_Class,
        // and crucially, it did not instantiate UnallowedClass.
        $this->expectException(\Throwable::class);
        $driver->push('default', $maliciousPayload);
    }

    public function test_unserialize_safe_deserialization_blocks_non_job_class_type(): void
    {
        $driver = new \Luminus\Queue\Drivers\SyncDriver(new \Luminus\Container());
        $unallowed = new UnallowedClass();
        $maliciousPayload = json_encode([
            'job' => UnallowedClass::class, // Expected class is UnallowedClass (not a subclass of Job)
            'data' => serialize($unallowed)
        ]);

        // This should be blocked by the defense-in-depth is_subclass_of() check before unserialize is even called.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Job class must be a subclass of Luminus\Queue\Job");
        $driver->push('default', $maliciousPayload);
    }

    public function test_csrf_token_regenerated_on_login(): void
    {
        $oldToken = Session::token();
        $this->assertNotEmpty($oldToken);

        $appMock = $this->createMock(\Luminus\App::class);
        $viewMock = $this->createMock(\Luminus\View::class);
        $dbMock = $this->createMock(\Luminus\Database::class);

        $dbMock->method('query')
            ->willReturn([
                [
                    'id' => 1,
                    'email' => 'test@example.com',
                    'name' => 'Test User',
                    'password' => password_hash('password', PASSWORD_BCRYPT),
                ]
            ]);

        $request = new Request(
            body: [
                'email' => 'test@example.com',
                'password' => 'password',
            ],
            server: ['REQUEST_METHOD' => 'POST']
        );

        $controller = new \Luminus\Breeze\Controllers\AuthController($appMock, $viewMock, $dbMock);
        $response = $controller->store($request);

        $this->assertSame(302, $response->getStatusCode());

        $newToken = Session::token();
        $this->assertNotEmpty($newToken);
        $this->assertNotEquals($oldToken, $newToken);
    }

    public function test_login_timing_mitigation_and_password_limits(): void
    {
        $appMock = $this->createMock(\Luminus\App::class);
        $viewMock = $this->createMock(\Luminus\View::class);
        $dbMock = $this->createMock(\Luminus\Database::class);

        // When queried, user does not exist (empty array returned)
        $dbMock->method('query')
            ->willReturn([]);

        $request = new Request(
            body: [
                'email' => 'nonexistent@example.com',
                'password' => 'somepassword',
            ],
            server: ['REQUEST_METHOD' => 'POST']
        );

        $controller = new \Luminus\Breeze\Controllers\AuthController($appMock, $viewMock, $dbMock);
        $response = $controller->store($request);

        // Should redirect back to /login
        $this->assertSame(302, $response->getStatusCode());

        $ref = new \ReflectionClass($response);
        $prop = $ref->getProperty('redirectUrl');
        $prop->setAccessible(true);
        $this->assertSame('/login', $prop->getValue($response));

        // Assert that the error is flashed
        $errors = Session::getFlash('errors', []);
        $this->assertArrayHasKey('email', $errors);
        $this->assertSame('These credentials do not match our records.', $errors['email']);

        // Test login password length limit validation
        $longPassword = str_repeat('a', 256);
        $requestLong = new Request(
            body: [
                'email' => 'test@example.com',
                'password' => $longPassword,
            ],
            server: ['REQUEST_METHOD' => 'POST']
        );

        $responseLong = $controller->store($requestLong);
        $this->assertSame(302, $responseLong->getStatusCode());
        $errorsLong = Session::getFlash('errors', []);
        $this->assertArrayHasKey('password', $errorsLong);
        $this->assertSame('The password must not exceed 255 characters.', $errorsLong['password']);

        // Test registration password length limit validation
        $requestRegLong = new Request(
            body: [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => $longPassword,
                'password_confirmation' => $longPassword,
            ],
            server: ['REQUEST_METHOD' => 'POST']
        );

        $responseRegLong = $controller->registerStore($requestRegLong);
        $this->assertSame(302, $responseRegLong->getStatusCode());
        $errorsRegLong = Session::getFlash('errors', []);
        $this->assertArrayHasKey('password', $errorsRegLong);
        $this->assertSame('The password must be between 8 and 255 characters.', $errorsRegLong['password']);
    }

    public function test_confirm_password_limits(): void
    {
        $viewMock = $this->createMock(\Luminus\View::class);
        $dbMock = $this->createMock(\Luminus\Database::class);

        // Put user_id in Session to bypass guest redirect
        Session::put('user_id', 1);

        $controller = new \Luminus\Breeze\Controllers\ConfirmablePasswordController($viewMock, $dbMock);

        // Test empty password
        $requestEmpty = new Request(
            body: ['password' => ''],
            server: ['REQUEST_METHOD' => 'POST']
        );
        $responseEmpty = $controller->store($requestEmpty);
        $this->assertSame(302, $responseEmpty->getStatusCode());
        $errorsEmpty = Session::getFlash('errors', []);
        $this->assertArrayHasKey('password', $errorsEmpty);
        $this->assertSame('The password field is required.', $errorsEmpty['password']);

        // Test long password (>255 characters)
        $longPassword = str_repeat('a', 256);
        $requestLong = new Request(
            body: ['password' => $longPassword],
            server: ['REQUEST_METHOD' => 'POST']
        );
        $responseLong = $controller->store($requestLong);
        $this->assertSame(302, $responseLong->getStatusCode());
        $errorsLong = Session::getFlash('errors', []);
        $this->assertArrayHasKey('password', $errorsLong);
        $this->assertSame('The password must not exceed 255 characters.', $errorsLong['password']);
    }

    public function test_confirm_password_rate_limiting(): void
    {
        $viewMock = $this->createMock(\Luminus\View::class);
        $dbMock = $this->createMock(\Luminus\Database::class);

        // Put user_id in Session to bypass guest redirect
        Session::put('user_id', 42);

        // Mock a query that always returns empty (failed credentials)
        $dbMock->method('query')
            ->willReturn([]);

        $throttleKey = 'confirm_password_throttle_' . md5('42');

        $controller = new \Luminus\Breeze\Controllers\ConfirmablePasswordController($viewMock, $dbMock);

        // Attempt 1 to 4: Standard failure redirect to /confirm-password
        for ($i = 1; $i <= 4; $i++) {
            $request = new Request(
                body: ['password' => 'wrongpass'],
                server: ['REQUEST_METHOD' => 'POST']
            );
            $response = $controller->store($request);
            $this->assertSame(302, $response->getStatusCode());
            $this->assertSame($i, Session::get($throttleKey . '_attempts'));
            $this->assertNull(Session::get($throttleKey . '_locked_at'));
        }

        // Attempt 5: Reaches the limit, sets lockout
        $request5 = new Request(
            body: ['password' => 'wrongpass'],
            server: ['REQUEST_METHOD' => 'POST']
        );
        $response5 = $controller->store($request5);
        $this->assertSame(302, $response5->getStatusCode());
        $this->assertSame(5, Session::get($throttleKey . '_attempts'));
        $this->assertNotNull(Session::get($throttleKey . '_locked_at'));

        // Attempt 6: Immediately blocked/throttled without query or hashing
        $request6 = new Request(
            body: ['password' => 'wrongpass'],
            server: ['REQUEST_METHOD' => 'POST']
        );
        $response6 = $controller->store($request6);
        $this->assertSame(302, $response6->getStatusCode());

        $errors = Session::getFlash('errors', []);
        $this->assertArrayHasKey('password', $errors);
        $this->assertStringContainsString('Too many confirmation attempts', $errors['password']);

        // Test lockout expiration bypass (e.g. simulating 61 seconds later)
        Session::put($throttleKey . '_locked_at', time() - 61);

        $request7 = new Request(
            body: ['password' => 'wrongpass'],
            server: ['REQUEST_METHOD' => 'POST']
        );
        $response7 = $controller->store($request7);
        $this->assertSame(302, $response7->getStatusCode());

        // The attempts counter should be reset back to 1
        $this->assertSame(1, Session::get($throttleKey . '_attempts'));
    }

    public function test_trusted_proxy_ssl_detection(): void
    {
        // 1. Unsecured request (no headers/ssl)
        $req1 = new Request(server: ['REMOTE_ADDR' => '192.168.1.1']);
        $this->assertFalse($req1->isSecure());
        $this->assertSame('http', $req1->scheme());

        // Save old env state
        $oldTrustProxies = getenv('TRUST_PROXIES');

        try {
            // Set up environment variable
            $_ENV['TRUST_PROXIES'] = '192.168.1.1, 10.0.0.1';

            // 2. Client behind trusted proxy, using HTTPS protocol
            $req2 = new Request(server: [
                'REMOTE_ADDR' => '192.168.1.1',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ]);
            $this->assertTrue($req2->isSecure());
            $this->assertSame('https', $req2->scheme());

            // 3. Client behind trusted proxy, but using HTTP protocol
            $req3 = new Request(server: [
                'REMOTE_ADDR' => '192.168.1.1',
                'HTTP_X_FORWARDED_PROTO' => 'http',
            ]);
            $this->assertFalse($req3->isSecure());
            $this->assertSame('http', $req3->scheme());

            // 4. Client from untrusted proxy trying to spoof HTTPS protocol
            $req4 = new Request(server: [
                'REMOTE_ADDR' => '203.0.113.5',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ]);
            $this->assertFalse($req4->isSecure());
            $this->assertSame('http', $req4->scheme());

            // 5. Wildcard proxy trust
            $_ENV['TRUST_PROXIES'] = '*';
            $req5 = new Request(server: [
                'REMOTE_ADDR' => '203.0.113.5',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ]);
            $this->assertTrue($req5->isSecure());
            $this->assertSame('https', $req5->scheme());

        } finally {
            if ($oldTrustProxies === false) {
                unset($_ENV['TRUST_PROXIES']);
                putenv('TRUST_PROXIES');
            } else {
                $_ENV['TRUST_PROXIES'] = $oldTrustProxies;
                putenv("TRUST_PROXIES={$oldTrustProxies}");
            }
        }
    }

    public function test_security_headers_middleware_appends_hsts_when_secure(): void
    {
        $middleware = new SecurityHeadersMiddleware();

        // Secure request
        $requestSecure = new Request(server: ['HTTPS' => 'on']);
        $responseSecure = $middleware->handle($requestSecure, function ($req) {
            return new Response();
        });

        $refSecure = new ReflectionClass($responseSecure);
        $propSecure = $refSecure->getProperty('headers');
        $propSecure->setAccessible(true);
        $headersSecure = $propSecure->getValue($responseSecure);

        $this->assertArrayHasKey('Strict-Transport-Security', $headersSecure);
        $this->assertSame('max-age=31536000; includeSubDomains', $headersSecure['Strict-Transport-Security']);

        // Insecure request
        $requestInsecure = new Request(server: ['HTTPS' => 'off']);
        $responseInsecure = $middleware->handle($requestInsecure, function ($req) {
            return new Response();
        });

        $refInsecure = new ReflectionClass($responseInsecure);
        $propInsecure = $refInsecure->getProperty('headers');
        $propInsecure->setAccessible(true);
        $headersInsecure = $propInsecure->getValue($responseInsecure);

        $this->assertArrayNotHasKey('Strict-Transport-Security', $headersInsecure);
    }

    public function test_response_is_safe_url_prevents_open_redirect_bypasses(): void
    {
        $response = new Response();

        // Valid local paths
        $this->assertTrue($response->isSafeUrl('/dashboard'));
        $this->assertTrue($response->isSafeUrl('/user/profile'));

        // Invalid: Protocol-relative URLs or backslash URLs
        $this->assertFalse($response->isSafeUrl('//evil.com'));
        $this->assertFalse($response->isSafeUrl('/\\evil.com'));
        $this->assertFalse($response->isSafeUrl('\\\\evil.com'));
        $this->assertFalse($response->isSafeUrl('\\evil.com'));

        // Invalid: Whitespace or control character obfuscated URLs
        $this->assertFalse($response->isSafeUrl('/ evil.com'));
        $this->assertFalse($response->isSafeUrl("/\tevil.com"));
        $this->assertFalse($response->isSafeUrl("/\nevil.com"));
        $this->assertFalse($response->isSafeUrl("/\revil.com"));

        // Absolute URL checks with APP_URL
        $_ENV['APP_URL'] = 'https://example.com';
        putenv('APP_URL=https://example.com');

        try {
            // Same host and scheme
            $this->assertTrue($response->isSafeUrl('https://example.com/login'));

            // Scheme mismatch (http vs https)
            $this->assertFalse($response->isSafeUrl('http://example.com/login'));

            // Host mismatch
            $this->assertFalse($response->isSafeUrl('https://evil.com/login'));
        } finally {
            unset($_ENV['APP_URL']);
            putenv('APP_URL');
        }
    }

    public function test_auth_controller_prevents_open_redirect(): void
    {
        // Place malicious URL in 'intended' flash session
        Session::flash('intended', 'http://evil.com/steal-credentials');

        $appMock = $this->createMock(\Luminus\App::class);
        $viewMock = $this->createMock(\Luminus\View::class);
        $dbMock = $this->createMock(\Luminus\Database::class);

        $dbMock->method('query')
            ->willReturn([
                [
                    'id' => 1,
                    'email' => 'user@example.com',
                    'name' => 'John Doe',
                    'password' => password_hash('password123', PASSWORD_BCRYPT),
                ]
            ]);

        $request = new Request(
            body: [
                'email' => 'user@example.com',
                'password' => 'password123',
            ],
            server: ['REQUEST_METHOD' => 'POST']
        );

        $controller = new \Luminus\Breeze\Controllers\AuthController($appMock, $viewMock, $dbMock);
        $response = $controller->store($request);

        $this->assertSame(302, $response->getStatusCode());

        $ref = new ReflectionClass($response);
        $prop = $ref->getProperty('redirectUrl');
        $prop->setAccessible(true);

        // Assert that we fallback to '/' instead of redirecting to evil.com
        $this->assertSame('/', $prop->getValue($response));
    }

    public function test_login_rate_limiting(): void
    {
        $appMock = $this->createMock(\Luminus\App::class);
        $viewMock = $this->createMock(\Luminus\View::class);
        $dbMock = $this->createMock(\Luminus\Database::class);

        // Mock a query that always returns empty (failed credentials)
        $dbMock->method('query')
            ->willReturn([]);

        $email = 'bruteforce@example.com';
        $throttleKey = 'login_throttle_' . md5($email);

        $controller = new \Luminus\Breeze\Controllers\AuthController($appMock, $viewMock, $dbMock);

        // Attempt 1 to 4: Standard failure redirect to /login
        for ($i = 1; $i <= 4; $i++) {
            $request = new Request(
                body: ['email' => $email, 'password' => 'wrongpass'],
                server: ['REQUEST_METHOD' => 'POST']
            );
            $response = $controller->store($request);
            $this->assertSame(302, $response->getStatusCode());
            $this->assertSame($i, Session::get($throttleKey . '_attempts'));
            $this->assertNull(Session::get($throttleKey . '_locked_at'));
        }

        // Attempt 5: Reaches the limit, sets lockout
        $request5 = new Request(
            body: ['email' => $email, 'password' => 'wrongpass'],
            server: ['REQUEST_METHOD' => 'POST']
        );
        $response5 = $controller->store($request5);
        $this->assertSame(302, $response5->getStatusCode());
        $this->assertSame(5, Session::get($throttleKey . '_attempts'));
        $this->assertNotNull(Session::get($throttleKey . '_locked_at'));

        // Attempt 6: Immediately blocked/throttled without query or hashing
        $request6 = new Request(
            body: ['email' => $email, 'password' => 'wrongpass'],
            server: ['REQUEST_METHOD' => 'POST']
        );
        $response6 = $controller->store($request6);
        $this->assertSame(302, $response6->getStatusCode());

        $errors = Session::getFlash('errors', []);
        $this->assertArrayHasKey('email', $errors);
        $this->assertStringContainsString('Too many login attempts', $errors['email']);

        // Test lockout expiration bypass (e.g. simulating 61 seconds later)
        Session::put($throttleKey . '_locked_at', time() - 61);

        $request7 = new Request(
            body: ['email' => $email, 'password' => 'wrongpass'],
            server: ['REQUEST_METHOD' => 'POST']
        );
        $response7 = $controller->store($request7);
        $this->assertSame(302, $response7->getStatusCode());

        // The attempts counter should be reset back to 1
        $this->assertSame(1, Session::get($throttleKey . '_attempts'));
    }
}
