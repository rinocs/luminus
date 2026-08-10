<?php

use PHPUnit\Framework\TestCase;
use Luminus\Controller;
use Luminus\Request;
use Luminus\Response;
use Luminus\View;

class ControllerTest extends TestCase
{
    private string $viewsDir;

    protected function setUp(): void
    {
        $this->viewsDir = __DIR__ . '/fixtures/controller-views';
        if (!is_dir($this->viewsDir . '/layouts')) {
            mkdir($this->viewsDir . '/layouts', 0777, true);
        }

        file_put_contents(
            $this->viewsDir . '/layouts/main.php',
            '<html><body><?php $this->renderSection("content") ?></body></html>'
        );
        file_put_contents(
            $this->viewsDir . '/page.php',
            '<?php $this->layout("layouts.main") ?><p><?= $msg ?></p>'
        );
    }

    protected function tearDown(): void
    {
        $this->rmdirRecursive($this->viewsDir);
    }

    private function makeController(Request $request): Controller
    {
        $view = new View($this->viewsDir);

        return new class ($request, $view) extends Controller {
            public function renderPublic(string $template, array $data = []): Response
            {
                return $this->render($template, $data);
            }

            public function htmxRedirectPublic(string $url): Response
            {
                return $this->htmxRedirect($url);
            }

            public function safeHtmxRedirectPublic(string $url, string $default = '/'): Response
            {
                return $this->safeHtmxRedirect($url, $default);
            }

            public function withFlashPublic(Response $response, string $msg): Response
            {
                return $this->withFlash($response, $msg);
            }
        };
    }

    public function test_render_uses_full_layout_without_hx_request(): void
    {
        $request = new Request(server: ['REQUEST_METHOD' => 'GET']);
        $controller = $this->makeController($request);

        $response = $controller->renderPublic('page', ['msg' => 'Hello']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('<html><body><p>Hello</p></body></html>', (string) $response);
    }

    public function test_render_detects_hx_request_and_returns_partial(): void
    {
        $request = new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'HTTP_HX_REQUEST' => 'true',
        ]);
        $controller = $this->makeController($request);

        $response = $controller->renderPublic('page', ['msg' => 'Hello']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('<p>Hello</p>', (string) $response);
        $this->assertStringNotContainsString('<html>', (string) $response);
    }

    public function test_htmx_redirect_sets_header_and_200_status(): void
    {
        $request = new Request();
        $controller = $this->makeController($request);

        $response = $controller->htmxRedirectPublic('/dashboard');

        $this->assertSame(200, $response->getStatusCode());

        $ref = new ReflectionClass($response);
        $prop = $ref->getProperty('headers');
        $prop->setAccessible(true);
        $headers = $prop->getValue($response);

        $this->assertSame('/dashboard', $headers['HX-Redirect']);
    }

    public function test_with_flash_appends_oob_swap_div(): void
    {
        $request = new Request();
        $controller = $this->makeController($request);

        $response = (new Response())->body('<p>Content</p>');
        $response = $controller->withFlashPublic($response, 'Saved!');

        $body = (string) $response;
        $this->assertStringStartsWith('<p>Content</p>', $body);
        $this->assertStringContainsString('id="flash"', $body);
        $this->assertStringContainsString('hx-swap-oob="true"', $body);
        $this->assertStringContainsString('Saved!', $body);
    }

    public function test_safe_htmx_redirect_allows_safe_url(): void
    {
        $request = new Request();
        $controller = $this->makeController($request);

        $response = $controller->safeHtmxRedirectPublic('/dashboard');

        $this->assertSame(200, $response->getStatusCode());

        $ref = new ReflectionClass($response);
        $prop = $ref->getProperty('headers');
        $prop->setAccessible(true);
        $headers = $prop->getValue($response);

        $this->assertSame('/dashboard', $headers['HX-Redirect']);
    }

    public function test_safe_htmx_redirect_filters_unsafe_url_to_default(): void
    {
        $request = new Request();
        $controller = $this->makeController($request);

        // Try an unsafe external URL
        $response = $controller->safeHtmxRedirectPublic('http://evil.com/steal-credentials', '/fallback');

        $this->assertSame(200, $response->getStatusCode());

        $ref = new ReflectionClass($response);
        $prop = $ref->getProperty('headers');
        $prop->setAccessible(true);
        $headers = $prop->getValue($response);

        $this->assertSame('/fallback', $headers['HX-Redirect']);
    }

    private function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $f) {
            $path = "$dir/$f";
            is_dir($path) ? $this->rmdirRecursive($path) : unlink($path);
        }
        rmdir($dir);
    }
}
