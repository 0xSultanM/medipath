<?php

namespace App\Enums;

enum InteractionSeverity: string
{
    case Minor = 'minor';
    case Moderate = 'moderate';
    case Major = 'major';
    case Contraindicated = 'contraindicated';

    public function label(): string
    {
        return (string) config("medipath.interaction_severities.{$this->value}.label", ucfirst($this->value));
    }

    public function tone(): string
    {
        return (string) config("medipath.interaction_severities.{$this->value}.tone", 'info');
    }

    public function blocksDispense(): bool
    {
        return in_array($this, [self::Major, self::Contraindicated], true);
    }
}
