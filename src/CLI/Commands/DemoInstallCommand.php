<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\CLI\Commands;

class DemoInstallCommand implements CommandInterface
{
    private const DEMO_FILES = [
        'entities/User.php',
        'entities/Post.php',
        'entities/Comment.php',
        'entities/Tag.php',
        'services/AuthService.php',
        'services/PostService.php',
        'services/StatsService.php',
        'services/Middleware/RequestLogger.php',
    ];

    public function execute(array $args): int
    {
        $cwd = getcwd();
        echo "\n  Blog API Demo — Install\n";
        echo "  " . str_repeat('=', 36) . "\n\n";

        if (!file_exists("{$cwd}/composer.json")) {
            echo "  Error: Run this from a project created with 'api init'.\n\n";
            return 1;
        }

        $sourceDir = $this->findDemoSource();
        if ($sourceDir === null) {
            echo "  Error: Demo templates not found.\n\n";
            return 1;
        }

        // Check for existing demo files
        if (file_exists("{$cwd}/entities/User.php")) {
            echo "  Demo already installed. Run 'api demo:remove' first.\n\n";
            return 1;
        }

        // Copy template files
        foreach (self::DEMO_FILES as $file) {
            $src = "{$sourceDir}/{$file}";
            $dst = "{$cwd}/{$file}";
            $dir = dirname($dst);

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            copy($src, $dst);
            echo "  Created: {$file}\n";
        }

        // Copy demo index.php (backup original)
        if (file_exists("{$cwd}/index.php")) {
            copy("{$cwd}/index.php", "{$cwd}/index.original.php");
            echo "  Backup:  index.php → index.original.php\n";
        }
        copy("{$sourceDir}/index.php", "{$cwd}/index.php");
        echo "  Updated: index.php (with Auth + RateLimit + demo routes)\n";

        // Copy and execute schema
        copy("{$sourceDir}/schema.sql", "{$cwd}/demo-schema.sql");
        echo "  Created: demo-schema.sql\n";

        $this->executeSchema($cwd);

        echo "\n  Demo installed successfully!\n\n";
        echo "  Endpoints available:\n";
        echo "    PUBLIC:\n";
        echo "      GET  /api/v1/health          Health check\n";
        echo "      GET  /api/v1/stats            Blog statistics\n";
        echo "      POST /api/v1/auth/register    Register user\n";
        echo "      POST /api/v1/auth/login       Login (get JWT)\n";
        echo "      POST /api/v1/auth/refresh     Refresh token\n";
        echo "      GET  /api/v1/docs/swagger     Swagger UI\n";
        echo "\n";
        echo "    PROTECTED (Bearer token required):\n";
        echo "      GET|POST        /api/v1/posts       List / Create\n";
        echo "      GET|PUT|DELETE  /api/v1/posts/:id   Read / Update / Delete\n";
        echo "      PATCH           /api/v1/posts/:id/publish   Publish post\n";
        echo "      PATCH           /api/v1/posts/:id/archive   Archive post\n";
        echo "      GET|POST        /api/v1/comments    List / Create\n";
        echo "      GET|POST        /api/v1/tags        List / Create\n";
        echo "\n";
        echo "  Demo credentials:\n";
        echo "    Email:    admin@demo.com\n";
        echo "    Password: password\n\n";
        echo "  Try it:\n";
        echo "    Open http://localhost:8080/api/v1/docs/swagger\n\n";
        echo "  To remove: ./api demo:remove\n\n";

        return 0;
    }

    private function findDemoSource(): ?string
    {
        $candidates = [
            '/opt/php-api-builder/resources/demo',
            dirname(__DIR__, 3) . '/resources/demo',
        ];

        foreach ($candidates as $candidate) {
            if (is_dir($candidate) && file_exists("{$candidate}/schema.sql")) {
                return $candidate;
            }
        }

        return null;
    }

    private function executeSchema(string $cwd): void
    {
        $schemaFile = "{$cwd}/demo-schema.sql";
        if (!file_exists($schemaFile)) {
            return;
        }

        // Try to load .env and execute SQL
        try {
            if (file_exists("{$cwd}/.env") && class_exists(\Dotenv\Dotenv::class)) {
                $dotenv = \Dotenv\Dotenv::createImmutable($cwd);
                $dotenv->safeLoad();
            }

            $driver = $_ENV['DB_DRIVER'] ?? 'mysql';
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $port = $_ENV['DB_PORT'] ?? '3306';
            $dbName = $_ENV['DB_NAME'] ?? '';
            $username = $_ENV['DB_USERNAME'] ?? 'root';
            $password = $_ENV['DB_PASSWORD'] ?? '';

            if ($driver === 'sqlite') {
                echo "\n  Note: SQLite detected. Run the schema manually:\n";
                echo "    sqlite3 <database> < demo-schema.sql\n";
                return;
            }

            $dsn = "{$driver}:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
            $pdo = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);

            $sql = file_get_contents($schemaFile);
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                fn(string $s) => $s !== '' && !str_starts_with($s, '--')
            );

            foreach ($statements as $statement) {
                $pdo->exec($statement);
            }

            echo "  Database: tables created and seed data inserted\n";
        } catch (\Exception $e) {
            echo "\n  Note: Could not connect to database ({$e->getMessage()}).\n";
            echo "  Run the schema manually when the database is available:\n";
            echo "    mysql -u root -p my_api < demo-schema.sql\n";
        }
    }
}
