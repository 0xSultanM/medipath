<?php

namespace App\Enums;

enum AllergySeverity: string
{
    case Mild = 'mild';
    case Moderate = 'moderate';
    case Severe = 'severe';
    case LifeThreatening = 'life_threatening';

    public function label(): string
    {
        return (string) config("medipath.allergy_severities.{$this->value}.label", ucfirst(str_replace('_', ' ', $this->value)));
    }

    public function tone(): string
    {
        return (string) config("medipath.allergy_severities.{$this->value}.tone", 'neutral');
    }
}
