<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\CLI\Commands;

use Coagus\PhpApiBuilder\OpenAPI\SpecBuilder;
use Coagus\PhpApiBuilder\ORM\Entity;
use Coagus\PhpApiBuilder\Resource\Resource;

class DocsGenerateCommand implements CommandInterface
{
    private const DEFAULT_NAMESPACE = 'App';
    private const DEFAULT_TITLE = 'API';
    private const DEFAULT_VERSION = '1.0.0';

    public function execute(array $args): int
    {
        $cwd = getcwd();
        $output = $this->getOption($args, '--output', "{$cwd}/openapi.json");
        $namespace = $this->getOption($args, '--namespace', self::DEFAULT_NAMESPACE);

        $this->loadProjectAutoloader($cwd);

        echo "Generating OpenAPI specification...\n";

        $builder = new SpecBuilder(
            title: self::DEFAULT_TITLE,
            version: self::DEFAULT_VERSION,
        );

        $entityCount = $this->registerEntities($builder, $cwd, $namespace);
        $serviceCount = $this->registerServices($builder, $cwd, $namespace);

        echo "  Registered entities: {$entityCount}\n";
        echo "  Registered services: {$serviceCount}\n";

        $json = $builder->toJson();
        file_put_contents($output, $json);

        echo "  Generated: {$output}\n";

        return 0;
    }

    private function registerEntities(SpecBuilder $builder, string $cwd, string $namespace): int
    {
        $entitiesNamespace = rtrim($namespace, '\\') . '\\Entities\\';
        $count = 0;

        foreach ($this->candidateEntityDirs($cwd) as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            foreach (glob("{$dir}/*.php") as $file) {
                $className = $entitiesNamespace . pathinfo($file, PATHINFO_FILENAME);
                if (class_exists($className) && is_subclass_of($className, Entity::class)) {
                    $builder->addEntity($className);
                    $count++;
                }
            }
        }

        return $count;
    }

    private function registerServices(SpecBuilder $builder, string $cwd, string $namespace): int
    {
        $servicesNamespace = rtrim($namespace, '\\') . '\\';
        $count = 0;

        foreach ($this->candidateServiceDirs($cwd) as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            foreach (glob("{$dir}/*.php") as $file) {
                $className = $servicesNamespace . pathinfo($file, PATHINFO_FILENAME);
                if (class_exists($className) && is_subclass_of($className, Resource::class)) {
                    $builder->addService($className);
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * @return list<string>
     */
    private function candidateEntityDirs(string $cwd): array
    {
        return [
            "{$cwd}/entities",
            "{$cwd}/src/Entities",
        ];
    }

    /**
     * @return list<string>
     */
    private function candidateServiceDirs(string $cwd): array
    {
        return [
            "{$cwd}/services",
            "{$cwd}/src/Services",
        ];
    }

    private function loadProjectAutoloader(string $cwd): void
    {
        $autoload = "{$cwd}/vendor/autoload.php";
        if (file_exists($autoload)) {
            require_once $autoload;
        }
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
