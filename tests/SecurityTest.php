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
}
