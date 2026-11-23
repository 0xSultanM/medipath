<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingApproval extends Model
{
    protected $fillable = [
        'user_id', 'requested_role', 'license_details', 'credential_path',
        'status', 'review_reason', 'reviewer_id', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'license_details' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
