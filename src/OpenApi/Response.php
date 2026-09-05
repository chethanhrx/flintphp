<?php

declare(strict_types=1);

namespace FlintPHP\Framework\OpenApi;

/**
 * Represents an OpenAPI Response Object.
 */
final readonly class Response implements OpenApiComponent
{
    /**
     * @param string $description Response description.
     * @param array<string, Schema|Reference>|null $content A map of content types (e.g., 'application/json') to schemas.
     */
    public function __construct(
        public string $description,
        public ?array $content = null,
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'description' => $this->description,
        ];
        
        if ($this->content !== null) {
            $content = [];
            foreach ($this->content as $type => $schema) {
                $content[$type] = ['schema' => $schema->toArray()];
            }
            $data['content'] = empty($content) ? new \stdClass() : $content;
        }

        return $data;
    }
}
