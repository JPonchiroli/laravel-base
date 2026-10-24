<?php

namespace Jponchiroli\LaravelBase\Tests\Unit;

use Jponchiroli\LaravelBase\Base\BaseDTO;
use Jponchiroli\LaravelBase\Base\BasePatchDTO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BasePatchDTOTest extends TestCase
{
    #[Test]
    public function it_separates_the_id_from_the_sent_attributes(): void
    {
        $dto = BasePatchDTO::fromArray(['name' => 'Nut', 'qty' => 3, 'id' => 7]);

        $this->assertInstanceOf(BaseDTO::class, $dto);
        $this->assertSame('7', $dto->id);
        $this->assertSame(['name' => 'Nut', 'qty' => 3], $dto->attributes);
    }

    #[Test]
    public function to_array_has_the_id_and_only_the_sent_fields(): void
    {
        $this->assertSame(
            ['id' => '7', 'name' => 'Nut'],
            BasePatchDTO::fromArray(['id' => 7, 'name' => 'Nut'])->toArray(),
        );
        $this->assertSame(['id' => '7'], BasePatchDTO::fromArray(['id' => 7])->toArray());
    }

    #[Test]
    public function null_values_stay_in_the_dto_for_the_repository_to_ignore(): void
    {
        $this->assertSame(
            ['id' => '1', 'color' => null],
            BasePatchDTO::fromArray(['id' => 1, 'color' => null])->toArray(),
        );
    }

    #[Test]
    public function a_subclass_without_code_works_and_is_returned_as_static(): void
    {
        $dto = ProductPatchStub::fromArray(['id' => 1, 'price' => 9.9]);

        $this->assertInstanceOf(ProductPatchStub::class, $dto);
        $this->assertSame(['id' => '1', 'price' => 9.9], $dto->toArray());
    }
}

class ProductPatchStub extends BasePatchDTO {}
