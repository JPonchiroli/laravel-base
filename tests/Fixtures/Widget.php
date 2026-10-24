<?php

namespace Jponchiroli\LaravelBase\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class Widget extends Model
{
    protected $table = 'widgets';

    protected $fillable = ['name', 'color', 'qty'];
}
