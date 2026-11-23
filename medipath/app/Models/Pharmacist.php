<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pharmacist extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'pharmacist_code', 'license_number', 'license_authority',
        'credential_path', 'pharmacy_name', 'approved_at', 'approved_by', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
