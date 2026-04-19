<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\ORM;

use Coagus\PhpApiBuilder\ORM\Drivers\DriverInterface;
use Coagus\PhpApiBuilder\ORM\Drivers\MySqlDriver;
use Coagus\PhpApiBuilder\ORM\Drivers\PostgresDriver;
use Coagus\PhpApiBuilder\ORM\Drivers\SqliteDriver;
use PDO;
use PDOStatement;
use RuntimeException;

class Connection
{
    private static ?self $instance = null;
    private static ?array $config = null;

    private PDO $pdo;
    private DriverInterface $driver;

    private function __construct(array $config)
    {
        $this->driver = self::createDriver($config['driver'] ?? 'mysql');

        $this->pdo = new PDO(
            $this->driver->getDsn($config),
            $config['username'] ?? null,
            $config['password'] ?? null,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $this->driver->applySessionSettings($this->pdo);
    }

    public static function configure(array $config): void
    {
        self::$config = $config;
        self::$instance = null;
    }

    public static function getInstance(): self
    {
        if (self::$config === null) {
            throw new RuntimeException('Connection not configured. Call Connection::configure() first.');
        }

        if (self::$instance === null) {
            self::$instance = new self(self::$config);
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
        self::$config = null;
    }

    public function getDriver(): DriverInterface
    {
        return $this->driver;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->prepareAndExecute($sql, $params);

        return $stmt->fetchAll();
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->prepareAndExecute($sql, $params);

        return $stmt->rowCount();
    }

    public function exec(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    public function lastInsertId(): string|false
    {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }

    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    private function prepareAndExecute(string $sql, array $params): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    private static function createDriver(string $driver): DriverInterface
    {
        return match ($driver) {
            'mysql' => new MySqlDriver(),
            'pgsql' => new PostgresDriver(),
            'sqlite' => new SqliteDriver(),
            default => throw new RuntimeException("Unsupported database driver: {$driver}"),
        };
    }
}
