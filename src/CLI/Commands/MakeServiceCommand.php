<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\CLI\Commands;

class MakeServiceCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $name = $args[0] ?? null;
        if ($name === null) {
            echo "Usage: php api make:service <Name>\n";
            return 1;
        }

        $content = <<<PHP
        <?php

        declare(strict_types=1);

        namespace App;

        use Coagus\\PhpApiBuilder\\Resource\\Service;

        class {$name} extends Service
        {
            public function get(): void
            {
                \$this->success(['message' => '{$name} service']);
            }
        }
        PHP;

        $dir = getcwd() . '/services';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = "{$dir}/{$name}.php";
        if (file_exists($path)) {
            echo "Service {$name} already exists at {$path}\n";
            return 1;
        }

        file_put_contents($path, $content);
        echo "Service created: services/{$name}.php\n";

        return 0;
    }
}
