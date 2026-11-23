<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Medicine extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'generic_name', 'category', 'strength', 'form', 'manufacturer',
        'unit_price', 'stock_quantity', 'min_stock_threshold', 'expiry_date',
        'batch_number', 'side_effects', 'contraindications', 'description',
        'requires_prescription', 'is_active', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'min_stock_threshold' => 'integer',
            'side_effects' => 'array',
            'expiry_date' => 'date',
            'requires_prescription' => 'boolean',
            'is_active' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function prescriptionItems(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(InventoryLedger::class);
    }

    public function getStockStatusAttribute(): string
    {
        if ($this->stock_quantity <= 0) {
            return 'out_of_stock';
        }
        if ($this->stock_quantity <= $this->min_stock_threshold) {
            return 'critical';
        }
        if ($this->stock_quantity <= $this->min_stock_threshold * 2) {
            return 'low';
        }

        return 'healthy';
    }

    public function getExpiryStatusAttribute(): string
    {
        if (! $this->expiry_date) {
            return 'ok';
        }

        $days = now()->startOfDay()->diffInDays($this->expiry_date, false);

        return match (true) {
            $days < 0 => 'expired',
            $days <= 30 => 'critical',
            $days <= 90 => 'upcoming',
            default => 'ok',
        };
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('archived_at');
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock_quantity', '<=', 'min_stock_threshold');
    }
}
