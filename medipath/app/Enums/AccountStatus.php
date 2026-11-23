<?php

namespace App\Enums;

enum AccountStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function label(): string
    {
        return (string) config("medipath.account_statuses.{$this->value}.label", ucfirst($this->value));
    }

    public function tone(): string
    {
        return (string) config("medipath.account_statuses.{$this->value}.tone", 'neutral');
    }

    public function canAuthenticate(): bool
    {
        return $this === self::Approved;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
