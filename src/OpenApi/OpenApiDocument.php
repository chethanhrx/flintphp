<?php

declare(strict_types=1);

namespace FlintPHP\Framework\OpenApi;

use FlintPHP\Framework\OpenApi\Exception\InvalidDocumentException;

/**
 * Represents the root of an OpenAPI 3.1 Document.
 */
final readonly class OpenApiDocument implements OpenApiComponent
{
    /**
     * @param Info $info
     * @param array<string, PathItem> $paths
     * @param Components|null $components
     * @param string $openapi The OpenAPI version (default 3.1.0).
     */
    public function __construct(
        public Info $info,
        public array $paths = [],
        public ?Components $components = null,
        public string $openapi = '3.1.0',
    ) {
        foreach (array_keys($this->paths) as $path) {
            if ($path === '' || $path[0] !== '/') {
                throw new InvalidDocumentException(sprintf('Invalid path "%s". Paths must begin with a forward slash (/).', $path));
            }
        }
    }

    public function toArray(): array
    {
        $paths = [];
        foreach ($this->paths as $path => $pathItem) {
            $paths[$path] = $pathItem->toArray();
        }

        $data = [
            'openapi' => $this->openapi,
            'info' => $this->info->toArray(),
            'paths' => empty($paths) ? new \stdClass() : $paths,
        ];
        
        if ($this->components !== null) {
            $data['components'] = $this->components->toArray();
        }

        return $data;
    }
}
