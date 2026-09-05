<?php

declare(strict_types=1);

namespace FlintPHP\Framework\OpenApi;

/**
 * Represents a $ref pointer to another OpenAPI component.
 */
final readonly class Reference implements OpenApiComponent
{
    /**
     * @param string $ref The reference path (e.g., '#/components/schemas/User').
     */
    public function __construct(
        public string $ref,
    ) {
    }

    public function toArray(): array
    {
        return ['$ref' => $this->ref];
    }
}
