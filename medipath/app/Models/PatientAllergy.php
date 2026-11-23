<?php

namespace App\Models;

use App\Enums\AllergySeverity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientAllergy extends Model
{
    protected $fillable = [
        'patient_id', 'substance', 'allergy_type', 'severity', 'reaction_notes', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['severity' => AllergySeverity::class];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function isSevere(): bool
    {
        return in_array($this->severity, [AllergySeverity::Severe, AllergySeverity::LifeThreatening], true);
    }
}
