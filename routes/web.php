<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\Inventory\InboundController;
use App\Controllers\Inventory\OutboundController;
use App\Controllers\Master\ItemController;
use App\Controllers\Master\WarehouseController;
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

$router->get('/items', [ItemController::class, 'index']);
$router->get('/items/create', [ItemController::class, 'create']);
$router->post('/items/create', [ItemController::class, 'store']);
$router->get('/items/edit', [ItemController::class, 'edit']);
$router->post('/items/edit', [ItemController::class, 'update']);
$router->get('/items/delete', [ItemController::class, 'delete']);
$router->post('/items/delete', [ItemController::class, 'destroy']);

$router->get('/warehouses', [WarehouseController::class, 'index']);
$router->get('/warehouses/create', [WarehouseController::class, 'create']);
$router->post('/warehouses/create', [WarehouseController::class, 'store']);
$router->get('/warehouses/edit', [WarehouseController::class, 'edit']);
$router->post('/warehouses/edit', [WarehouseController::class, 'update']);
$router->get('/warehouses/delete', [WarehouseController::class, 'delete']);
$router->post('/warehouses/delete', [WarehouseController::class, 'destroy']);

$router->get('/inventory/inbound', [InboundController::class, 'create']);
$router->post('/inventory/inbound', [InboundController::class, 'store']);
$router->get('/inventory/inbound/history', [InboundController::class, 'history']);

$router->get('/inventory/outbound', [OutboundController::class, 'create']);
$router->post('/inventory/outbound', [OutboundController::class, 'store']);
$router->get('/inventory/outbound/history', [OutboundController::class, 'history']);