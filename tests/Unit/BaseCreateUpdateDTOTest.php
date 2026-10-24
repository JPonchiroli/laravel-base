<?php

namespace Jponchiroli\LaravelBase\Tests\Unit;

use Jponchiroli\LaravelBase\Base\BaseCreateDTO;
use Jponchiroli\LaravelBase\Base\BaseDTO;
use Jponchiroli\LaravelBase\Base\BasePatchDTO;
use Jponchiroli\LaravelBase\Base\BaseUpdateDTO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BaseCreateUpdateDTOTest extends TestCase
{
    #[Test]
    public function create_dto_carries_every_validated_attribute(): void
    {
        $dto = BaseCreateDTO::fromArray(['name' => 'Bolt', 'qty' => 3, 'color' => null]);

        $this->assertInstanceOf(BaseDTO::class, $dto);
        $this->assertSame(['name' => 'Bolt', 'qty' => 3, 'color' => null], $dto->toArray());
    }

    #[Test]
    public function create_dto_works_with_no_data_and_as_a_subclass(): void
    {
        $this->assertSame([], BaseCreateDTO::fromArray([])->toArray());
        $this->assertInstanceOf(NoteCreateStub::class, NoteCreateStub::fromArray(['title' => 'a']));
    }

    #[Test]
    public function update_dto_carries_the_id_and_the_sent_attributes(): void
    {
        $dto = BaseUpdateDTO::fromArray(['id' => 5, 'title' => 'a', 'body' => null]);

        $this->assertInstanceOf(BasePatchDTO::class, $dto);
        $this->assertSame(['id' => '5', 'title' => 'a', 'body' => null], $dto->toArray());
        $this->assertInstanceOf(NoteUpdateStub::class, NoteUpdateStub::fromArray(['id' => 1]));
    }
}

class NoteCreateStub extends BaseCreateDTO {}

class NoteUpdateStub extends BaseUpdateDTO {}
