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
}
