<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\CLI;

use Coagus\PhpApiBuilder\CLI\Commands\DocsGenerateCommand;
use Coagus\PhpApiBuilder\CLI\Commands\EnvCheckCommand;
use Coagus\PhpApiBuilder\CLI\Commands\InitCommand;
use Coagus\PhpApiBuilder\CLI\Commands\KeysGenerateCommand;
use Coagus\PhpApiBuilder\CLI\Commands\MakeEntityCommand;
use Coagus\PhpApiBuilder\CLI\Commands\MakeMiddlewareCommand;
use Coagus\PhpApiBuilder\CLI\Commands\MakeServiceCommand;
use Coagus\PhpApiBuilder\CLI\Commands\ServeCommand;

class Application
{
    private const VERSION = '2.0.0';

    private array $commands = [];

    public function __construct()
    {
        $this->registerCommands();
    }

    public function run(array $argv): int
    {
        $command = $argv[1] ?? null;
        $args = array_slice($argv, 2);

        if ($command === null || $command === '--help' || $command === '-h') {
            $this->showHelp();
            return 0;
        }

        if ($command === '--version' || $command === '-v') {
            echo "PHP API Builder v" . self::VERSION . "\n";
            return 0;
        }

        if (!isset($this->commands[$command])) {
            echo "Unknown command: {$command}\n";
            echo "Run 'php api --help' for available commands.\n";
            return 1;
        }

        $handler = $this->commands[$command];

        return $handler->execute($args);
    }

    private function registerCommands(): void
    {
        $this->commands = [
            'init' => new InitCommand(),
            'serve' => new ServeCommand(),
            'env:check' => new EnvCheckCommand(),
            'make:entity' => new MakeEntityCommand(),
            'make:service' => new MakeServiceCommand(),
            'make:middleware' => new MakeMiddlewareCommand(),
            'keys:generate' => new KeysGenerateCommand(),
            'docs:generate' => new DocsGenerateCommand(),
        ];
    }

    private function showHelp(): void
    {
        $v = self::VERSION;
        echo <<<HELP
        PHP API Builder v{$v}

        Usage: php api <command> [options]

        Available commands:
          init               Initialize a new API project
          serve              Start PHP development server
          env:check          Verify environment configuration
          make:entity        Generate a new Entity class
          make:service       Generate a new Service class
          make:middleware     Generate a new Middleware class
          keys:generate      Generate RSA key pair for JWT
          docs:generate      Generate OpenAPI specification

        Options:
          --help, -h         Show this help
          --version, -v      Show version

        HELP;
    }
}
