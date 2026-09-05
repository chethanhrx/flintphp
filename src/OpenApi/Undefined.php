<?php

declare(strict_types=1);

namespace FlintPHP\Framework\OpenApi;

/**
 * Internal sentinel used to distinguish omitted values from explicit nulls.
 * Used primarily for mixed-type fields like 'example' where null is a valid value.
 */
final class Undefined
{
}
