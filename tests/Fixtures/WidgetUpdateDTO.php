<?php

namespace Jponchiroli\LaravelBase\Tests\Fixtures;

use Jponchiroli\LaravelBase\Base\BaseDTO;

class WidgetUpdateDTO extends BaseDTO
{
    public function __construct(
        public string $id,
        public ?string $name = null,
        public ?string $color = null,
        public ?int $qty = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self((string) $data['id'], $data['name'] ?? null, $data['color'] ?? null, $data['qty'] ?? null);
    }
}
