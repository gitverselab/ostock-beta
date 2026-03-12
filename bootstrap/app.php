<?php

declare(strict_types=1);

session_start();

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

error_reporting(E_ALL);
ini_set('display_errors', '1');

/*
|--------------------------------------------------------------------------
| Simple .env Loader
|--------------------------------------------------------------------------
*/
$envPath = BASE_PATH . '/.env';

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);

        $name = trim($name);
        $value = trim($value);

        // Remove wrapping quotes if present
        $value = trim($value, "\"'");

        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

/*
|--------------------------------------------------------------------------
| Simple Autoloader
|--------------------------------------------------------------------------
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

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? $appConfig['timezone'] ?? 'Asia/Manila');

use App\Support\Database;
use App\Support\Router;

Database::init($config['database']);

$router = new Router();

require BASE_PATH . '/routes/web.php';
require BASE_PATH . '/routes/api.php';