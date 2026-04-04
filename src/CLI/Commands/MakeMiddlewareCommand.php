<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\CLI\Commands;

class MakeMiddlewareCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $name = $args[0] ?? null;
        if ($name === null) {
            echo "Usage: php api make:middleware <Name>\n";
            return 1;
        }

        $content = <<<PHP
        <?php

        declare(strict_types=1);

        namespace App;

        use Coagus\\PhpApiBuilder\\Http\\Middleware\\MiddlewareInterface;
        use Coagus\\PhpApiBuilder\\Http\\Request;
        use Coagus\\PhpApiBuilder\\Http\\Response;

        class {$name} implements MiddlewareInterface
        {
            public function handle(Request \$request, callable \$next): Response
            {
                // Before request processing
                \$response = \$next(\$request);
                // After request processing
                return \$response;
            }
        }
        PHP;

        $dir = getcwd() . '/services';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = "{$dir}/{$name}.php";
        if (file_exists($path)) {
            echo "Middleware {$name} already exists at {$path}\n";
            return 1;
        }

        file_put_contents($path, $content);
        echo "Middleware created: services/{$name}.php\n";

        return 0;
    }
}
