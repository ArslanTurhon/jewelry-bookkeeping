<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Translation extends Model
{
    protected $fillable = ['language_code', 'translation_key', 'translation_value'];

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'language_code', 'code');
    }
}
