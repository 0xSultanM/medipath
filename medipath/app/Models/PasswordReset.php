<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
    public $timestamps = false;

    protected $table = 'password_resets';

    protected $fillable = [
        'email', 'token_hash', 'expires_at', 'consumed_at', 'ip_address', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function scopeValid($query)
    {
        return $query->whereNull('consumed_at')->where('expires_at', '>', now());
    }
}
