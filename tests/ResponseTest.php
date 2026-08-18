<?php

use PHPUnit\Framework\TestCase;
use Luminus\Response;

class ResponseTest extends TestCase
{
    private Response $response;

    protected function setUp(): void
    {
        $this->response = new Response();
    }

    public function test_default_status_is_200(): void
    {
        $this->assertSame(200, $this->response->getStatusCode());
    }

    public function test_status_returns_self(): void
    {
        $ret = $this->response->status(404);
        $this->assertSame($this->response, $ret);
    }

    public function test_status_sets_code(): void
    {
        $this->response->status(201);
        $this->assertSame(201, $this->response->getStatusCode());
    }

    public function test_header_returns_self(): void
    {
        $ret = $this->response->header('X-Foo', 'bar');
        $this->assertSame($this->response, $ret);
    }

    public function test_body_content(): void
    {
        $this->response->body('Hello');
        $this->assertSame('Hello', (string) $this->response);
    }

    public function test_json_sets_content_type_and_body(): void
    {
        $this->response->json(['key' => 'value']);

        ob_start();
        $this->response->send();
        $output = ob_get_clean();

        $this->assertSame('{"key":"value"}', $output);
    }

    public function test_json_sets_status(): void
    {
        $this->response->json(['error' => 'not found'], 404);
        $this->assertSame(404, $this->response->getStatusCode());
    }

    public function test_send_outputs_body_text(): void
    {
        $this->response->body('Hello World');

        ob_start();
        $this->response->send();
        $output = ob_get_clean();

        $this->assertSame('Hello World', $output);
    }

    public function test_redirect_sets_location_and_status(): void
    {
        $res = $this->response->redirect('/login', 302);

        $this->assertSame(302, $res->getStatusCode());

        ob_start();
        $res->send();
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    public function test_redirect_returns_response(): void
    {
        $res = $this->response->redirect('/new-page');
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(302, $res->getStatusCode());
    }

    public function test_chained_calls(): void
    {
        $this->response
            ->status(201)
            ->header('X-Custom', 'val')
            ->body('created');

        ob_start();
        $this->response->send();
        $output = ob_get_clean();

        $this->assertSame('created', $output);
        $this->assertSame(201, $this->response->getStatusCode());
    }

    public function test_get_status_code(): void
    {
        $this->response->status(500);
        $this->assertSame(500, $this->response->getStatusCode());
    }

    public function test_body_returns_self(): void
    {
        $ret = $this->response->body('test');
        $this->assertSame($this->response, $ret);
    }

    public function test_is_safe_url(): void
    {
        // 1. Safe relative paths
        $this->assertTrue($this->response->isSafeUrl('/dashboard'));
        $this->assertTrue($this->response->isSafeUrl('/'));
        $this->assertTrue($this->response->isSafeUrl('/home?user=1'));

        // 2. Unsafe relative paths (potential protocol relative or obfuscated paths)
        $this->assertFalse($this->response->isSafeUrl(''));
        $this->assertFalse($this->response->isSafeUrl('//evil.com'));
        $this->assertFalse($this->response->isSafeUrl('/\\evil.com'));
        $this->assertFalse($this->response->isSafeUrl('/ evil.com'));
        $this->assertFalse($this->response->isSafeUrl("/\tevil.com"));
        $this->assertFalse($this->response->isSafeUrl("/\revil.com"));
        $this->assertFalse($this->response->isSafeUrl("/\nevil.com"));
        $this->assertFalse($this->response->isSafeUrl('\\evil.com'));

        // 3. Absolute URLs matching APP_URL config
        $oldAppUrl = $_ENV['APP_URL'] ?? getenv('APP_URL') ?: '';
        $_ENV['APP_URL'] = 'http://localhost:8080';

        try {
            $this->assertTrue($this->response->isSafeUrl('http://localhost:8080/dashboard'));
            $this->assertTrue($this->response->isSafeUrl('https://localhost:8080/home'));
            $this->assertFalse($this->response->isSafeUrl('http://evil.com/dashboard'));
            $this->assertFalse($this->response->isSafeUrl('javascript://localhost:8080'));
            $this->assertFalse($this->response->isSafeUrl('data://localhost:8080'));
            $this->assertFalse($this->response->isSafeUrl('ftp://localhost:8080'));
        } finally {
            if ($oldAppUrl !== '') {
                $_ENV['APP_URL'] = $oldAppUrl;
            } else {
                unset($_ENV['APP_URL']);
            }
        }
    }

    public function test_safe_redirect(): void
    {
        $oldAppUrl = $_ENV['APP_URL'] ?? getenv('APP_URL') ?: '';
        $_ENV['APP_URL'] = 'http://localhost:8080';

        try {
            // Safe relative URL redirect
            $res = $this->response->safeRedirect('/dashboard');
            $ref = new ReflectionClass($res);
            $prop = $ref->getProperty('redirectUrl');
            $prop->setAccessible(true);
            $this->assertSame('/dashboard', $prop->getValue($res));

            // Safe absolute URL redirect
            $res2 = (new Response())->safeRedirect('http://localhost:8080/home');
            $this->assertSame('http://localhost:8080/home', $prop->getValue($res2));

            // Unsafe URL redirect should fall back to default '/'
            $res3 = (new Response())->safeRedirect('http://evil.com/dashboard');
            $this->assertSame('/', $prop->getValue($res3));

            // Unsafe URL redirect should fall back to specified default '/fallback'
            $res4 = (new Response())->safeRedirect('//evil.com/dashboard', '/fallback');
            $this->assertSame('/fallback', $prop->getValue($res4));
        } finally {
            if ($oldAppUrl !== '') {
                $_ENV['APP_URL'] = $oldAppUrl;
            } else {
                unset($_ENV['APP_URL']);
            }
        }
    }
}
