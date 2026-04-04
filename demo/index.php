<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Coagus\PhpApiBuilder\API;
use Coagus\PhpApiBuilder\Auth\Auth;
use Coagus\PhpApiBuilder\Http\Middleware\AuthMiddleware;
use Coagus\PhpApiBuilder\Http\Middleware\CorsMiddleware;
use Coagus\PhpApiBuilder\Http\Middleware\SecurityHeadersMiddleware;
use Coagus\PhpApiBuilder\ORM\Connection;

// Configure database (SQLite for demo)
Connection::configure([
    'driver' => 'sqlite',
    'database' => __DIR__ . '/demo.sqlite',
]);

// Configure auth
Auth::configure([
    'algorithm' => 'HS256',
    'secret' => 'demo-secret-key-change-in-production-32chars!',
    'access_ttl' => 900,
    'refresh_ttl' => 604800,
    'issuer' => 'demo-api',
    'audience' => 'demo-api',
]);

// Create and run API
$api = new API('DemoApi');
$api->middleware([
    CorsMiddleware::class,
    SecurityHeadersMiddleware::class,
    new AuthMiddleware(['/api/v1/health', '/api/v1/users/login', '/api/v1/users/refresh']),
    \DemoApi\LogMiddleware::class,
]);

$response = $api->run();
$response->send();
