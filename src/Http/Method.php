<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Http;

/**
 * Standard HTTP methods.
 *
 * Provides type safety and IDE autocompletion for the most common
 * HTTP methods. The Request class still accepts arbitrary method
 * strings for extensibility (e.g., PURGE, PROPFIND).
 */
enum Method: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';
    case HEAD = 'HEAD';
    case OPTIONS = 'OPTIONS';
}
