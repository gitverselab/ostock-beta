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

    public function put(string $uri, array|callable $action): void
    {
        $this->addRoute('PUT', $uri, $action);
    }

    public function delete(string $uri, array|callable $action): void
    {
        $this->addRoute('DELETE', $uri, $action);
    }

    private function addRoute(string $method, string $uri, array|callable $action): void
    {
        $normalizedUri = rtrim($uri, '/') ?: '/';
        $this->routes[$method][$normalizedUri] = $action;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $uri = $request->uri();

        $action = $this->routes[$method][$uri] ?? null;

        if ($action === null) {
            return Response::make('404 Not Found', 404);
        }

        if (is_callable($action)) {
            return $action($request);
        }

        [$controller, $methodName] = $action;

        if (!class_exists($controller)) {
            return Response::make("Controller not found: {$controller}", 500);
        }

        $instance = new $controller();

        if (!method_exists($instance, $methodName)) {
            return Response::make("Method not found: {$methodName}", 500);
        }

        $response = $instance->$methodName($request);

        if (!$response instanceof Response) {
            return Response::make('Invalid response returned by controller.', 500);
        }

        return $response;
    }
}