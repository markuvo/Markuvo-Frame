<?php

namespace App\Models;

use Core\Model;

class User extends Model
{
    protected $fillable = ['username', 'email', 'password', 'role'];

    // JSON / toArray çıktısında şifre asla görünmez
    protected $hidden = ['password'];
}
