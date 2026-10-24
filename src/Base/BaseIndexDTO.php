<?php

namespace Jponchiroli\LaravelBase\Base;

use Illuminate\Support\Arr;

abstract class BaseIndexDTO extends BaseDTO
{
    /**
     * @param  array<string, mixed>  $filters
     */
    final public function __construct(
        public array $filters = [],
        public ?int $perPage = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new static(
            filters: Arr::except($data, ['per_page', 'page']),
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : null,
        );
    }
}
