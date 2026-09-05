<?php

declare(strict_types=1);

namespace FlintPHP\Framework\OpenApi;

/**
 * Represents an OpenAPI Info Object.
 */
final readonly class Info implements OpenApiComponent
{
    /**
     * @param string $title API Title
     * @param string $version API Version
     * @param string|null $description API Description
     */
    public function __construct(
        public string $title,
        public string $version,
        public ?string $description = null,
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'title' => $this->title,
            'version' => $this->version,
        ];
        
        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        return $data;
    }
}
