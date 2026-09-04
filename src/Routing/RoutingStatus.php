<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Routing;

/**
 * The result status of a route matching attempt.
 */
enum RoutingStatus
{
    /** A matching route was found. */
    case FOUND;

    /** No route matches the requested path. */
    case NOT_FOUND;

    /** The path exists but the HTTP method is not allowed. */
    case METHOD_NOT_ALLOWED;
}
