<?php

declare(strict_types=1);

namespace FlintPHP\Framework\OpenApi;

/**
 * Represents an OpenAPI Path Item Object.
 */
final readonly class PathItem implements OpenApiComponent
{
    /**
     * @param Operation|null $get
     * @param Operation|null $put
     * @param Operation|null $post
     * @param Operation|null $delete
     * @param Operation|null $options
     * @param Operation|null $head
     * @param Operation|null $patch
     * @param Operation|null $trace
     * @param string|null $summary
     * @param string|null $description
     * @param array<Parameter|Reference>|null $parameters
     */
    public function __construct(
        public ?Operation $get = null,
        public ?Operation $put = null,
        public ?Operation $post = null,
        public ?Operation $delete = null,
        public ?Operation $options = null,
        public ?Operation $head = null,
        public ?Operation $patch = null,
        public ?Operation $trace = null,
        public ?string $summary = null,
        public ?string $description = null,
        public ?array $parameters = null,
    ) {
    }

    public function toArray(): array
    {
        $data = [];
        
        if ($this->summary !== null) {
            $data['summary'] = $this->summary;
        }
        if ($this->description !== null) {
            $data['description'] = $this->description;
        }
        if ($this->get !== null) {
            $data['get'] = $this->get->toArray();
        }
        if ($this->put !== null) {
            $data['put'] = $this->put->toArray();
        }
        if ($this->post !== null) {
            $data['post'] = $this->post->toArray();
        }
        if ($this->delete !== null) {
            $data['delete'] = $this->delete->toArray();
        }
        if ($this->options !== null) {
            $data['options'] = $this->options->toArray();
        }
        if ($this->head !== null) {
            $data['head'] = $this->head->toArray();
        }
        if ($this->patch !== null) {
            $data['patch'] = $this->patch->toArray();
        }
        if ($this->trace !== null) {
            $data['trace'] = $this->trace->toArray();
        }
        if ($this->parameters !== null) {
            $data['parameters'] = array_map(fn($p) => $p->toArray(), $this->parameters);
        }

        return $data;
    }
}
