<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Routing;

use InvalidArgumentException;

/**
 * Exception for invalid route definitions.
 *
 * Thrown at route registration time when the route pattern is
 * malformed (e.g., empty parameter names, duplicate parameters,
 * unmatched braces). Not thrown during request matching.
 */
final class RoutingException extends InvalidArgumentException
{
}
