<?php

namespace Core\Facades;

class Auth extends Facade
{
    protected static function accessor(): string
    {
        return 'auth';
    }
}
