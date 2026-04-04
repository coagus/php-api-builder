<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\CLI\Commands;

class InitCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $cwd = getcwd();
        echo "Initializing PHP API Builder project...\n\n";

        $this->createDirectories($cwd);
        $this->createEnvFile($cwd);
        $this->createIndexFile($cwd);
        $this->createHtaccess($cwd);

        echo "\n  Project initialized successfully!\n";
        echo "  Next steps:\n";
        echo "    1. Configure your .env file\n";
        echo "    2. php api make:entity YourEntity\n";
        echo "    3. php api serve\n\n";

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
        DB_HOST=localhost
        DB_PORT=3306
        DB_NAME=my_api
        DB_USERNAME=root
        DB_PASSWORD=

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
        use Dotenv\Dotenv;

        // Load environment
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->safeLoad();

        // Configure database
        Coagus\PhpApiBuilder\ORM\Connection::configure([
            'driver' => $_ENV['DB_DRIVER'] ?? 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
            'database' => $_ENV['DB_NAME'] ?? '',
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
        ]);

        // Configure auth
        Coagus\PhpApiBuilder\Auth\Auth::configure([
            'algorithm' => $_ENV['JWT_ALGORITHM'] ?? 'HS256',
            'secret' => $_ENV['JWT_SECRET'] ?? null,
            'access_ttl' => (int) ($_ENV['JWT_ACCESS_TOKEN_TTL'] ?? 900),
            'refresh_ttl' => (int) ($_ENV['JWT_REFRESH_TOKEN_TTL'] ?? 604800),
            'issuer' => $_ENV['JWT_ISSUER'] ?? 'my-api',
            'audience' => $_ENV['JWT_AUDIENCE'] ?? 'my-api',
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
}
