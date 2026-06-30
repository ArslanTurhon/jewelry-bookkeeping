<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Exchange extends Model
{
    protected $fillable = [
        'store_id',
        'recorded_by_admin_id',
        'direction',
        'online_method',
        'amount',
        'fee',
        'exchange_date',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'exchange_date' => 'date:Y-m-d',
        ];
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'recorded_by_admin_id');
    }
}
