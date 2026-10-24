<?php

namespace Jponchiroli\LaravelBase\Base;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

interface BaseRepository
{
    public function getTable(): string;

    public function get(BaseDTO $dto): ?Model;

    /**
     * @return LengthAwarePaginator<int, Model>
     */
    public function getPaginate(BaseIndexDTO $dto): LengthAwarePaginator;

    public function create(BaseDTO $dto): Model;

    public function update(BaseDTO $dto): ?Model;

    public function patch(BaseDTO $dto): ?Model;

    public function delete(BaseDTO $dto): bool;
}
