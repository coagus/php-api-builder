<?php

require __DIR__ . '/vendor/autoload.php';

use Coagus\PhpApiBuilder\API;
use Coagus\PhpApiBuilder\Auth\Auth;
use Coagus\PhpApiBuilder\Http\Middleware\AuthMiddleware;
use Coagus\PhpApiBuilder\Http\Middleware\CorsMiddleware;
use Coagus\PhpApiBuilder\Http\Middleware\RateLimitMiddleware;
use Coagus\PhpApiBuilder\Http\Middleware\SecurityHeadersMiddleware;
use Coagus\PhpApiBuilder\ORM\Connection;
use Dotenv\Dotenv;

// Load environment
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Configure database
Connection::configure([
    'driver' => $_ENV['DB_DRIVER'] ?? 'mysql',
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
    'database' => $_ENV['DB_NAME'] ?? '',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
]);

// Configure auth
Auth::configure([
    'algorithm' => $_ENV['JWT_ALGORITHM'] ?? 'HS256',
    'secret' => $_ENV['JWT_SECRET'] ?? 'change-this-to-a-random-string-at-least-32-chars',
    'access_ttl' => (int) ($_ENV['JWT_ACCESS_TOKEN_TTL'] ?? 900),
    'refresh_ttl' => (int) ($_ENV['JWT_REFRESH_TOKEN_TTL'] ?? 604800),
    'issuer' => $_ENV['JWT_ISSUER'] ?? 'my-api',
    'audience' => $_ENV['JWT_AUDIENCE'] ?? 'my-api',
]);

// Create and run API
$api = new API('App');
$api->middleware([
    new RateLimitMiddleware(limit: 30, windowSeconds: 60),
    CorsMiddleware::class,
    SecurityHeadersMiddleware::class,
    new AuthMiddleware([
        '/api/v1/health',
        '/api/v1/auth/register',
        '/api/v1/auth/login',
        '/api/v1/auth/refresh',
        '/api/v1/stats',
        '/api/v1/docs',
    ]),
]);

$response = $api->run();
$response->send();
