<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap/app.php';

use App\Support\Request;

$request = new Request();
$response = $router->dispatch($request);
$response->send();