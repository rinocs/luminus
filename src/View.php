<?php

namespace Luminus;

class View
{
    private readonly string $viewsPath;
    private ?string $layout = null;
    private array $sections = [];
    private string $currentSection = '';

    public function __construct(string $viewsPath)
    {
        $this->viewsPath = rtrim($viewsPath, '/');
    }

    public function render(string $template, array $data = []): string
    {
        $this->layout = null;
        $this->sections = [];
        $this->currentSection = '';

        $content = $this->renderFile($template, $data);

        if ($this->layout) {
            if (empty($this->sections)) {
                $this->sections['content'] = $content;
            } elseif ($content !== '') {
                if (isset($this->sections['content'])) {
                    $this->sections['content'] = $content . $this->sections['content'];
                } else {
                    $this->sections['content'] = $content;
                }
            }
            return $this->renderFile($this->layout, $data);
        }

        return $content;
    }

    public function layout(string $layout): void
    {
        $this->layout = $layout;
    }

    public function section(string $name): void
    {
        if ($this->currentSection !== '') {
            $this->sections[$this->currentSection] = ob_get_clean();
        }
        $this->currentSection = $name;
        ob_start();
    }

    public function endSection(): void
    {
        if ($this->currentSection !== '') {
            $this->sections[$this->currentSection] = ob_get_clean();
            $this->currentSection = '';
        }
    }

    public function renderSection(string $name): void
    {
        echo $this->sections[$name] ?? '';
    }

    private function renderFile(string $__template, array $__data): string
    {
        $__file = $this->viewsPath . '/' . str_replace('.', '/', $__template) . '.php';

        if (!file_exists($__file)) {
            throw new \RuntimeException("View [{$__template}] not found: {$__file}");
        }

        extract($__data);
        ob_start();
        try {
            require $__file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return ob_get_clean();
    }
}
