<?php

namespace App\Services;

use App\Enums\InventoryLedgerType;
use App\Enums\PaymentStatus;
use App\Enums\PrescriptionStatus;
use App\Models\InventoryLedger;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Medicine;
use App\Models\Pharmacist;
use App\Models\Prescription;
use App\Models\User;
use App\Support\ActivityActions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Pharmacist pre-dispense safety gate and the atomic dispense transaction
 * (PLAN Sprints 8.3/8.7/8.8). Re-runs the deterministic checks, locks every
 * medicine row, deducts stock, writes the append-only ledger, issues the
 * invoice, closes the prescription and notifies both parties. Any failure
 * rolls the whole transaction back.
 */
class PharmacyDispenseService
{
    public function __construct(
        private readonly PrescriptionSafetyService $safety,
        private readonly ActivityLogService $activity,
        private readonly NotificationService $notifications,
        private readonly CurrencyFormatter $money,
        private readonly SettingsService $settings,
    ) {}

    /** @return array<string, mixed> */
    public function quote(Prescription $prescription): array
    {
        $prescription->loadMissing(['patient.allergies', 'patient.medications', 'doctor.user', 'items.medicine', 'appointment', 'invoice']);

        $items = $prescription->items;
        $safety = $this->safety->evaluate(
            $prescription->patient,
            $items->map(fn ($item) => [
                'medicine_id' => $item->medicine_id,
                'quantity' => (int) $item->quantity,
            ])->all(),
        );

        return [
            'prescription' => $prescription,
            'items' => $items,
            'safety' => $safety,
            'consultation_fee' => (float) ($prescription->doctor?->fee ?? 0),
        ];
    }

    public function refuse(Prescription $prescription, string $reason, User $actor): void
    {
        DB::transaction(function () use ($prescription, $reason, $actor): void {
            /** @var Prescription $locked */
            $locked = Prescription::query()->whereKey($prescription->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PrescriptionStatus::PendingDispense) {
                throw ValidationException::withMessages([
                    'prescription' => 'Only a pending prescription can be refused.',
                ]);
            }

            $locked->forceFill(['status' => PrescriptionStatus::Cancelled])->save();

            $this->activity->record(ActivityActions::PRESCRIPTION_REFUSED, $locked, [
                'prescription_code' => $locked->prescription_code,
                'reason' => $reason,
            ], $actor);

            if ($locked->patient?->user) {
                $this->notifications->send(
                    $locked->patient->user,
                    'prescription.refused',
                    'Prescription returned by pharmacy',
                    "Prescription {$locked->prescription_code} was not dispensed. Please contact your doctor. Reason: {$reason}",
                    'prescription',
                    ['prescription_id' => $locked->id],
                    '/patient/prescriptions',
                );
            }

            if ($locked->doctor?->user) {
                $this->notifications->send(
                    $locked->doctor->user,
                    'prescription.refused',
                    'Pharmacy refused a prescription',
                    "{$locked->prescription_code} was refused at the pharmacy. Reason: {$reason}",
                    'prescription',
                    ['prescription_id' => $locked->id],
                    '/doctor/prescriptions',
                );
            }
        });
    }

    /**
     * @param array{
     *   lines: array<int, array{prescription_item_id:int, quantity:int}>,
     *   consultation_fee?: float|int|string|null,
     *   discount_kind?: string,
     *   discount_value?: float|int|string|null,
     *   payment_method: string,
     *   mark_paid?: bool,
     *   notes?: string|null,
     *   safety_ack: bool
     * } $data
     */
    public function dispense(Prescription $prescription, array $data, Pharmacist $pharmacist, User $actor): Invoice
    {
        $prescription->loadMissing(['patient', 'doctor.user', 'items.medicine']);

        if ($prescription->status !== PrescriptionStatus::PendingDispense) {
            throw ValidationException::withMessages([
                'prescription' => 'This prescription is no longer pending dispense.',
            ]);
        }

        if (! ($data['safety_ack'] ?? false)) {
            throw ValidationException::withMessages([
                'safety_ack' => 'Confirm the pharmacist verification before dispensing.',
            ]);
        }

        $quantityByItem = collect($data['lines'])
            ->keyBy(fn (array $line) => (int) $line['prescription_item_id'])
            ->map(fn (array $line) => (int) $line['quantity']);

        if ($quantityByItem->count() !== $prescription->items->count()) {
            throw ValidationException::withMessages([
                'lines' => 'Every prescription item must be dispensed (or the prescription refused).',
            ]);
        }

        foreach ($prescription->items as $item) {
            $qty = (int) ($quantityByItem[$item->id] ?? 0);
            if ($qty !== (int) $item->quantity) {
                throw ValidationException::withMessages([
                    'lines' => "Dispense the full prescribed quantity for {$item->medicine?->name}, or refuse the prescription.",
                ]);
            }
        }

        $safety = $this->safety->evaluate(
            $prescription->patient,
            $prescription->items->map(fn ($item) => [
                'medicine_id' => $item->medicine_id,
                'quantity' => (int) $quantityByItem[$item->id],
            ])->all(),
        );

        $override = filled($prescription->override_justification);

        if ($safety['expired']) {
            throw ValidationException::withMessages([
                'lines' => 'An expired batch cannot be dispensed. Restock or replace the batch first.',
            ]);
        }

        if ($safety['blocked'] && ! $override) {
            throw ValidationException::withMessages([
                'lines' => 'The deterministic safety gate is blocking this dispense. A sealed doctor override is required.',
            ]);
        }

        return DB::transaction(function () use ($prescription, $data, $pharmacist, $actor, $quantityByItem): Invoice {
            /** @var Prescription $locked */
            $locked = Prescription::query()->whereKey($prescription->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PrescriptionStatus::PendingDispense) {
                throw ValidationException::withMessages([
                    'prescription' => 'Another counter already dispensed this prescription.',
                ]);
            }

            $items = $locked->items()->with('medicine')->get();
            $medicines = Medicine::query()
                ->whereIn('id', $items->pluck('medicine_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $previous = [];
            $subtotal = 0.0;

            foreach ($items as $item) {
                /** @var Medicine $medicine */
                $medicine = $medicines->get($item->medicine_id);
                $qty = (int) $quantityByItem[$item->id];

                if (! $medicine || $medicine->stock_quantity < $qty) {
                    throw ValidationException::withMessages([
                        'lines' => "Stock changed during checkout: {$item->medicine?->name} no longer has {$qty} available.",
                    ]);
                }

                $previous[$medicine->id] = (int) $medicine->stock_quantity;
                $unit = (float) ($item->unit_price_snapshot ?: $medicine->unit_price);
                $subtotal += $unit * $qty;
            }

            $consultation = round((float) ($data['consultation_fee'] ?? 0), 2);
            if ($consultation > 0) {
                $subtotal += $consultation;
            }

            [$kind, $discount] = $this->discount($data, $subtotal);
            $net = round(max(0, $subtotal - $discount), 2);
            $markPaid = (bool) ($data['mark_paid'] ?? false);
            $code = $this->invoiceCode();

            $invoice = Invoice::query()->create([
                'invoice_code' => $code,
                'patient_id' => $locked->patient_id,
                'pharmacist_id' => $pharmacist->id,
                'prescription_id' => $locked->id,
                'subtotal' => $subtotal,
                'discount_kind' => $kind,
                'discount_value' => $kind === 'none' ? 0 : round((float) ($data['discount_value'] ?? 0), 2),
                'discount' => $discount,
                'net_total' => $net,
                'currency_code' => $this->settings->currencyCode(),
                'payment_method' => $data['payment_method'],
                'payment_status' => $markPaid ? PaymentStatus::Paid : PaymentStatus::Pending,
                'paid_amount' => $markPaid ? $net : 0,
                'paid_at' => $markPaid ? now() : null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                /** @var Medicine $medicine */
                $medicine = $medicines->get($item->medicine_id);
                $qty = (int) $quantityByItem[$item->id];
                $unit = (float) ($item->unit_price_snapshot ?: $medicine->unit_price);

                InvoiceItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'medicine_id' => $medicine->id,
                    'description' => trim($medicine->name.' '.($medicine->strength ?? '')),
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'line_total' => round($unit * $qty, 2),
                ]);

                $new = $previous[$medicine->id] - $qty;
                $medicine->forceFill(['stock_quantity' => $new])->save();

                InventoryLedger::query()->create([
                    'medicine_id' => $medicine->id,
                    'type' => InventoryLedgerType::Dispense,
                    'quantity_change' => -$qty,
                    'previous_qty' => $previous[$medicine->id],
                    'new_qty' => $new,
                    'actor_id' => $actor->id,
                    'reference' => $locked->prescription_code,
                    'notes' => "Dispensed against {$code}.",
                    'created_at' => now(),
                ]);

                $this->activity->record(ActivityActions::STOCK_DEDUCTED, $medicine, [
                    'medicine' => $medicine->name,
                    'quantity' => $qty,
                    'previous' => $previous[$medicine->id],
                    'new' => $new,
                    'invoice' => $code,
                ], $actor);
            }

            if ($consultation > 0) {
                InvoiceItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'medicine_id' => null,
                    'description' => 'Consultation — '.($locked->doctor?->user?->name ?? 'Clinic'),
                    'quantity' => 1,
                    'unit_price' => $consultation,
                    'line_total' => $consultation,
                ]);
            }

            $locked->forceFill([
                'status' => PrescriptionStatus::Dispensed,
                'dispensed_at' => now(),
                'dispensed_by' => $actor->id,
            ])->save();

            $this->activity->record(ActivityActions::PRESCRIPTION_DISPENSED, $locked, [
                'prescription_code' => $locked->prescription_code,
                'invoice_code' => $code,
                'net_total' => $net,
            ], $actor);

            $this->activity->record(ActivityActions::INVOICE_CREATED, $invoice, [
                'invoice_code' => $code,
                'net_total' => $net,
                'payment_status' => $invoice->payment_status?->value,
            ], $actor);

            if ($locked->patient?->user) {
                $this->notifications->send(
                    $locked->patient->user,
                    'prescription.dispensed',
                    'Prescription dispensed',
                    "{$locked->prescription_code} was dispensed. Invoice {$code} totals {$this->money->format($net)}.",
                    'billing',
                    ['invoice_id' => $invoice->id, 'prescription_id' => $locked->id],
                    '/patient/bills',
                );
            }

            if ($locked->doctor?->user) {
                $this->notifications->send(
                    $locked->doctor->user,
                    'prescription.dispensed',
                    'Prescription dispensed',
                    "{$locked->prescription_code} was dispensed and billed as {$code}.",
                    'prescription',
                    ['prescription_id' => $locked->id],
                    '/doctor/prescriptions',
                );
            }

            return $invoice->fresh(['items.medicine', 'patient', 'prescription']);
        });
    }

    /** @param array<string, mixed> $data @return array{0:string,1:float} */
    private function discount(array $data, float $subtotal): array
    {
        $kind = (string) ($data['discount_kind'] ?? 'none');
        $value = (float) ($data['discount_value'] ?? 0);

        if ($kind === 'percent') {
            return ['percent', round($subtotal * min(100, max(0, $value)) / 100, 2)];
        }

        if ($kind === 'fixed') {
            return ['fixed', round(min($subtotal, max(0, $value)), 2)];
        }

        return ['none', 0.0];
    }

    private function invoiceCode(): string
    {
        do {
            $code = 'INV-'.now()->format('Y').'-'.Str::upper(Str::random(5));
        } while (Invoice::query()->where('invoice_code', $code)->exists());

        return $code;
    }
}