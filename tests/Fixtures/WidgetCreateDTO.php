<?php

namespace Jponchiroli\LaravelBase\Tests\Fixtures;

use Jponchiroli\LaravelBase\Base\BaseDTO;

class WidgetCreateDTO extends BaseDTO
{
    public function __construct(
        public string $name,
        public ?string $color = null,
        public ?int $qty = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self($data['name'], $data['color'] ?? null, $data['qty'] ?? null);
    }
}
