<?php

declare(strict_types=1);

session_start();

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/vendor/autoload.php';

$config = [
    'app' => require BASE_PATH . '/config/app.php',
    'database' => require BASE_PATH . '/config/database.php',
    'permissions' => require BASE_PATH . '/config/permissions.php',
];

$appConfig = $config['app'];

date_default_timezone_set($appConfig['timezone'] ?? 'Asia/Manila');

use App\Support\Database;
use App\Support\Router;

Database::init($config['database']);

$router = new Router();

require BASE_PATH . '/routes/web.php';
require BASE_PATH . '/routes/api.php';