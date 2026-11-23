<?php

namespace App\Models;

use App\Enums\PrescriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Prescription extends Model
{
    use HasFactory;

    protected $fillable = [
        'prescription_code', 'patient_id', 'doctor_id', 'appointment_id',
        'status', 'clinical_notes', 'pharmacy_notes', 'patient_notes',
        'safety_snapshot', 'override_justification', 'override_approved_by',
        'ai_safety_summary', 'ai_status', 'dispensed_at', 'dispensed_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PrescriptionStatus::class,
            'safety_snapshot' => 'array',
            'dispensed_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function scopePendingDispense($query)
    {
        return $query->where('status', PrescriptionStatus::PendingDispense->value);
    }

    public function hasOverride(): bool
    {
        return filled($this->override_justification);
    }
}
