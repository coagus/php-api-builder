<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\CLI\Commands;

use Coagus\PhpApiBuilder\Helpers\Utils;

class MakeEntityCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $name = $args[0] ?? null;
        if ($name === null) {
            echo "Usage: php api make:entity <Name> [--fields=name:string,email:string] [--soft-delete]\n";
            return 1;
        }

        $fields = $this->getFields($args);
        $softDelete = in_array('--soft-delete', $args, true);
        $table = Utils::camelToSnake($name) . 's';

        $content = $this->generate($name, $table, $fields, $softDelete);

        $dir = getcwd() . '/entities';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = "{$dir}/{$name}.php";
        if (file_exists($path)) {
            echo "Entity {$name} already exists at {$path}\n";
            return 1;
        }

        file_put_contents($path, $content);
        echo "Entity created: entities/{$name}.php\n";

        return 0;
    }

    public function generate(string $name, string $table, array $fields, bool $softDelete): string
    {
        $uses = [
            'use Coagus\\PhpApiBuilder\\Attributes\\PrimaryKey;',
            'use Coagus\\PhpApiBuilder\\Attributes\\Table;',
            'use Coagus\\PhpApiBuilder\\ORM\\Entity;',
        ];

        $attributes = "#[Table('{$table}')]";
        if ($softDelete) {
            $uses[] = 'use Coagus\\PhpApiBuilder\\Attributes\\SoftDelete;';
            $attributes .= "\n#[SoftDelete]";
        }

        $hasRequired = false;
        foreach ($fields as $f) {
            if (!$hasRequired) {
                $uses[] = 'use Coagus\\PhpApiBuilder\\Validation\\Attributes\\Required;';
                $hasRequired = true;
            }
        }

        sort($uses);
        $usesStr = implode("\n", $uses);

        $properties = "    #[PrimaryKey]\n    public int \$id;\n";
        foreach ($fields as $field) {
            $properties .= "\n    #[Required]\n    public {$field['type']} \${$field['name']};\n";
        }

        if ($softDelete) {
            $properties .= "\n    public ?string \$deletedAt = null;\n";
        }

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\\Entities;

        {$usesStr}

        {$attributes}
        class {$name} extends Entity
        {
        {$properties}}
        PHP;
    }

    private function getFields(array $args): array
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--fields=')) {
                $fieldsStr = substr($arg, 9);
                $fields = [];
                foreach (explode(',', $fieldsStr) as $fieldDef) {
                    $parts = explode(':', $fieldDef);
                    $fields[] = [
                        'name' => trim($parts[0]),
                        'type' => trim($parts[1] ?? 'string'),
                    ];
                }
                return $fields;
            }
        }

        return [];
    }
}
