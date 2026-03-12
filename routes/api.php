<?php

declare(strict_types=1);

$router->get('/api/health', function () {
    return \App\Support\Response::json([
        'status' => 'ok',
        'message' => 'API is working'
    ]);
});