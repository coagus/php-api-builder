<?php

declare(strict_types=1);

/**
 * Architecture test: no SQLite-only SQL syntax inside src/.
 *
 * The library advertises MySQL, PostgreSQL, and SQLite support. SQLite-specific
 * literals like `datetime('now')` or `AUTOINCREMENT` break the cross-driver
 * contract; they must live behind the DriverInterface, not in core classes.
 */

function srcFiles(): array
{
    $root = dirname(__DIR__, 1) . '/../src';
    $root = realpath($root) ?: dirname(__DIR__, 2) . '/src';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    $files = [];
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isFile() && $fileInfo->getExtension() === 'php') {
            $files[] = $fileInfo->getPathname();
        }
    }
    return $files;
}

test('no source file contains the SQLite-only expression datetime(\'now\')', function () {
    $offenders = [];
    foreach (srcFiles() as $file) {
        // Drivers are allowed to use driver-specific SQL; check everything else.
        if (str_contains($file, '/ORM/Drivers/')) {
            continue;
        }
        $contents = file_get_contents($file);
        if ($contents === false) {
            continue;
        }
        if (str_contains($contents, "datetime('now')")) {
            $offenders[] = $file;
        }
    }

    expect($offenders)->toBe([], 'Found SQLite-only datetime(\'now\') outside drivers in: ' . implode(', ', $offenders));
});

test('no source file hard-codes AUTOINCREMENT outside drivers', function () {
    $offenders = [];
    foreach (srcFiles() as $file) {
        if (str_contains($file, '/ORM/Drivers/')) {
            continue;
        }
        $contents = file_get_contents($file);
        if ($contents === false) {
            continue;
        }
        if (str_contains($contents, 'AUTOINCREMENT')) {
            $offenders[] = $file;
        }
    }

    expect($offenders)->toBe([], 'Found SQLite-only AUTOINCREMENT outside drivers in: ' . implode(', ', $offenders));
});
