<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\CLI\Commands\DocsGenerateCommand;

test('docs:generate writes a JSON file (entities/services registered via autoloader)', function () {
    $tmp = sys_get_temp_dir() . '/php-api-builder-docs-' . uniqid();
    mkdir($tmp . '/entities', 0755, true);
    mkdir($tmp . '/services', 0755, true);

    $outputFile = "{$tmp}/openapi.json";

    // Run command from the tmp directory so the discovery paths resolve there.
    $prevDir = getcwd();
    chdir($tmp);

    ob_start();
    $exit = (new DocsGenerateCommand())->execute(["--output={$outputFile}"]);
    ob_end_clean();

    chdir($prevDir);

    expect($exit)->toBe(0)
        ->and(file_exists($outputFile))->toBeTrue();

    $json = json_decode(file_get_contents($outputFile), true);
    expect($json)->toBeArray()
        ->and($json)->toHaveKey('openapi')
        ->and($json)->toHaveKey('paths');

    // Cleanup
    @unlink($outputFile);
    @rmdir("{$tmp}/entities");
    @rmdir("{$tmp}/services");
    @rmdir($tmp);
});
