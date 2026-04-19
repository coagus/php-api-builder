<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\CLI\Commands\ServeCommand;

test('rejects shell-injection attempts via --host argument', function () {
    $cmd = new ServeCommand();

    ob_start();
    $exit = $cmd->execute(['--host=; rm -rf .', '--port=8000']);
    ob_end_clean();

    expect($exit)->toBe(1);
});

test('rejects out-of-range port values', function () {
    $cmd = new ServeCommand();

    ob_start();
    $exit = $cmd->execute(['--host=localhost', '--port=99999']);
    ob_end_clean();

    expect($exit)->toBe(1);
});

test('rejects non-numeric port values', function () {
    $cmd = new ServeCommand();

    ob_start();
    $exit = $cmd->execute(['--host=localhost', '--port=abc']);
    ob_end_clean();

    expect($exit)->toBe(1);
});
