<?php

namespace App\Enums;

enum PrescriptionStatus: string
{
    case Draft = 'draft';
    case PendingDispense = 'pending_dispense';
    case Dispensed = 'dispensed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return (string) config("medipath.prescription_statuses.{$this->value}.label", ucfirst($this->value));
    }

    public function tone(): string
    {
        return (string) config("medipath.prescription_statuses.{$this->value}.tone", 'neutral');
    }
}
