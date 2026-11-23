<?php

namespace App\Models;

use App\Exceptions\ImmutableRecordException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $table = 'activity_log';

    protected $fillable = [
        'actor_id', 'actor_role', 'action', 'target_type', 'target_id',
        'detail', 'ip_address', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'detail' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Append-only contract (PLAN §4 / G8). No route may update or delete.
        static::updating(fn () => throw ImmutableRecordException::forModel(self::class, 'row'));
        static::deleting(fn () => throw ImmutableRecordException::forModel(self::class, 'row'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function scopeAction($query, string $action)
    {
        return $query->where('action', $action);
    }
}
