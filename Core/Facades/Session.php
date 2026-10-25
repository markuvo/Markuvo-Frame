<?php

namespace Core\Facades;

class Session extends Facade
{
    protected static function accessor(): string
    {
        return 'session';
    }
}
