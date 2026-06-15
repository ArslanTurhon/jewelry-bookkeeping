<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'business_type',
        'payment_account',
        'online_method',
        'amount',
        'stock_bucket',
        'product_type',
        'wrap_material',
        'pure_gold_weight',
        'wrapped_gold_weight',
        'material_weight',
        'material_pieces',
        'expense_category',
        'transaction_date',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'pure_gold_weight' => 'decimal:3',
            'wrapped_gold_weight' => 'decimal:3',
            'material_weight' => 'decimal:3',
            'material_pieces' => 'integer',
            'transaction_date' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

}
