<?php

declare(strict_types=1);

namespace App\Support;

class Router
{
    private array $routes = [];

    public function get(string $uri, array|callable $action): void
    {
        $this->addRoute('GET', $uri, $action);
    }

    public function post(string $uri, array|callable $action): void
    {
        $this->addRoute('POST', $uri, $action);
    }

    private function addRoute(string $method, string $uri, array|callable $action): void
    {
        $this->routes[$method][rtrim($uri, '/') ?: '/'] = $action;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $uri = $request->uri();

        $action = $this->routes[$method][$uri] ?? null;

        if (!$action) {
            return Response::make('404 Not Found', 404);
        }

        if (is_callable($action)) {
            return $action($request);
        }

        [$controller, $methodName] = $action;
        $instance = new $controller();

        return $instance->$methodName($request);
    }
}