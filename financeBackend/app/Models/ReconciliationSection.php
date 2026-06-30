<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationSection extends Model
{
    protected $fillable = [
        'daily_reconciliation_id',
        'section_type',
        'status',
        'submitted_by_admin_id',
        'version',
        'no_business',
        'business_summary',
        'actual_snapshot',
        'book_snapshot',
        'differences',
        'difference_reason',
        'submitted_at',
        'reviewed_by_admin_id',
        'reviewed_at',
        'return_reason',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'no_business' => 'boolean',
            'business_summary' => 'array',
            'actual_snapshot' => 'array',
            'book_snapshot' => 'array',
            'differences' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(DailyReconciliation::class, 'daily_reconciliation_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'submitted_by_admin_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'reviewed_by_admin_id');
    }

    public function getStoreIdAttribute(): ?int
    {
        return $this->reconciliation?->store_id;
    }
}
