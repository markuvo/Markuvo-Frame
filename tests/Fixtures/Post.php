<?php

namespace Tests\Fixtures;

use Core\Model;

class Post extends Model
{
    protected $fillable = ['title', 'status', 'views'];
    protected $hidden = ['secret'];

    public function getTitleAttribute($value)
    {
        return $value === null ? null : strtoupper($value);
    }

    public function setStatusAttribute($value)
    {
        return strtolower($value);
    }
}
