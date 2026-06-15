<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Language extends Model
{
    protected $fillable = ['code', 'name', 'enabled', 'sort_order'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class, 'language_code', 'code');
    }
}
