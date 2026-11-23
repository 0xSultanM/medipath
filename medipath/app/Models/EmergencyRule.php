<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmergencyRule extends Model
{
    protected $fillable = [
        'category', 'title', 'symptom_keywords', 'severity',
        'action_directive', 'hotline_key', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'symptom_keywords' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function hotline(): BelongsTo
    {
        return $this->belongsTo(EmergencyContact::class, 'hotline_key', 'key');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
