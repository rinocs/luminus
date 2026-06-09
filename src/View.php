<?php

namespace Luminus;

class View
{
    private string $viewsPath;
    private ?string $layout = null;
    private array $sections = [];
    private string $currentSection = '';

    public function __construct(string $viewsPath)
    {
        $this->viewsPath = rtrim($viewsPath, '/');
    }

    public function render(string $template, array $data = []): string
    {
        // Save state so render() is re-entrant (partials rendered inside a
        // template must not clobber the outer layout/sections).
        $previousState = [$this->layout, $this->sections, $this->currentSection];
        $this->layout = null;
        $this->sections = [];
        $this->currentSection = '';

        try {
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
        } finally {
            [$this->layout, $this->sections, $this->currentSection] = $previousState;
        }
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

    private function renderFile(string $template, array $data): string
    {
        $file = $this->viewsPath . '/' . str_replace('.', '/', $template) . '.php';

        if (!file_exists($file)) {
            throw new \RuntimeException("View [{$template}] not found: {$file}");
        }

        $bufferLevel = ob_get_level();
        extract($data, EXTR_SKIP);
        ob_start();

        try {
            require $file;
            return ob_get_clean();
        } catch (\Throwable $e) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            throw $e;
        }
    }
}
