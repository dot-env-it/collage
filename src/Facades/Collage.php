<?php

namespace DotEnvIt\Collage\Facades;

use Illuminate\Support\Facades\Facade;

class Collage extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'collage';
    }
}