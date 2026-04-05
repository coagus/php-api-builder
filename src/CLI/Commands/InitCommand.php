<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\CLI\Commands;

class InitCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $cwd = getcwd();
        echo "\n  PHP API Builder v2 — Setup\n";
        echo "  " . str_repeat('=', 36) . "\n\n";

        $this->createDirectories($cwd);
        $this->createEnvFile($cwd);
        $this->createIndexFile($cwd);
        $this->createHtaccess($cwd);
        $this->createComposerJson($cwd);
        $this->createRouterFile($cwd);
        $this->createDockerCompose($cwd);
        $this->createApiWrapper($cwd);
        $this->createHealthService($cwd);
        $this->createClaudeSkill($cwd);

        echo "\n  Project initialized successfully!\n\n";
        echo "  Next steps:\n";
        echo "    1. Edit .env with your database credentials\n";
        echo "    2. docker compose up -d\n";
        echo "    3. ./api make:entity YourEntity\n";
        echo "    4. Open http://localhost:8080/api/v1/health\n\n";

        return 0;
    }

    private function createDirectories(string $cwd): void
    {
        $dirs = ['services', 'entities', 'log', 'keys'];
        foreach ($dirs as $dir) {
            $path = "{$cwd}/{$dir}";
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
                echo "  Created: {$dir}/\n";
            }
        }
    }

    private function createEnvFile(string $cwd): void
    {
        $path = "{$cwd}/.env";
        if (file_exists($path)) {
            echo "  Skipped: .env (already exists)\n";
            return;
        }

        $content = <<<'ENV'
# Database
DB_DRIVER=mysql
DB_HOST=db
DB_PORT=3306
DB_NAME=my_api
DB_USERNAME=root
DB_PASSWORD=secret

# JWT
JWT_ALGORITHM=HS256
JWT_SECRET=change-this-to-a-random-string-at-least-32-chars
JWT_ACCESS_TOKEN_TTL=900
JWT_REFRESH_TOKEN_TTL=604800
JWT_ISSUER=my-api
JWT_AUDIENCE=my-api

# CORS
CORS_ALLOWED_ORIGINS=*
CORS_ALLOWED_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS
CORS_ALLOWED_HEADERS=Content-Type,Authorization,X-Request-ID

# App
APP_PORT=8080
DB_EXTERNAL_PORT=3306
APP_DEBUG=true
API_DOCS=true
ENV;

        file_put_contents($path, $content);
        echo "  Created: .env\n";
    }

    private function createIndexFile(string $cwd): void
    {
        $path = "{$cwd}/index.php";
        if (file_exists($path)) {
            echo "  Skipped: index.php (already exists)\n";
            return;
        }

        $content = <<<'PHP'
<?php

require __DIR__ . '/vendor/autoload.php';

use Coagus\PhpApiBuilder\API;
use Coagus\PhpApiBuilder\Http\Middleware\CorsMiddleware;
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

// Create and run API
$api = new API('App');
$api->middleware([
    CorsMiddleware::class,
    SecurityHeadersMiddleware::class,
]);

$response = $api->run();
$response->send();
PHP;

        file_put_contents($path, $content);
        echo "  Created: index.php\n";
    }

    private function createHtaccess(string $cwd): void
    {
        $path = "{$cwd}/.htaccess";
        if (file_exists($path)) {
            echo "  Skipped: .htaccess (already exists)\n";
            return;
        }

        $content = <<<'HTACCESS'
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
HTACCESS;

        file_put_contents($path, $content);
        echo "  Created: .htaccess\n";
    }

    private function createComposerJson(string $cwd): void
    {
        $path = "{$cwd}/composer.json";
        if (file_exists($path)) {
            echo "  Skipped: composer.json (already exists)\n";
            return;
        }

        $content = <<<'JSON'
{
    "name": "app/my-api",
    "description": "My API built with php-api-builder",
    "type": "project",
    "require": {
        "php": "^8.3",
        "coagus/php-api-builder": "^2.0"
    },
    "require-dev": {
        "pestphp/pest": "^3.0"
    },
    "autoload": {
        "psr-4": {
            "App\\": "services/",
            "App\\Entities\\": "entities/"
        }
    },
    "minimum-stability": "alpha",
    "prefer-stable": true,
    "config": {
        "allow-plugins": {
            "pestphp/pest-plugin": true
        }
    }
}
JSON;

        file_put_contents($path, $content);
        echo "  Created: composer.json\n";
    }

    private function createRouterFile(string $cwd): void
    {
        $path = "{$cwd}/router.php";
        if (file_exists($path)) {
            echo "  Skipped: router.php (already exists)\n";
            return;
        }

        $content = <<<'PHP'
<?php

// Router for PHP built-in server
// Routes all requests to index.php except existing files

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// Serve existing files directly (CSS, JS, images, etc.)
if ($path !== '/' && file_exists(__DIR__ . $path) && !is_dir(__DIR__ . $path)) {
    return false;
}

// Route everything else to index.php
require __DIR__ . '/index.php';
PHP;

        file_put_contents($path, $content);
        echo "  Created: router.php\n";
    }

    private function createDockerCompose(string $cwd): void
    {
        $path = "{$cwd}/docker-compose.yml";
        if (file_exists($path)) {
            echo "  Skipped: docker-compose.yml (already exists)\n";
            return;
        }

        $content = <<<'YAML'
services:
  app:
    image: coagus/php-api-builder:latest
    entrypoint: []
    command: >
      sh -c "
        if [ ! -d vendor ]; then composer install --no-interaction; fi &&
        php -S 0.0.0.0:8080 -t /app /app/router.php
      "
    working_dir: /app
    volumes:
      - .:/app
    ports:
      - "${APP_PORT:-8080}:8080"
    depends_on:
      db:
        condition: service_healthy
    env_file: .env

  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_PASSWORD:-secret}
      MYSQL_DATABASE: ${DB_NAME:-my_api}
    ports:
      - "${DB_EXTERNAL_PORT:-3306}:3306"
    volumes:
      - db_data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 5s
      timeout: 3s
      retries: 10

volumes:
  db_data:
YAML;

        file_put_contents($path, $content);
        echo "  Created: docker-compose.yml\n";
    }

    private function createApiWrapper(string $cwd): void
    {
        $path = "{$cwd}/api";
        if (file_exists($path)) {
            echo "  Skipped: api (already exists)\n";
            return;
        }

        $content = <<<'BASH'
#!/bin/bash
# ./api — Smart wrapper for php-api-builder CLI
# Auto-detects PHP local or uses Docker

set -e

# Check if local PHP >= 8.3 is available
php_available() {
    if ! command -v php &> /dev/null; then
        return 1
    fi
    local version=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
    local major=$(echo $version | cut -d. -f1)
    local minor=$(echo $version | cut -d. -f2)
    if [ "$major" -lt 8 ] || ([ "$major" -eq 8 ] && [ "$minor" -lt 3 ]); then
        return 1
    fi
    return 0
}

# Check if docker compose app is running
docker_running() {
    docker compose ps --status running 2>/dev/null | grep -q "app"
}

if php_available && [ -f vendor/bin/api ]; then
    php vendor/bin/api "$@"
elif docker_running; then
    docker compose exec app php vendor/bin/api "$@"
elif command -v docker &> /dev/null; then
    echo "Running via Docker..."
    docker run --rm -v "$(pwd):/app" coagus/php-api-builder "$@"
else
    echo "Error: Neither PHP (>=8.3) nor Docker found."
    echo "Install one of:"
    echo "  PHP 8.3+  -> https://www.php.net/downloads"
    echo "  Docker    -> https://docs.docker.com/get-docker/"
    exit 1
fi
BASH;

        file_put_contents($path, $content);
        chmod($path, 0755);
        echo "  Created: api (CLI wrapper)\n";
    }

    private function createHealthService(string $cwd): void
    {
        $path = "{$cwd}/services/Health.php";
        if (file_exists($path)) {
            return;
        }

        $content = <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

use Coagus\PhpApiBuilder\Attributes\PublicResource;
use Coagus\PhpApiBuilder\Resource\Service;

#[PublicResource]
class Health extends Service
{
    public function get(): void
    {
        $this->success([
            'status' => 'ok',
            'timestamp' => date('c'),
        ]);
    }
}
PHP;

        file_put_contents($path, $content);
        echo "  Created: services/Health.php\n";
    }

    private function createClaudeSkill(string $cwd): void
    {
        $targetDir = "{$cwd}/.claude/skills/php-api-builder";
        if (is_dir($targetDir)) {
            echo "  Skipped: .claude/skills/ (already exists)\n";
            return;
        }

        // Locate the skill source: Docker image or composer vendor
        $candidates = [
            '/opt/php-api-builder/resources/skill/php-api-builder',
            dirname(__DIR__, 3) . '/resources/skill/php-api-builder',
        ];

        $sourceDir = null;
        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                $sourceDir = $candidate;
                break;
            }
        }

        if ($sourceDir === null) {
            return;
        }

        $this->copyDirectory($sourceDir, $targetDir);
        echo "  Created: .claude/skills/ (Claude Code AI assistant)\n";
    }

    private function copyDirectory(string $source, string $target): void
    {
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        $items = scandir($source);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $srcPath = "{$source}/{$item}";
            $dstPath = "{$target}/{$item}";

            if (is_dir($srcPath)) {
                $this->copyDirectory($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
        }
    }
}
