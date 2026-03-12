<?php

declare(strict_types=1);

namespace App\Support;

class View
{
    public static function render(string $view, array $data = [], string $layout = 'app'): string
    {
        $viewPath = BASE_PATH . '/app/Views/' . str_replace('.', '/', $view) . '.php';
        $layoutPath = BASE_PATH . '/app/Views/layouts/' . $layout . '.php';

        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View not found: {$viewPath}");
        }

        if (!file_exists($layoutPath)) {
            throw new \RuntimeException("Layout not found: {$layoutPath}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        ob_start();
        require $layoutPath;
        return ob_get_clean();
    }

    public static function partial(string $partial, array $data = []): void
    {
        $partialPath = BASE_PATH . '/app/Views/' . str_replace('.', '/', $partial) . '.php';

        if (!file_exists($partialPath)) {
            throw new \RuntimeException("Partial not found: {$partialPath}");
        }

        extract($data, EXTR_SKIP);
        require $partialPath;
    }
}