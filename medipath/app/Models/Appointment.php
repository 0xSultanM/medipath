<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_code', 'patient_id', 'doctor_id', 'date', 'start_time',
        'end_time', 'status', 'visit_reason', 'rejection_reason',
        'cancellation_reason', 'reschedule_count', 'cancelled_by',
        'cancelled_at', 'accepted_at', 'completed_at', 'created_by',
        'is_walk_in', 'active_slot_key',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => AppointmentStatus::class,
            'cancelled_at' => 'datetime',
            'accepted_at' => 'datetime',
            'completed_at' => 'datetime',
            'is_walk_in' => 'boolean',
            'reschedule_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        /*
         * The generated active-slot key is the DB-level concurrency guard:
         * a second pending/accepted appointment for the same doctor, date and
         * start time violates uq_doctor_date_active_slot. Terminal rows get
         * NULL so they never block a future booking.
         */
        static::saving(function (Appointment $appointment): void {
            $status = $appointment->status instanceof AppointmentStatus
                ? $appointment->status
                : AppointmentStatus::from((string) $appointment->status);

            $start = substr((string) $appointment->start_time, 0, 5);
            $appointment->active_slot_key = $status->holdsSlot() ? $start : null;
            $appointment->end_time = $appointment->end_time ?: null;
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }

    public function scopeActiveSlot($query)
    {
        return $query->whereIn('status', ['pending', 'accepted']);
    }

    public function scopeForDoctorDate(Builder $query, int $doctorId, string $date): Builder
    {
        return $query->where('doctor_id', $doctorId)->whereDate('date', $date);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('date', '>=', now()->toDateString())->activeSlot();
    }

    public function getStartsAtAttribute(): ?CarbonInterface
    {
        if (! $this->date) {
            return null;
        }

        return Carbon::parse($this->date->format('Y-m-d').' '.$this->start_time);
    }
}
