<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevenueConfig extends Model
{
    protected $fillable = ['percentage', 'effective_from', 'note', 'created_by'];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'effective_from' => 'date',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeEffective($query, ?string $on = null)
    {
        return $query->whereDate('effective_from', '<=', $on ?? now()->toDateString())
            ->orderByDesc('effective_from')
            ->orderByDesc('id');
    }
}
