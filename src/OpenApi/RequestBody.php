<?php

declare(strict_types=1);

namespace FlintPHP\Framework\OpenApi;

/**
 * Represents an OpenAPI Request Body Object.
 */
final readonly class RequestBody implements OpenApiComponent
{
    /**
     * @param array<string, Schema|Reference> $content A map of content types (e.g., 'application/json') to schemas.
     * @param string|null $description Request body description.
     * @param bool|null $required Whether it is required.
     */
    public function __construct(
        public array $content,
        public ?string $description = null,
        public ?bool $required = null,
    ) {
    }

    public function toArray(): array
    {
        $content = [];
        foreach ($this->content as $type => $schema) {
            $content[$type] = ['schema' => $schema->toArray()];
        }

        $data = [
            'content' => empty($content) ? new \stdClass() : $content,
        ];
        
        if ($this->description !== null) {
            $data['description'] = $this->description;
        }
        if ($this->required !== null) {
            $data['required'] = $this->required;
        }

        return $data;
    }
}
