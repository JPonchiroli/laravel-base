<?php

namespace Jponchiroli\LaravelBase\Base;

abstract class BaseDTO
{
    /**
     * @param  array<string, mixed>  $data
     */
    abstract public static function fromArray(array $data): self;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
