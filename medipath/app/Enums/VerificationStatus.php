<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case Unverified = 'unverified';
    case Pending = 'pending';
    case Verified = 'verified';
    case Resubmission = 'resubmission';
    case Revoked = 'revoked';

    public function label(): string
    {
        return (string) config("medipath.verification_statuses.{$this->value}.label", ucfirst($this->value));
    }

    public function tone(): string
    {
        return (string) config("medipath.verification_statuses.{$this->value}.tone", 'neutral');
    }

    public function isPublic(): bool
    {
        return $this === self::Verified;
    }
}
