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
            throw new \RuntimeException("View not found: {$view}");
        }

        extract($data);

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

        extract($data);

        if (file_exists($partialPath)) {
            require $partialPath;
        }
    }
}