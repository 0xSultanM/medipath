<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SymptomCheckLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'patient_id', 'session_key', 'symptoms', 'ai_response', 'matched_rules',
        'emergency_triggered', 'matched_rule_id', 'source', 'ip_address', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'symptoms' => 'array',
            'ai_response' => 'array',
            'matched_rules' => 'array',
            'emergency_triggered' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function matchedRule(): BelongsTo
    {
        return $this->belongsTo(EmergencyRule::class, 'matched_rule_id');
    }
}
