<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Orm\Exception;

final class ModelNotFoundException extends OrmException
{
    public static function forModel(string $modelClass, mixed $id): self
    {
        return new self(sprintf('No query results for model [%s] with id [%s].', $modelClass, (string) $id));
    }
}
