<?php

namespace Jponchiroli\LaravelBase\Base;

use Illuminate\Support\Arr;

/**
 * Partial update: carries the primary key plus only the attributes that were sent,
 * so a Patch DTO works without declaring each column.
 */
class BasePatchDTO extends BaseDTO
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    final public function __construct(
        public string $id,
        public array $attributes = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new static((string) $data['id'], Arr::except($data, 'id'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['id' => $this->id, ...$this->attributes];
    }
}
