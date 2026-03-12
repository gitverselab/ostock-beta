<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Response;
use App\Support\View;

abstract class BaseController
{
    protected function view(string $view, array $data = [], string $layout = 'app'): Response
    {
        return Response::make(View::render($view, $data, $layout));
    }

    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $url): Response
    {
        return Response::redirect($url);
    }
}