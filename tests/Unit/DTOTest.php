<?php

namespace Jponchiroli\LaravelBase\Tests\Unit;

use Jponchiroli\LaravelBase\Base\BaseDTO;
use Jponchiroli\LaravelBase\Base\BaseIndexDTO;
use Jponchiroli\LaravelBase\Tests\Fixtures\WidgetCreateDTO;
use Jponchiroli\LaravelBase\Tests\Fixtures\WidgetIndexDTO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class DTOTest extends TestCase
{
    #[Test]
    public function base_dto_is_abstract(): void
    {
        $this->assertTrue((new ReflectionClass(BaseDTO::class))->isAbstract());
        $this->assertTrue((new ReflectionClass(BaseIndexDTO::class))->isAbstract());
    }

    #[Test]
    public function to_array_returns_public_properties_by_name(): void
    {
        $dto = WidgetCreateDTO::fromArray(['name' => 'Bolt', 'qty' => 3]);

        $this->assertSame(['name' => 'Bolt', 'color' => null, 'qty' => 3], $dto->toArray());
    }

    #[Test]
    public function index_dto_splits_filters_from_pagination(): void
    {
        $dto = WidgetIndexDTO::fromArray(['per_page' => '15', 'page' => 2, 'color' => 'red']);

        $this->assertSame(['color' => 'red'], $dto->filters);
        $this->assertSame(15, $dto->perPage);
    }

    #[Test]
    public function index_dto_without_per_page_leaves_it_null(): void
    {
        $dto = WidgetIndexDTO::fromArray(['name' => 'Bolt']);

        $this->assertSame(['name' => 'Bolt'], $dto->filters);
        $this->assertNull($dto->perPage);
    }

    #[Test]
    public function index_dto_with_no_data_is_empty(): void
    {
        $dto = WidgetIndexDTO::fromArray([]);

        $this->assertSame([], $dto->filters);
        $this->assertNull($dto->perPage);
    }

    #[Test]
    public function index_dto_returns_the_calling_subclass(): void
    {
        $this->assertInstanceOf(WidgetIndexDTO::class, WidgetIndexDTO::fromArray([]));
    }
}
