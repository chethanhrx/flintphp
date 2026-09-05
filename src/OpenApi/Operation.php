<?php

declare(strict_types=1);

namespace FlintPHP\Framework\OpenApi;

use FlintPHP\Framework\OpenApi\Exception\InvalidDocumentException;

/**
 * Represents an OpenAPI Operation Object.
 */
final readonly class Operation implements OpenApiComponent
{
    /**
     * @param array<string, Response|Reference> $responses A map of HTTP status codes to Responses.
     * @param string|null $operationId Unique operation ID.
     * @param string|null $summary Operation summary.
     * @param string|null $description Operation description.
     * @param array<Parameter|Reference>|null $parameters Parameters array.
     * @param RequestBody|Reference|null $requestBody Request body.
     * @param string[]|null $tags Tags array.
     */
    public function __construct(
        public array $responses,
        public ?string $operationId = null,
        public ?string $summary = null,
        public ?string $description = null,
        public ?array $parameters = null,
        public RequestBody|Reference|null $requestBody = null,
        public ?array $tags = null,
    ) {
        foreach (array_keys($this->responses) as $status) {
            $statusStr = (string) $status;
            if ($statusStr !== 'default' && preg_match('/^(?:[1-5](?:XX|[0-9]{2}))$/', $statusStr) !== 1) {
                throw new InvalidDocumentException(sprintf('Invalid HTTP status code "%s". Must be default, 1XX-5XX, or 100-599.', $statusStr));
            }
        }
    }

    public function toArray(): array
    {
        $responses = [];
        foreach ($this->responses as $status => $response) {
            $responses[(string) $status] = $response->toArray();
        }

        $data = [
            'responses' => empty($responses) ? new \stdClass() : $responses,
        ];
        
        if ($this->operationId !== null) {
            $data['operationId'] = $this->operationId;
        }
        if ($this->summary !== null) {
            $data['summary'] = $this->summary;
        }
        if ($this->description !== null) {
            $data['description'] = $this->description;
        }
        if ($this->tags !== null) {
            $data['tags'] = $this->tags;
        }
        if ($this->parameters !== null) {
            $data['parameters'] = array_map(fn($p) => $p->toArray(), $this->parameters);
        }
        if ($this->requestBody !== null) {
            $data['requestBody'] = $this->requestBody->toArray();
        }

        return $data;
    }
}
