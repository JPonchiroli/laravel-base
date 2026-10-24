<?php

namespace Jponchiroli\LaravelBase\Tests\Fixtures;

use Jponchiroli\LaravelBase\Base\BaseRepositoryImpl;

class WidgetRepository extends BaseRepositoryImpl
{
    public function __construct(Widget $model)
    {
        parent::__construct($model);
    }
}
