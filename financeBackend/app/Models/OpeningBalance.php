<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpeningBalance extends Model
{
    protected $fillable = ['store_id', 'scope', 'key', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:3',
        ];
    }
}
