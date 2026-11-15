<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Not installed yet (no .env / APP_KEY)? → web installer
$envFile = __DIR__.'/../.env';
if (! is_file(__DIR__.'/../storage/installed') && is_file(__DIR__.'/install.php')
    && (! is_file($envFile) || ! preg_match('/^APP_KEY=\S+/m', (string) file_get_contents($envFile)))) {
    header('Location: '.rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/').'/install.php');
    exit;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
