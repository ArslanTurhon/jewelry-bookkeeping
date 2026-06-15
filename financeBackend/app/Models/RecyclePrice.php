<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecyclePrice extends Model
{
    protected $fillable = [
        'price_date',
        'reference_gold_price',
        'reference_silver_price',
    ];

    protected function casts(): array
    {
        return [
            'price_date' => 'date:Y-m-d',
            'reference_gold_price' => 'decimal:2',
            'reference_silver_price' => 'decimal:2',
        ];
    }
}
