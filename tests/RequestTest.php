<?php

use PHPUnit\Framework\TestCase;
use Luminus\Request;

class RequestTest extends TestCase
{
    public function test_capture_creates_request(): void
    {
        $req = Request::capture();
        $this->assertInstanceOf(Request::class, $req);
    }

    public function test_constructor_accepts_custom_data(): void
    {
        $req = new Request(
            query: ['page' => 2],
            body: ['name' => 'test'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/submit'],
        );

        $this->assertSame(2, $req->query('page'));
        $this->assertSame('test', $req->post('name'));
        $this->assertSame('test', $req->input('name'));
        $this->assertSame('POST', $req->method());
        $this->assertSame('/submit', $req->path());
    }

    public function test_input_falls_back_between_query_and_body(): void
    {
        $req = new Request(
            query: ['q' => 'search'],
            body: ['b' => 'body'],
            server: ['REQUEST_METHOD' => 'POST'],
        );

        $this->assertSame('search', $req->input('q'));
        $this->assertSame('body', $req->input('b'));
    }

    public function test_input_returns_default(): void
    {
        $req = new Request();
        $this->assertNull($req->input('nonexistent'));
        $this->assertSame('default', $req->input('nonexistent', 'default'));
    }

    public function test_method_spoofing_via_post(): void
    {
        $req = new Request(
            body: ['_method' => 'DELETE'],
            server: ['REQUEST_METHOD' => 'POST'],
        );
        $this->assertSame('DELETE', $req->method());
    }

    public function test_path_normalizes_trailing_slash(): void
    {
        $req = new Request(
            server: ['REQUEST_URI' => '/foo/'],
        );
        $this->assertSame('/foo', $req->path());
    }

    public function test_path_returns_root_for_empty(): void
    {
        $req = new Request(
            server: ['REQUEST_URI' => ''],
        );
        $this->assertSame('/', $req->path());
    }

    public function test_all_merges_query_and_body(): void
    {
        $req = new Request(
            query: ['q' => 'search'],
            body: ['page' => 1],
        );
        $this->assertSame(['q' => 'search', 'page' => 1], $req->all());
    }

    public function test_header(): void
    {
        $req = new Request(
            server: ['HTTP_CONTENT_TYPE' => 'application/json', 'HTTP_X_API_KEY' => 'secret'],
        );
        $this->assertSame('application/json', $req->header('Content-Type'));
        $this->assertSame('secret', $req->header('X-API-Key'));
        $this->assertNull($req->header('Nonexistent'));
    }

    public function test_json(): void
    {
        $req = new Request();
        $this->assertNull($req->json());
    }

    public function test_method_returns_get_by_default(): void
    {
        $req = new Request();
        $this->assertSame('GET', $req->method());
    }

    public function test_uri(): void
    {
        $req = new Request(
            server: ['REQUEST_URI' => '/foo?bar=baz'],
        );
        $this->assertSame('/foo?bar=baz', $req->uri());
    }

    public function test_is_method(): void
    {
        $req = new Request(
            server: ['REQUEST_METHOD' => 'POST'],
        );
        $this->assertTrue($req->isMethod('post'));
        $this->assertTrue($req->isMethod('POST'));
        $this->assertFalse($req->isMethod('GET'));
    }

    public function test_scheme_http(): void
    {
        $req = new Request(server: ['HTTPS' => 'off']);
        $this->assertSame('http', $req->scheme());
    }

    public function test_scheme_https(): void
    {
        $req = new Request(server: ['HTTPS' => 'on']);
        $this->assertSame('https', $req->scheme());
    }

    public function test_scheme_https_via_port(): void
    {
        $req = new Request(server: ['SERVER_PORT' => 443]);
        $this->assertSame('https', $req->scheme());
    }

    public function test_host(): void
    {
        $req = new Request(server: ['HTTP_HOST' => 'example.com']);
        $this->assertSame('example.com', $req->host());
    }

    public function test_host_falls_back_to_server_name(): void
    {
        $req = new Request(server: ['SERVER_NAME' => 'api.example.com']);
        $this->assertSame('api.example.com', $req->host());
    }

    public function test_is_secure(): void
    {
        $req = new Request(server: ['HTTPS' => 'on']);
        $this->assertTrue($req->isSecure());

        $req2 = new Request(server: ['HTTPS' => 'off']);
        $this->assertFalse($req2->isSecure());
    }
}
