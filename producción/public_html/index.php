<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$directory = realpath(__DIR__) ?: __DIR__;
$laravelPath = null;

for ($depth = 0; $depth < 6; $depth++) {
    foreach ([$directory, $directory.DIRECTORY_SEPARATOR.'laravel'] as $candidate) {
        if (
            is_file($candidate.DIRECTORY_SEPARATOR.'artisan')
            && is_file($candidate.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php')
            && is_file($candidate.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php')
        ) {
            $laravelPath = realpath($candidate);
            break 2;
        }
    }

    $parent = dirname($directory);
    if ($parent === $directory) {
        break;
    }
    $directory = $parent;
}

if ($laravelPath === null) {
    error_log('Almacena could not locate its Laravel application from the public entry point.');
    http_response_code(500);
    exit('Application is not configured.');
}

if (file_exists($maintenance = $laravelPath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $laravelPath.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $laravelPath.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
