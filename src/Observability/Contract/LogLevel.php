<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Observability\Contract;

/**
 * PSR-style log severity levels.
 *
 * Values are lowercase strings matching the conventional PSR log level names.
 */
enum LogLevel: string
{
    case DEBUG = 'debug';
    case INFO = 'info';
    case NOTICE = 'notice';
    case WARNING = 'warning';
    case ERROR = 'error';
    case CRITICAL = 'critical';
    case ALERT = 'alert';
    case EMERGENCY = 'emergency';
}
