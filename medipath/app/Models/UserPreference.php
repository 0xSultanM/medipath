<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    protected $fillable = [
        'user_id', 'theme', 'language', 'timezone', 'date_format',
        'notification_prefs', 'reduce_motion',
    ];

    protected function casts(): array
    {
        return [
            'notification_prefs' => 'array',
            'reduce_motion' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
