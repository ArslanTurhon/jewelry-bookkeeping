<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyReconciliation extends Model
{
    protected $fillable = ['store_id', 'reconciliation_date', 'status'];

    protected function casts(): array
    {
        return ['reconciliation_date' => 'date:Y-m-d'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ReconciliationSection::class);
    }

    public function recalculateStatus(): void
    {
        $sections = $this->sections()->get();
        $required = [];
        $staff = AdminUser::query()->where('store_id', $this->store_id)->where('enabled', true)->get();

        if ($staff->contains(fn (AdminUser $user) => $user->hasPermission('recycle_pure_gold'))) {
            $required[] = 'pure_gold';
        }
        if ($staff->contains(fn (AdminUser $user) => $user->hasPermission('transactions'))) {
            $required[] = 'general';
        }
        $required = $required ?: $sections->pluck('section_type')->all();
        $requiredSections = collect($required)->map(fn (string $type) => $sections->firstWhere('section_type', $type));

        $status = match (true) {
            $requiredSections->contains(fn ($section) => $section?->status === 'returned') => 'returned',
            $requiredSections->isNotEmpty() && $requiredSections->every(fn ($section) => $section?->status === 'confirmed') => 'confirmed',
            $requiredSections->isNotEmpty() && $requiredSections->every(fn ($section) => in_array($section?->status, ['submitted', 'confirmed'], true)) => 'submitted',
            $requiredSections->contains(fn ($section) => in_array($section?->status, ['submitted', 'confirmed'], true)) => 'partial',
            default => 'pending',
        };

        $this->update(['status' => $status]);
    }
}
