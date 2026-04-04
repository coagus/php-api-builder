<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\CLI\Commands;

interface CommandInterface
{
    public function execute(array $args): int;
}
