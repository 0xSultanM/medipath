<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Refunded = 'refunded';

    public function label(): string
    {
        return (string) config("medipath.payment_statuses.{$this->value}.label", ucfirst($this->value));
    }

    public function tone(): string
    {
        return (string) config("medipath.payment_statuses.{$this->value}.tone", 'neutral');
    }
}
