<?php

namespace App\Models;

use App\Enums\InteractionSeverity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrugInteraction extends Model
{
    protected $fillable = [
        'medicine_a_id', 'medicine_b_id', 'severity', 'description', 'management',
    ];

    protected function casts(): array
    {
        return ['severity' => InteractionSeverity::class];
    }

    public function medicineA(): BelongsTo
    {
        return $this->belongsTo(Medicine::class, 'medicine_a_id');
    }

    public function medicineB(): BelongsTo
    {
        return $this->belongsTo(Medicine::class, 'medicine_b_id');
    }

    /** Canonical ordering so a pair is stored exactly once. */
    public static function canonicalPair(int $a, int $b): array
    {
        return $a < $b ? [$a, $b] : [$b, $a];
    }
}
