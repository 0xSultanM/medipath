<?php

namespace App\Models;

use App\Enums\InventoryLedgerType;
use App\Exceptions\ImmutableRecordException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLedger extends Model
{
    public $timestamps = false;

    protected $table = 'inventory_ledger';

    protected $fillable = [
        'medicine_id', 'type', 'quantity_change', 'previous_qty',
        'new_qty', 'actor_id', 'reference', 'notes', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => InventoryLedgerType::class,
            'quantity_change' => 'integer',
            'previous_qty' => 'integer',
            'new_qty' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw ImmutableRecordException::forModel(self::class, 'row'));
        static::deleting(fn () => throw ImmutableRecordException::forModel(self::class, 'row'));
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
