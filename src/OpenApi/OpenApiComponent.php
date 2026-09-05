<?php

declare(strict_types=1);

namespace FlintPHP\Framework\OpenApi;

/**
 * Interface for all OpenAPI components that can be serialized.
 */
interface OpenApiComponent
{
    /**
     * Convert the component to an associative array for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
