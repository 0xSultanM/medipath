<?php

namespace App\Services;

use App\Enums\InventoryLedgerType;
use App\Models\InventoryLedger;
use App\Models\Medicine;
use App\Models\User;
use App\Support\ActivityActions;
use Illuminate\Support\Facades\DB;

/**
 * Pharmacist inventory workflow (PLAN Sprints 8.4/8.5).
 * Stock is never silently rewritten: every movement writes an append-only
 * InventoryLedger row inside the same transaction as the balance change.
 */
class InventoryService
{
    public function __construct(private readonly ActivityLogService $activity) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): Medicine
    {
        return DB::transaction(function () use ($actor, $data): Medicine {
            $opening = (int) ($data['stock_quantity'] ?? 0);

            $medicine = Medicine::query()->create([
                'name' => $data['name'],
                'generic_name' => $data['generic_name'] ?? null,
                'category' => $data['category'],
                'strength' => $data['strength'] ?? null,
                'form' => $data['form'] ?? 'tablet',
                'manufacturer' => $data['manufacturer'] ?? null,
                'unit_price' => $data['unit_price'],
                'stock_quantity' => $opening,
                'min_stock_threshold' => (int) ($data['min_stock_threshold'] ?? 10),
                'expiry_date' => $data['expiry_date'] ?? null,
                'batch_number' => $data['batch_number'] ?? null,
                'side_effects' => $data['side_effects'] ?? null,
                'contraindications' => $data['contraindications'] ?? null,
                'description' => $data['description'] ?? null,
                'requires_prescription' => (bool) ($data['requires_prescription'] ?? true),
                'is_active' => true,
            ]);

            if ($opening > 0) {
                $this->ledger($medicine, InventoryLedgerType::Restock, $opening, 0, $opening, $actor, 'OPENING', 'Opening stock');
            }

            $this->activity->record(ActivityActions::MEDICINE_CREATED, $medicine, [
                'name' => $medicine->name,
                'opening_stock' => $opening,
            ], $actor);

            return $medicine;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, Medicine $medicine, array $data): Medicine
    {
        return DB::transaction(function () use ($actor, $medicine, $data): Medicine {
            $medicine->fill([
                'name' => $data['name'],
                'generic_name' => $data['generic_name'] ?? null,
                'category' => $data['category'],
                'strength' => $data['strength'] ?? null,
                'form' => $data['form'] ?? $medicine->form,
                'manufacturer' => $data['manufacturer'] ?? null,
                'unit_price' => $data['unit_price'],
                'min_stock_threshold' => (int) ($data['min_stock_threshold'] ?? $medicine->min_stock_threshold),
                'expiry_date' => $data['expiry_date'] ?? $medicine->expiry_date,
                'batch_number' => $data['batch_number'] ?? $medicine->batch_number,
                'side_effects' => $data['side_effects'] ?? $medicine->side_effects,
                'contraindications' => $data['contraindications'] ?? null,
                'description' => $data['description'] ?? null,
                'requires_prescription' => (bool) ($data['requires_prescription'] ?? $medicine->requires_prescription),
                'is_active' => $medicine->is_active,
            ]);
            $medicine->save();

            $this->activity->record(ActivityActions::MEDICINE_UPDATED, $medicine, [
                'fields' => array_keys($data),
            ], $actor);

            return $medicine->fresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function restock(User $actor, Medicine $medicine, array $data): Medicine
    {
        return DB::transaction(function () use ($actor, $medicine, $data): Medicine {
            /** @var Medicine $locked */
            $locked = Medicine::query()->whereKey($medicine->id)->lockForUpdate()->firstOrFail();
            $quantity = (int) $data['quantity'];
            $previous = (int) $locked->stock_quantity;
            $new = $previous + $quantity;

            $locked->forceFill([
                'stock_quantity' => $new,
                'batch_number' => $data['batch_number'] ?? $locked->batch_number,
                'expiry_date' => $data['expiry_date'] ?? $locked->expiry_date,
                'unit_price' => $data['unit_price'] ?? $locked->unit_price,
                'is_active' => true,
                'archived_at' => null,
            ])->save();

            $this->ledger(
                $locked,
                InventoryLedgerType::Restock,
                $quantity,
                $previous,
                $new,
                $actor,
                $data['reference'] ?? 'RESTOCK',
                $data['notes'] ?? null,
            );

            $this->activity->record(ActivityActions::STOCK_RESTOCKED, $locked, [
                'medicine' => $locked->name,
                'quantity' => $quantity,
                'previous' => $previous,
                'new' => $new,
            ], $actor);

            return $locked->fresh();
        });
    }

    public function archive(User $actor, Medicine $medicine): void
    {
        DB::transaction(function () use ($actor, $medicine): void {
            $medicine->forceFill([
                'is_active' => false,
                'archived_at' => now(),
            ])->save();
            $medicine->delete();

            $this->activity->record(ActivityActions::MEDICINE_ARCHIVED, $medicine, [
                'name' => $medicine->name,
            ], $actor);
        });
    }

    private function ledger(
        Medicine $medicine,
        InventoryLedgerType $type,
        int $change,
        int $previous,
        int $new,
        User $actor,
        ?string $reference,
        ?string $notes,
    ): void {
        InventoryLedger::query()->create([
            'medicine_id' => $medicine->id,
            'type' => $type,
            'quantity_change' => $change,
            'previous_qty' => $previous,
            'new_qty' => $new,
            'actor_id' => $actor->id,
            'reference' => $reference,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }
}