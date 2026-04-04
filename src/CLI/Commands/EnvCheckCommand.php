<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\CLI\Commands;

class EnvCheckCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        echo "Environment Check\n";
        echo str_repeat('=', 40) . "\n\n";

        $allOk = true;

        // PHP version
        $phpVersion = PHP_VERSION;
        $phpOk = version_compare($phpVersion, '8.3.0', '>=');
        $this->printCheck("PHP >= 8.3", $phpOk, $phpVersion);
        $allOk = $allOk && $phpOk;

        // Extensions
        $extensions = ['pdo', 'json', 'mbstring', 'openssl'];
        foreach ($extensions as $ext) {
            $loaded = extension_loaded($ext);
            $this->printCheck("ext-{$ext}", $loaded);
            $allOk = $allOk && $loaded;
        }

        // DB drivers
        $drivers = ['pdo_mysql', 'pdo_pgsql', 'pdo_sqlite'];
        $hasDriver = false;
        foreach ($drivers as $driver) {
            $loaded = extension_loaded($driver);
            if ($loaded) $hasDriver = true;
            $this->printCheck("ext-{$driver}", $loaded, $loaded ? '' : '(optional)');
        }

        // .env file
        $envExists = file_exists(getcwd() . '/.env');
        $this->printCheck('.env file', $envExists);

        // Composer
        $composerExists = file_exists(getcwd() . '/vendor/autoload.php');
        $this->printCheck('Composer dependencies', $composerExists);

        echo "\n";
        if ($allOk) {
            echo "All checks passed!\n";
        } else {
            echo "Some checks failed. Please fix the issues above.\n";
        }

        return $allOk ? 0 : 1;
    }

    private function printCheck(string $name, bool $ok, string $extra = ''): void
    {
        $status = $ok ? '[OK]' : '[FAIL]';
        $extra = $extra ? " ({$extra})" : '';
        echo "  {$status} {$name}{$extra}\n";
    }
}
