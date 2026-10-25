<?php

namespace Tests\Fixtures;

use Core\Model;

class Kategori extends Model
{
    protected $table = 'kategoriler';
    protected $primaryKey = 'kid';
    protected $timestamps = false;
    protected $fillable = ['ad'];
}
