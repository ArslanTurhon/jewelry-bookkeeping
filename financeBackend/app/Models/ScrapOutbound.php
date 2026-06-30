<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScrapOutbound extends Model
{
    protected $fillable = [
        'store_id', 'recorded_by_admin_id', 'product_type', 'wrap_material',
        'pure_gold_weight', 'wrapped_gold_weight', 'material_weight', 'material_pieces',
        'gross_amount', 'received_amount', 'payment_account', 'online_method',
        'fees', 'cost_amount', 'profit_amount', 'outbound_date', 'remark',
    ];

    protected function casts(): array
    {
        return [
            'pure_gold_weight' => 'decimal:3',
            'wrapped_gold_weight' => 'decimal:3',
            'material_weight' => 'decimal:3',
            'material_pieces' => 'integer',
            'gross_amount' => 'decimal:2',
            'received_amount' => 'decimal:2',
            'fees' => 'array',
            'cost_amount' => 'decimal:2',
            'profit_amount' => 'decimal:2',
            'outbound_date' => 'date:Y-m-d',
        ];
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'recorded_by_admin_id');
    }
}
