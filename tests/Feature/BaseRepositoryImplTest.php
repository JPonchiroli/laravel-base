<?php

namespace Jponchiroli\LaravelBase\Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Jponchiroli\LaravelBase\Tests\Fixtures\Widget;
use Jponchiroli\LaravelBase\Tests\Fixtures\WidgetCreateDTO;
use Jponchiroli\LaravelBase\Tests\Fixtures\WidgetIdDTO;
use Jponchiroli\LaravelBase\Tests\Fixtures\WidgetIndexDTO;
use Jponchiroli\LaravelBase\Tests\Fixtures\WidgetRepository;
use Jponchiroli\LaravelBase\Tests\Fixtures\WidgetUpdateDTO;
use Jponchiroli\LaravelBase\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class BaseRepositoryImplTest extends TestCase
{
    private WidgetRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new WidgetRepository(new Widget);
    }

    private function makeWidget(string $name, ?string $color = null, ?int $qty = null): Widget
    {
        return $this->repository->create(new WidgetCreateDTO($name, $color, $qty));
    }

    #[Test]
    public function it_exposes_the_model_table(): void
    {
        $this->assertSame('widgets', $this->repository->getTable());
    }

    #[Test]
    public function create_persists_and_returns_the_model(): void
    {
        $model = $this->makeWidget('Bolt', 'red', 5);

        $this->assertInstanceOf(Widget::class, $model);
        $this->assertDatabaseHas('widgets', ['id' => $model->id, 'name' => 'Bolt', 'color' => 'red', 'qty' => 5]);
    }

    #[Test]
    public function get_finds_by_id_accepting_string_or_int(): void
    {
        $model = $this->makeWidget('Bolt');

        $this->assertSame($model->id, $this->repository->get(new WidgetIdDTO($model->id))->id);
        $this->assertSame($model->id, $this->repository->get(new WidgetIdDTO((string) $model->id))->id);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unusableIds(): array
    {
        return ['missing record' => [999], 'null' => [null], 'array' => [[1]], 'float' => [1.5]];
    }

    #[Test]
    #[DataProvider('unusableIds')]
    public function get_returns_null_for_unusable_ids(mixed $id): void
    {
        $this->makeWidget('Bolt');

        $this->assertNull($this->repository->get(new WidgetIdDTO($id)));
    }

    #[Test]
    public function get_paginate_returns_everything_without_filters(): void
    {
        foreach (range(1, 20) as $i) {
            $this->makeWidget("W$i");
        }

        $page = $this->repository->getPaginate(WidgetIndexDTO::fromArray([]));

        $this->assertInstanceOf(LengthAwarePaginator::class, $page);
        $this->assertSame(20, $page->total());
        $this->assertSame(15, $page->perPage());
        $this->assertCount(15, $page->items());
    }

    #[Test]
    public function get_paginate_honours_per_page(): void
    {
        foreach (range(1, 5) as $i) {
            $this->makeWidget("W$i");
        }

        $page = $this->repository->getPaginate(WidgetIndexDTO::fromArray(['per_page' => 2]));

        $this->assertSame(2, $page->perPage());
        $this->assertCount(2, $page->items());
        $this->assertSame(3, $page->lastPage());
    }

    #[Test]
    public function get_paginate_filters_by_field_value(): void
    {
        $this->makeWidget('A', 'red');
        $this->makeWidget('B', 'blue');
        $this->makeWidget('C', 'red');

        $page = $this->repository->getPaginate(WidgetIndexDTO::fromArray(['color' => 'red']));

        $this->assertSame(2, $page->total());
        $this->assertSame(['A', 'C'], collect($page->items())->pluck('name')->all());
    }

    #[Test]
    public function get_paginate_combines_filters_and_ignores_null_values(): void
    {
        $this->makeWidget('A', 'red', 1);
        $this->makeWidget('B', 'red', 2);
        $this->makeWidget('C', 'blue', 2);

        $page = $this->repository->getPaginate(WidgetIndexDTO::fromArray(['color' => 'red', 'qty' => 2, 'name' => null]));

        $this->assertSame(['B'], collect($page->items())->pluck('name')->all());
    }

    #[Test]
    public function update_replaces_the_sent_attributes_including_nulls(): void
    {
        $model = $this->makeWidget('Bolt', 'red', 5);

        $updated = $this->repository->update(new WidgetUpdateDTO((string) $model->id, 'Nut', null, 7));

        $this->assertSame('Nut', $updated->name);
        $this->assertDatabaseHas('widgets', ['id' => $model->id, 'name' => 'Nut', 'color' => null, 'qty' => 7]);
    }

    #[Test]
    public function update_returns_null_when_the_record_is_missing(): void
    {
        $this->assertNull($this->repository->update(new WidgetUpdateDTO('999', 'Nut')));
        $this->assertDatabaseCount('widgets', 0);
    }

    #[Test]
    public function patch_keeps_current_values_for_null_attributes(): void
    {
        $model = $this->makeWidget('Bolt', 'red', 5);

        $patched = $this->repository->patch(new WidgetUpdateDTO((string) $model->id, 'Nut'));

        $this->assertSame('Nut', $patched->name);
        $this->assertDatabaseHas('widgets', ['id' => $model->id, 'name' => 'Nut', 'color' => 'red', 'qty' => 5]);
    }

    #[Test]
    public function patch_returns_null_when_the_record_is_missing(): void
    {
        $this->assertNull($this->repository->patch(new WidgetUpdateDTO('999', 'Nut')));
    }

    #[Test]
    public function delete_removes_the_record(): void
    {
        $model = $this->makeWidget('Bolt');

        $this->assertTrue($this->repository->delete(new WidgetIdDTO($model->id)));
        $this->assertDatabaseMissing('widgets', ['id' => $model->id]);
    }

    #[Test]
    public function delete_returns_false_when_the_record_is_missing(): void
    {
        $this->makeWidget('Bolt');

        $this->assertFalse($this->repository->delete(new WidgetIdDTO(999)));
        $this->assertDatabaseCount('widgets', 1);
    }
}
