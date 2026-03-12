<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Support\Auth;
use App\Support\Response;

$router->get('/', function ($request) {
    if (Auth::check()) {
        return Response::redirect('/dashboard');
    }

    return Response::redirect('/login');
});

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/dashboard', [DashboardController::class, 'index']);