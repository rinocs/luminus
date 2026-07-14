<?php

use PHPUnit\Framework\TestCase;
use Luminus\View;

class ViewSafetyTest extends TestCase
{
    public function test_variable_collision(): void
    {
        $viewsPath = __DIR__ . '/views';
        if (!is_dir($viewsPath)) mkdir($viewsPath);
        file_put_contents($viewsPath . '/test_collision.php', '<?php echo $__template; ?>');

        $view = new View($viewsPath);
        $output = $view->render('test_collision', ['__template' => 'overwritten']);

        $this->assertSame('overwritten', $output);

        unlink($viewsPath . '/test_collision.php');
        rmdir($viewsPath);
    }
}
