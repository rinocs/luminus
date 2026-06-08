<?php

use PHPUnit\Framework\TestCase;
use Luminus\Request;
use Luminus\Response;
use Luminus\Session;
use Luminus\StartSessionMiddleware;
use Luminus\CsrfMiddleware;
use Luminus\SecurityHeadersMiddleware;

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
}
