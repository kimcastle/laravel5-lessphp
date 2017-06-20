<?php

namespace Kimcastle\Laravel5Less\Facades;

use Illuminate\Support\Facades\Facade;

class Less extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'less';
    }
}