<?php

namespace App\Support\Facades;

use Illuminate\Support\Facades\Facade;

class FinanceLog extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'log';
    }

    public static function __callStatic($method, $parameters)
    {
        return static::$app->make('log')->channel('finance')->$method(...$parameters);
    }
}
