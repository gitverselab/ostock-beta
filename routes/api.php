<?php

declare(strict_types=1);

use App\Controllers\Inventory\InboundController;
use App\Support\Response;

$router->get('/api/health', function () {
    return Response::json([
        'status' => 'ok',
        'message' => 'API is working',
    ]);
});

$router->get('/api/inbound/generate-pallet', [InboundController::class, 'generatePallet']);