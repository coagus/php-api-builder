<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\CLI\Commands\EnvCheckCommand;

test('env:check advertises PHP >= 8.4 (matching composer.json requirement)', function () {
    ob_start();
    $exit = (new EnvCheckCommand())->execute([]);
    $output = ob_get_clean();

    expect($output)->toContain('PHP >= 8.4');
    // When running under 8.4+ the check passes; under 8.3 it fails. Either way
    // the label must match the composer.json requirement.
    expect($exit)->toBeIn([0, 1]);
});
