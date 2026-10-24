<?php

namespace Jponchiroli\LaravelBase\Base;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class BaseService
{
    public function __construct(
        protected BaseRepository $repository
    ) {}

    public function getById(BaseDTO $dto): ?Model
    {
        return $this->repository->get($dto);
    }

    /**
     * @return LengthAwarePaginator<int, Model>
     */
    public function getPaginate(BaseIndexDTO $dto): LengthAwarePaginator
    {
        return $this->repository->getPaginate($dto);
    }

    public function store(BaseDTO $dto): Model
    {
        return $this->repository->create($dto);
    }

    public function update(BaseDTO $dto): ?Model
    {
        return $this->repository->update($dto);
    }

    public function patch(BaseDTO $dto): ?Model
    {
        return $this->repository->patch($dto);
    }

    public function delete(BaseDTO $dto): bool
    {
        return $this->repository->delete($dto);
    }
}
