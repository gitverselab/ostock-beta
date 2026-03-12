<?php

declare(strict_types=1);

session_start();

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

error_reporting(E_ALL);
ini_set('display_errors', '1');

/*
|--------------------------------------------------------------------------
| Simple Autoloader
|--------------------------------------------------------------------------
| This lets your namespaced classes under App\... load automatically
| without needing Composer yet.
*/
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = APP_PATH . '/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

/*
|--------------------------------------------------------------------------
| Config
|--------------------------------------------------------------------------
*/
$config = [
    'app' => require BASE_PATH . '/config/app.php',
    'database' => require BASE_PATH . '/config/database.php',
    'permissions' => require BASE_PATH . '/config/permissions.php',
];

$appConfig = $config['app'];

date_default_timezone_set($appConfig['timezone'] ?? 'Asia/Manila');

/*
|--------------------------------------------------------------------------
| Boot Core Services
|--------------------------------------------------------------------------
*/
use App\Support\Database;
use App\Support\Router;

Database::init($config['database']);

$router = new Router();

/*
|--------------------------------------------------------------------------
| Register Routes
|--------------------------------------------------------------------------
*/
require BASE_PATH . '/routes/web.php';
require BASE_PATH . '/routes/api.php';