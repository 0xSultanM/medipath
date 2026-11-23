<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case NoShow = 'no_show';

    public function label(): string
    {
        return (string) config("medipath.appointment_statuses.{$this->value}.label", ucfirst($this->value));
    }

    public function tone(): string
    {
        return (string) config("medipath.appointment_statuses.{$this->value}.tone", 'neutral');
    }

    /** Drives unique(doctor_id, date, active_slot_key) — the concurrency guard. */
    public function holdsSlot(): bool
    {
        return in_array($this, [self::Pending, self::Accepted], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Rejected, self::Cancelled, self::Completed, self::NoShow], true);
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
