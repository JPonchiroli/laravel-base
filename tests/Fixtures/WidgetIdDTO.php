<?php

namespace Jponchiroli\LaravelBase\Tests\Fixtures;

use Jponchiroli\LaravelBase\Base\BaseDTO;

class WidgetIdDTO extends BaseDTO
{
    public function __construct(public mixed $id) {}

    public static function fromArray(array $data): self
    {
        return new self($data['id'] ?? null);
    }
}
