<?php

use PHPUnit\Framework\TestCase;
use Luminus\View;

class ViewTest extends TestCase
{
    private string $viewsDir;
    private View $view;

    protected function setUp(): void
    {
        $this->viewsDir = __DIR__ . '/fixtures/views';
        $this->ensureFixtures();
        $this->view = new View($this->viewsDir);
    }

    private function ensureFixtures(): void
    {
        $dir = $this->viewsDir;
        if (!is_dir($dir)) {
            mkdir($dir . '/layouts', 0777, true);
        }

        file_put_contents($dir . '/simple.php', '<h1><?= $title ?></h1>');
        file_put_contents($dir . '/layouts/main.php', '<html><body><?php $this->renderSection("header") ?><?php $this->renderSection("content") ?></body></html>');
        file_put_contents($dir . '/with-layout.php', '<?php $this->layout("layouts.main") ?><?php $this->section("content") ?><p><?= $body ?></p><?php $this->endSection() ?>');
        file_put_contents($dir . '/multi-section.php', '<?php $this->layout("layouts.main") ?><?php $this->section("header") ?><header>H</header><?php $this->endSection() ?><?php $this->section("content") ?><main>M</main><?php $this->endSection() ?>');
    }

    public function test_render_simple_template(): void
    {
        $output = $this->view->render('simple', ['title' => 'Hello']);
        $this->assertSame('<h1>Hello</h1>', $output);
    }

    public function test_render_with_layout(): void
    {
        $output = $this->view->render('with-layout', ['body' => 'Content']);
        $this->assertSame('<html><body><p>Content</p></body></html>', $output);
    }

    public function test_render_with_multiple_sections(): void
    {
        $output = $this->view->render('multi-section', []);
        $this->assertStringContainsString('<header>H</header>', $output);
        $this->assertStringContainsString('<main>M</main>', $output);
    }

    public function test_render_throws_for_missing_view(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->view->render('nonexistent');
    }

    protected function tearDown(): void
    {
        $this->rmdirRecursive($this->viewsDir);
    }

    private function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $f) {
            $path = "$dir/$f";
            is_dir($path) ? $this->rmdirRecursive($path) : unlink($path);
        }
        rmdir($dir);
    }
}
