<?php

namespace Tests\Fixtures;

use Core\Model;

/** Tablo adı verilmedi: sınıf adından türetilir -> blogs */
class BlogModel extends Model
{
    protected $fillable = ['baslik'];
}
