<?php

declare(strict_types=1);

namespace FlintPHP\Framework\OpenApi;

use FlintPHP\Framework\OpenApi\Exception\InvalidDocumentException;

/**
 * Represents an OpenAPI Components Object.
 */
final readonly class Components implements OpenApiComponent
{
    /**
     * @param array<string, Schema|Reference>|null $schemas
     * @param array<string, Response|Reference>|null $responses
     * @param array<string, Parameter|Reference>|null $parameters
     * @param array<string, RequestBody|Reference>|null $requestBodies
     */
    public function __construct(
        public ?array $schemas = null,
        public ?array $responses = null,
        public ?array $parameters = null,
        public ?array $requestBodies = null,
    ) {
        $this->validateKeys($schemas, 'schemas');
        $this->validateKeys($responses, 'responses');
        $this->validateKeys($parameters, 'parameters');
        $this->validateKeys($requestBodies, 'requestBodies');
    }

    /**
     * @param array<string, mixed>|null $items
     * @throws InvalidDocumentException
     */
    private function validateKeys(?array $items, string $type): void
    {
        if ($items === null) {
            return;
        }

        foreach ($items as $name => $value) {
            if (preg_match('/^[a-zA-Z0-9.\-_]+$/', (string) $name) !== 1) {
                throw new InvalidDocumentException(sprintf('Invalid component name "%s" in %s. Component names must match ^[a-zA-Z0-9.\-_]+$', $name, $type));
            }
        }
    }

    public function toArray(): array
    {
        $data = [];
        
        if ($this->schemas !== null) {
            $schemas = [];
            foreach ($this->schemas as $name => $schema) {
                $schemas[$name] = $schema->toArray();
            }
            $data['schemas'] = empty($schemas) ? new \stdClass() : $schemas;
        }
        
        if ($this->responses !== null) {
            $responses = [];
            foreach ($this->responses as $name => $response) {
                $responses[$name] = $response->toArray();
            }
            $data['responses'] = empty($responses) ? new \stdClass() : $responses;
        }

        if ($this->parameters !== null) {
            $parameters = [];
            foreach ($this->parameters as $name => $parameter) {
                $parameters[$name] = $parameter->toArray();
            }
            $data['parameters'] = empty($parameters) ? new \stdClass() : $parameters;
        }

        if ($this->requestBodies !== null) {
            $requestBodies = [];
            foreach ($this->requestBodies as $name => $requestBody) {
                $requestBodies[$name] = $requestBody->toArray();
            }
            $data['requestBodies'] = empty($requestBodies) ? new \stdClass() : $requestBodies;
        }

        return $data;
    }
}
