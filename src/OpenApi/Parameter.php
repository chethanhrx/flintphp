<?php

declare(strict_types=1);

namespace FlintPHP\Framework\OpenApi;

use FlintPHP\Framework\OpenApi\Exception\InvalidDocumentException;

/**
 * Represents an OpenAPI Parameter Object.
 */
final readonly class Parameter implements OpenApiComponent
{
    /**
     * @param string $name Parameter name.
     * @param string $in Location ('query', 'header', 'path', 'cookie').
     * @param string|null $description Parameter description.
     * @param bool|null $required Whether it is required.
     * @param Schema|Reference|null $schema The schema defining the type.
     */
    public function __construct(
        public string $name,
        public string $in,
        public ?string $description = null,
        public ?bool $required = null,
        public Schema|Reference|null $schema = null,
    ) {
        if (!in_array($this->in, ['query', 'header', 'path', 'cookie'], true)) {
            throw new InvalidDocumentException(sprintf('Invalid parameter location "%s". Must be query, header, path, or cookie.', $this->in));
        }
    }

    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'in' => $this->in,
        ];
        
        if ($this->description !== null) {
            $data['description'] = $this->description;
        }
        if ($this->required !== null) {
            $data['required'] = $this->required;
        }
        if ($this->schema !== null) {
            $data['schema'] = $this->schema->toArray();
        }

        return $data;
    }
}
