<?php

namespace Jponchiroli\LaravelBase\Base;

/**
 * Create payload: carries the validated attributes as they were sent, so a
 * Create DTO works without declaring each column.
 */
class BaseCreateDTO extends BaseDTO
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    final public function __construct(
        public array $attributes = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new static($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
