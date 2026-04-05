<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\CLI\Commands;

class ServeCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $host = $this->getOption($args, '--host', 'localhost');
        $port = $this->getOption($args, '--port', '8000');

        echo "Starting PHP development server at http://{$host}:{$port}\n";
        echo "Press Ctrl+C to stop.\n\n";

        $cwd = getcwd();
        $router = file_exists("{$cwd}/router.php") ? "{$cwd}/router.php" : '';
        passthru("php -S {$host}:{$port} -t {$cwd} {$router}");

        return 0;
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
