<?php

namespace Core\Facades;

class Log extends Facade
{
    protected static function accessor(): string
    {
        return 'log';
    }
}
