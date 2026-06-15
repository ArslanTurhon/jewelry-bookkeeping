<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class AdminUser extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password', 'api_token'];

    protected $hidden = ['password', 'api_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
