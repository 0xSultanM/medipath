<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SymptomSpecialtyMap extends Model
{
    protected $table = 'symptom_specialty_map';

    protected $fillable = ['symptom_category', 'keywords', 'specialty_id', 'priority', 'is_active'];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }
}
