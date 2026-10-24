<?php

namespace Jponchiroli\LaravelBase\Tests\Unit;

use Illuminate\Pagination\LengthAwarePaginator;
use Jponchiroli\LaravelBase\Base\BaseRepository;
use Jponchiroli\LaravelBase\Base\BaseService;
use Jponchiroli\LaravelBase\Tests\Fixtures\Widget;
use Jponchiroli\LaravelBase\Tests\Fixtures\WidgetCreateDTO;
use Jponchiroli\LaravelBase\Tests\Fixtures\WidgetIdDTO;
use Jponchiroli\LaravelBase\Tests\Fixtures\WidgetIndexDTO;
use Jponchiroli\LaravelBase\Tests\Fixtures\WidgetUpdateDTO;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BaseServiceTest extends TestCase
{
    private BaseRepository&MockInterface $repository;

    private BaseService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(BaseRepository::class);
        $this->service = new BaseService($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    #[Test]
    public function get_by_id_delegates_to_get(): void
    {
        $dto = new WidgetIdDTO(1);
        $model = new Widget;
        $this->repository->shouldReceive('get')->once()->with($dto)->andReturn($model);

        $this->assertSame($model, $this->service->getById($dto));
    }

    #[Test]
    public function get_by_id_propagates_null(): void
    {
        $this->repository->shouldReceive('get')->once()->andReturnNull();

        $this->assertNull($this->service->getById(new WidgetIdDTO(99)));
    }

    #[Test]
    public function get_paginate_delegates(): void
    {
        $dto = new WidgetIndexDTO;
        $page = new LengthAwarePaginator([], 0, 15);
        $this->repository->shouldReceive('getPaginate')->once()->with($dto)->andReturn($page);

        $this->assertSame($page, $this->service->getPaginate($dto));
    }

    #[Test]
    public function store_delegates_to_create(): void
    {
        $dto = new WidgetCreateDTO('Bolt');
        $model = new Widget;
        $this->repository->shouldReceive('create')->once()->with($dto)->andReturn($model);

        $this->assertSame($model, $this->service->store($dto));
    }

    #[Test]
    public function update_and_patch_delegate_and_propagate_null(): void
    {
        $dto = new WidgetUpdateDTO('1', 'Bolt');
        $model = new Widget;
        $this->repository->shouldReceive('update')->once()->with($dto)->andReturn($model);
        $this->repository->shouldReceive('patch')->once()->with($dto)->andReturnNull();

        $this->assertSame($model, $this->service->update($dto));
        $this->assertNull($this->service->patch($dto));
    }

    #[Test]
    public function delete_delegates_and_propagates_the_result(): void
    {
        $dto = new WidgetIdDTO(1);
        $this->repository->shouldReceive('delete')->twice()->with($dto)->andReturn(true, false);

        $this->assertTrue($this->service->delete($dto));
        $this->assertFalse($this->service->delete($dto));
    }
}
