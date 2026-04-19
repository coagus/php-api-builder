<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\CLI\Commands;

class ServeCommand implements CommandInterface
{
    private const HOST_PATTERN = '/^[A-Za-z0-9.\-]+$/';
    private const MIN_PORT = 1;
    private const MAX_PORT = 65535;
    private const DEFAULT_HOST = 'localhost';
    private const DEFAULT_PORT = '8000';

    public function execute(array $args): int
    {
        $host = $this->getOption($args, '--host', self::DEFAULT_HOST);
        $port = $this->getOption($args, '--port', self::DEFAULT_PORT);

        if (!$this->isValidHost($host)) {
            fwrite(STDERR, "Invalid --host value. Allowed: letters, digits, '.', '-'.\n");
            return 1;
        }

        $portInt = $this->parsePort($port);
        if ($portInt === null) {
            fwrite(STDERR, "Invalid --port value. Must be an integer between "
                . self::MIN_PORT . " and " . self::MAX_PORT . ".\n");
            return 1;
        }

        echo "Starting PHP development server at http://{$host}:{$portInt}\n";
        echo "Press Ctrl+C to stop.\n\n";

        $cwd = getcwd();
        $router = file_exists("{$cwd}/router.php") ? "{$cwd}/router.php" : null;

        $command = [PHP_BINARY, '-S', "{$host}:{$portInt}", '-t', $cwd];
        if ($router !== null) {
            $command[] = $router;
        }

        return $this->runServer($command);
    }

    private function runServer(array $command): int
    {
        $descriptors = [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR,
        ];

        $process = proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            fwrite(STDERR, "Failed to launch PHP development server.\n");
            return 1;
        }

        return proc_close($process);
    }

    private function isValidHost(string $host): bool
    {
        return (bool) preg_match(self::HOST_PATTERN, $host);
    }

    private function parsePort(string $port): ?int
    {
        if (!ctype_digit($port)) {
            return null;
        }

        $value = (int) $port;
        if ($value < self::MIN_PORT || $value > self::MAX_PORT) {
            return null;
        }

        return $value;
    }

    private function getOption(array $args, string $name, string $default): string
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, "{$name}=")) {
                return substr($arg, strlen($name) + 1);
            }
        }

        return $default;
    }
}
