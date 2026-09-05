<?php

declare(strict_types=1);

namespace FlintPHP\Framework\OpenApi;

/**
 * Represents an OpenAPI Schema Object.
 */
final readonly class Schema implements OpenApiComponent
{
    /**
     * @param string|null $type The data type (e.g., 'string', 'object', 'array').
     * @param string|null $format The data format.
     * @param string|null $description Schema description.
     * @param array<string, Schema|Reference>|null $properties Object properties.
     * @param Schema|Reference|null $items Array items.
     * @param string[]|null $required Required properties.
     * @param mixed $example An example value.
     */
    public function __construct(
        public ?string $type = null,
        public ?string $format = null,
        public ?string $description = null,
        public ?array $properties = null,
        public Schema|Reference|null $items = null,
        public ?array $required = null,
        public mixed $example = new Undefined(),
    ) {
    }

    public function toArray(): array
    {
        $data = [];
        if ($this->type !== null) {
            $data['type'] = $this->type;
        }
        if ($this->format !== null) {
            $data['format'] = $this->format;
        }
        if ($this->description !== null) {
            $data['description'] = $this->description;
        }
        if ($this->properties !== null) {
            $properties = [];
            foreach ($this->properties as $name => $prop) {
                $properties[$name] = $prop->toArray();
            }
            $data['properties'] = empty($properties) ? new \stdClass() : $properties;
        }
        if ($this->items !== null) {
            $data['items'] = $this->items->toArray();
        }
        if ($this->required !== null) {
            $data['required'] = $this->required;
        }
        if (!$this->example instanceof Undefined) {
            $data['example'] = $this->example;
        }

        return $data;
    }
}
