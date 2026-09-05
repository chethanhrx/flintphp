<?php

declare(strict_types=1);

namespace FlintPHP\Framework\OpenApi;

use FlintPHP\Framework\OpenApi\Exception\SerializationException;

/**
 * Serializes an OpenAPI document to JSON.
 */
final class OpenApiSerializer
{
    /**
     * Serialize the document to a JSON string.
     *
     * @throws SerializationException
     */
    public function toJson(OpenApiDocument $document): string
    {
        try {
            return json_encode(
                $document->toArray(),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        } catch (\JsonException $e) {
            throw new SerializationException('Failed to serialize OpenAPI document to JSON: ' . $e->getMessage(), 0, $e);
        }
    }
}
