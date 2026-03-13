<?php

declare(strict_types=1);

use App\Controllers\Inventory\InboundController;
use App\Controllers\Inventory\OutboundController;
use App\Controllers\Inventory\TransferController;
use App\Support\Response;

$router->get('/api/health', function () {
    return Response::json([
        'status' => 'ok',
        'message' => 'API is working',
    ]);
});

$router->get('/api/inbound/generate-pallet', [InboundController::class, 'generatePallet']);
$router->get('/api/outbound/pallets', [OutboundController::class, 'pallets']);
$router->get('/api/transfer/source-pallets', [TransferController::class, 'sourcePallets']);
$router->get('/api/transfer/destination-pallets', [TransferController::class, 'destinationPallets']);