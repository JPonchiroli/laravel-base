<?php

namespace Jponchiroli\LaravelBase\Base;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class BaseRepositoryImpl implements BaseRepository
{
    public function __construct(
        protected Model $model
    ) {}

    public function getTable(): string
    {
        return $this->model->getTable();
    }

    public function get(BaseDTO $dto): ?Model
    {
        return $this->find($dto->toArray()['id'] ?? null);
    }

    public function getPaginate(BaseIndexDTO $dto): LengthAwarePaginator
    {
        $query = $this->model->newQuery();

        foreach ($dto->filters as $field => $value) {
            if ($value !== null) {
                $query->where($field, $value);
            }
        }

        return $query->paginate($dto->perPage ?? $this->model->getPerPage());
    }

    public function create(BaseDTO $dto): Model
    {
        return $this->model->newQuery()->create($dto->toArray());
    }

    public function update(BaseDTO $dto): ?Model
    {
        $attributes = $dto->toArray();
        $model = $this->find(Arr::pull($attributes, 'id'));

        $model?->update($attributes);

        return $model;
    }

    public function patch(BaseDTO $dto): ?Model
    {
        $attributes = $dto->toArray();
        $model = $this->find(Arr::pull($attributes, 'id'));

        $model?->update(array_filter($attributes, fn (mixed $value): bool => $value !== null));

        return $model;
    }

    public function delete(BaseDTO $dto): bool
    {
        return (bool) $this->find($dto->toArray()['id'] ?? null)?->delete();
    }

    protected function find(mixed $id): ?Model
    {
        if (! is_int($id) && ! is_string($id)) {
            return null;
        }

        return $this->model->newQuery()->find($id);
    }
}
