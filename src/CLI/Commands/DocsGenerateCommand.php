<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\CLI\Commands;

use Coagus\PhpApiBuilder\OpenAPI\SpecBuilder;

class DocsGenerateCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $output = $this->getOption($args, '--output', getcwd() . '/openapi.json');

        echo "Generating OpenAPI specification...\n";

        $builder = new SpecBuilder(
            title: 'API',
            version: '1.0.0'
        );

        // Auto-discover entities in the entities/ directory
        $entitiesDir = getcwd() . '/entities';
        if (is_dir($entitiesDir)) {
            $files = glob("{$entitiesDir}/*.php");
            echo "  Found " . count($files) . " entity file(s)\n";
        }

        $json = $builder->toJson();
        file_put_contents($output, $json);

        echo "  Generated: {$output}\n";

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
