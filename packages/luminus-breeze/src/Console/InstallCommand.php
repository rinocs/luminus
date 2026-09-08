<?php

namespace Luminus\Breeze\Console;

class InstallCommand
{
    private string $packagePath;
    private string $appPath;

    public function __construct()
    {
        $this->packagePath = realpath(__DIR__ . '/../..') ?: __DIR__ . '/../..';
        $this->appPath = getcwd() ?: '.';
    }

    public function handle(array $argv): int
    {
        $command = $argv[1] ?? 'install';

        switch ($command) {
            case 'install':
                return $this->install();
            case 'help':
            default:
                $this->help();
                return 0;
        }
    }

    public function install(): int
    {
        $this->header('Luminus Breeze Installer');

        if (!file_exists($this->appPath . '/composer.json')) {
            echo "\033[31mError: Could not find composer.json in the current directory.\033[0m\n";
            echo "Please run this command from the root of your Luminus application.\n";
            return 1;
        }

        // Publish views
        $this->publishViews();

        // Publish migrations
        $this->publishMigrations();

        $this->instructions();

        return 0;
    }

    private function publishViews(): void
    {
        $source = $this->packagePath . '/src/views';
        $target = $this->appPath . '/views/vendor/breeze';

        if (!is_dir($target)) {
            mkdir($target, 0775, true);
        }

        $this->copyDirectory($source, $target);
        echo "\033[32mPublished views to views/vendor/breeze\033[0m\n";
    }

    private function publishMigrations(): void
    {
        $source = $this->packagePath . '/database/migrations';
        $target = $this->appPath . '/database/migrations';

        if (!is_dir($source)) {
            echo "\033[33mNo migrations to publish.\033[0m\n";
            return;
        }

        if (!is_dir($target)) {
            mkdir($target, 0775, true);
        }

        $files = glob($source . '/*');
        if ($files === false || empty($files)) {
            echo "\033[33mNo migrations to publish.\033[0m\n";
            return;
        }

        foreach ($files as $file) {
            $dest = $target . '/' . basename($file);
            if (file_exists($dest)) {
                echo "\033[33mSkipped existing migration: " . basename($file) . "\033[0m\n";
                continue;
            }
            copy($file, $dest);
            echo "\033[32mPublished migration: " . basename($file) . "\033[0m\n";
        }
    }

    private function copyDirectory(string $source, string $target): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $dest = $target . '/' . substr($item->getPathname(), strlen($source) + 1);
            if ($item->isDir()) {
                if (!is_dir($dest)) {
                    mkdir($dest, 0775, true);
                }
            } else {
                copy($item->getPathname(), $dest);
            }
        }
    }

    private function instructions(): void
    {
        echo "\n";
        echo "═══════════════════════════════════════════════════════════════\n";
        echo "  Next steps:\n";
        echo "═══════════════════════════════════════════════════════════════\n";
        echo "  1. Add the provider to your application bootstrap:\n";
        echo "\n";
        echo "     \$app->registerProviders([\n";
        echo "         \\Luminus\\Breeze\\BreezeServiceProvider::class,\n";
        echo "     ]);\n";
        echo "\n";
        echo "  2. Ensure session middleware is active in your app:\n";
        echo "\n";
        echo "     \$app->addMiddleware(new \\Luminus\\StartSessionMiddleware());\n";
        echo "     \$app->addMiddleware(new \\Luminus\\CsrfMiddleware());\n";
        echo "\n";
        echo "  3. Run the published migrations:\n";
        echo "\n";
        echo "     php bin/migrate up\n";
        echo "\n";
        echo "  4. Visit /login and /register in your browser.\n";
        echo "═══════════════════════════════════════════════════════════════\n";
    }

    private function help(): void
    {
        echo "Luminus Breeze Installer\n\n";
        echo "Usage:\n";
        echo "  php vendor/luminus/breeze/bin/breeze <command>\n\n";
        echo "Commands:\n";
        echo "  install    Publish Breeze views and migrations\n";
        echo "  help       Show this help message\n";
    }

    private function header(string $title): void
    {
        $len = strlen($title);
        echo "╔═══════════════════════════════════════════════════════════════╗\n";
        echo "║  {$title}" . str_repeat(' ', 61 - $len) . "║\n";
        echo "╚═══════════════════════════════════════════════════════════════╝\n";
        echo "\n";
    }
}
