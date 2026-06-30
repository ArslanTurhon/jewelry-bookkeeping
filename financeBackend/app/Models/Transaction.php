<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'store_id',
        'recorded_by_admin_id',
        'business_type',
        'payment_account',
        'online_method',
        'amount',
        'cash_amount',
        'online_amount',
        'recycle_price_rate',
        'stock_bucket',
        'product_type',
        'wrap_material',
        'pure_gold_weight',
        'wrapped_gold_weight',
        'material_weight',
        'material_pieces',
        'item_weights',
        'gold_unit_price',
        'silver_unit_price',
        'reference_gold_price',
        'reference_silver_price',
        'expense_category',
        'transaction_date',
        'remark',
        'voided_at',
        'voided_by_admin_id',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'cash_amount' => 'decimal:2',
            'online_amount' => 'decimal:2',
            'recycle_price_rate' => 'decimal:2',
            'pure_gold_weight' => 'decimal:3',
            'wrapped_gold_weight' => 'decimal:3',
            'material_weight' => 'decimal:3',
            'material_pieces' => 'integer',
            'item_weights' => 'array',
            'gold_unit_price' => 'decimal:2',
            'silver_unit_price' => 'decimal:2',
            'reference_gold_price' => 'decimal:2',
            'reference_silver_price' => 'decimal:2',
            'transaction_date' => 'date:Y-m-d',
            'voided_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
