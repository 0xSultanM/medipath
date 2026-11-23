<?php

namespace App\Enums;

/**
 * The five-role model from PLAN §1 (guest is unauthenticated, not persisted).
 * Persisted in users.role and never hardcoded in UI (G6).
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Doctor = 'doctor';
    case Pharmacist = 'pharmacist';
    case Patient = 'patient';

    public function label(): string
    {
        return (string) config("medipath.roles.{$this->value}.label", ucfirst($this->value));
    }

    public function portal(): string
    {
        return (string) config("medipath.roles.{$this->value}.portal", 'Workspace');
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isClinical(): bool
    {
        return in_array($this, [self::Doctor, self::Pharmacist], true);
    }
}
