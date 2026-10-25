<?php

namespace Core\Facades;

class Cache extends Facade
{
    protected static function accessor(): string
    {
        return 'cache';
    }
}
