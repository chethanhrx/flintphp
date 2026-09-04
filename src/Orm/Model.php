<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Orm;

abstract class Model
{
    /**
     * The table associated with the model.
     */
    protected string $table;

    /**
     * The primary key for the model.
     */
    protected string $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected array $fillable = [];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get the primary key for the model.
     */
    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    /**
     * Get the fillable attributes for the model.
     *
     * @return array<int, string>
     */
    public function getFillable(): array
    {
        return $this->fillable;
    }
}
