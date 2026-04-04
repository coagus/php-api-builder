<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Helpers;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;

class LogFactory
{
    private static ?Logger $logger = null;

    public static function create(string $channel = 'api', ?string $logPath = null, Level $level = Level::Error): Logger
    {
        if (self::$logger !== null) {
            return self::$logger;
        }

        $logger = new Logger($channel);
        $logger->pushProcessor(new PsrLogMessageProcessor());

        $path = $logPath ?? ($_ENV['LOG_PATH'] ?? 'log');
        $handler = new RotatingFileHandler("{$path}/api.log", 30, $level);
        $handler->setFormatter(new JsonFormatter());
        $logger->pushHandler($handler);

        self::$logger = $logger;

        return $logger;
    }

    public static function reset(): void
    {
        self::$logger = null;
    }

    public static function getLogger(): Logger
    {
        return self::$logger ?? self::create();
    }
}
