<?php

namespace App\Enums;

enum ModerationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return (string) config("medipath.moderation_statuses.{$this->value}.label", ucfirst($this->value));
    }

    public function tone(): string
    {
        return (string) config("medipath.moderation_statuses.{$this->value}.tone", 'neutral');
    }
}
