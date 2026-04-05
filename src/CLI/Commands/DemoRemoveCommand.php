<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\CLI\Commands;

class DemoRemoveCommand implements CommandInterface
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
        'demo-schema.sql',
    ];

    private const DEMO_TABLES = ['post_tags', 'comments', 'posts', 'tags', 'users'];

    public function execute(array $args): int
    {
        $cwd = getcwd();
        echo "\n  Blog API Demo — Remove\n";
        echo "  " . str_repeat('=', 36) . "\n\n";

        $removed = false;

        // Remove demo files
        foreach (self::DEMO_FILES as $file) {
            $path = "{$cwd}/{$file}";
            if (file_exists($path)) {
                unlink($path);
                echo "  Removed: {$file}\n";
                $removed = true;
            }
        }

        // Remove empty Middleware directory
        $middlewareDir = "{$cwd}/services/Middleware";
        if (is_dir($middlewareDir) && count(scandir($middlewareDir)) === 2) {
            rmdir($middlewareDir);
            echo "  Removed: services/Middleware/\n";
        }

        // Restore original index.php
        if (file_exists("{$cwd}/index.original.php")) {
            rename("{$cwd}/index.original.php", "{$cwd}/index.php");
            echo "  Restored: index.php (from backup)\n";
            $removed = true;
        }

        // Drop demo tables
        $this->dropTables($cwd);

        if (!$removed) {
            echo "  No demo files found. Nothing to remove.\n";
        }

        echo "\n  Demo removed.\n\n";

        return 0;
    }

    private function dropTables(string $cwd): void
    {
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
                return;
            }

            $dsn = "{$driver}:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
            $pdo = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);

            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach (self::DEMO_TABLES as $table) {
                $pdo->exec("DROP TABLE IF EXISTS {$table}");
                echo "  Dropped: {$table}\n";
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

            echo "  Database: demo tables removed\n";
        } catch (\Exception $e) {
            echo "\n  Note: Could not drop tables ({$e->getMessage()}).\n";
            echo "  Drop manually: post_tags, comments, posts, tags, users\n";
        }
    }
}
