<?php

namespace App\Http\Controllers\Pharmacist;

use App\Enums\PrescriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Medicine;
use App\Models\Pharmacist;
use App\Models\Prescription;
use App\Services\PharmacyDispenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Pending queue, pre-dispense safety gate and dispense hand-off (Sprints 8.3/8.7/8.8). */
class PrescriptionController extends Controller
{
    public function __construct(private readonly PharmacyDispenseService $dispense) {}

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $stock = (string) $request->query('stock', 'any');
        $sort = $request->query('sort') === 'newest' ? 'newest' : 'oldest';

        $rows = Prescription::query()
            ->with(['patient:id,patient_code,name', 'doctor.user:id,name', 'items.medicine'])
            ->where('status', PrescriptionStatus::PendingDispense)
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                $query->where(fn ($inner) => $inner->where('prescription_code', 'like', $like)
                    ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', $like)->orWhere('patient_code', 'like', $like)));
            })
            ->orderBy('created_at', $sort === 'newest' ? 'desc' : 'asc')
            ->get()
            ->map(fn (Prescription $rx): array => [
                'id' => $rx->id,
                'code' => $rx->prescription_code,
                'patientName' => $rx->patient?->name,
                'patientCode' => $rx->patient?->patient_code,
                'doctorName' => $rx->doctor?->user?->name,
                'items' => $rx->items->count(),
                'itemNames' => $rx->items->map(fn ($item) => trim(($item->medicine?->name ?? 'Medicine').' '.($item->medicine?->strength ?? '')))->values(),
                'waitingHours' => abs((int) now()->diffInHours($rx->created_at)),
                'hasOverride' => $rx->hasOverride(),
                'stockState' => $this->stockState($rx),
                'createdAt' => $rx->created_at?->toIso8601String(),
            ])
            ->when(in_array($stock, ['partial', 'out_of_stock', 'all_in_stock'], true), fn ($collection) => $collection->where('stockState', $stock))
            ->values();

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 15;
        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        $oldest = Prescription::query()->where('status', PrescriptionStatus::PendingDispense)->min('created_at');

        return Inertia::render('Pharmacist/Prescriptions', [
            'prescriptions' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => ['q' => $q, 'stock' => $stock, 'sort' => $sort],
            'counts' => [
                'pending' => Prescription::query()->where('status', PrescriptionStatus::PendingDispense)->count(),
                'oldestHours' => $oldest ? abs((int) now()->diffInHours($oldest)) : null,
            ],
            'dataState' => $this->state($request) ?? ($rows->isEmpty() ? 'empty' : 'success'),
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function show(Request $request, Prescription $prescription): Response
    {
        $prescription->load(['patient.allergies', 'patient.medications', 'doctor.user', 'items.medicine', 'appointment', 'invoice']);
        $quote = $this->dispense->quote($prescription);
        $safety = $quote['safety'];
        $patient = $prescription->patient;

        $canDispense = $prescription->status === PrescriptionStatus::PendingDispense
            && ! $safety['expired']
            && (! $safety['blocked'] || $prescription->hasOverride());

        return Inertia::render('Pharmacist/SafetyGate', [
            'prescription' => [
                'id' => $prescription->id,
                'code' => $prescription->prescription_code,
                'status' => $prescription->status?->value,
                'statusLabel' => $prescription->status?->label(),
                'issuedAt' => $prescription->created_at?->toIso8601String(),
                'pharmacyNotes' => $prescription->pharmacy_notes,
                'patientNotes' => $prescription->patient_notes,
                'aiSummary' => $prescription->ai_safety_summary,
                'aiStatus' => $prescription->ai_status,
                'overrideJustification' => $prescription->override_justification,
                'hasOverride' => $prescription->hasOverride(),
            ],
            'patient' => [
                'id' => $patient->id,
                'name' => $patient->name,
                'code' => $patient->patient_code,
                'initials' => $patient->initials,
                'age' => $patient->age,
                'gender' => $patient->gender,
                'condition' => $patient->condition,
                'allergies' => $patient->allergies->map(fn ($allergy): array => [
                    'id' => $allergy->id,
                    'substance' => $allergy->substance,
                    'severity' => $allergy->severity?->value,
                    'severityLabel' => $allergy->severity?->label(),
                    'notes' => $allergy->reaction_notes,
                ])->values(),
            ],
            'doctor' => [
                'name' => $prescription->doctor?->user?->name,
                'fee' => (string) ($prescription->doctor?->fee ?? ''),
            ],
            'items' => $prescription->items->map(fn ($item): array => [
                'id' => $item->id,
                'medicineId' => $item->medicine_id,
                'name' => trim(($item->medicine?->name ?? 'Medicine').' '.($item->medicine?->strength ?? '')),
                'dosage' => $item->dosage,
                'schedule' => $item->schedule,
                'duration' => $item->duration,
                'quantity' => (int) $item->quantity,
                'stock' => (int) ($item->medicine?->stock_quantity ?? 0),
                'batch' => $item->medicine?->batch_number,
                'expiryDate' => $item->medicine?->expiry_date?->toDateString(),
                'stockStatus' => $this->lineStockStatus($item->medicine, (int) $item->quantity),
            ])->values(),
            'safety' => $safety,
            'canDispense' => $canDispense,
            'dispenseUrl' => '/pharmacist/prescriptions/'.$prescription->id.'/dispense',
            'dataState' => $this->state($request) ?? 'success',
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function dispensePage(Request $request, Prescription $prescription): Response|RedirectResponse
    {
        $prescription->load(['patient', 'doctor.user', 'items.medicine', 'invoice.items.medicine', 'appointment']);

        if ($prescription->status === PrescriptionStatus::Cancelled) {
            return redirect()->route('pharmacist.prescriptions.index')
                ->with('error', 'This prescription was refused and cannot be dispensed.');
        }

        if ($prescription->invoice) {
            $invoice = $prescription->invoice;

            return Inertia::render('Pharmacist/Dispense', [
                'prescription' => [
                    'id' => $prescription->id,
                    'code' => $prescription->prescription_code,
                ],
                'patient' => [
                    'name' => $prescription->patient?->name,
                    'code' => $prescription->patient?->patient_code,
                    'initials' => $prescription->patient?->initials,
                ],
                'lines' => [],
                'receipt' => [
                    'code' => $invoice->invoice_code,
                    'netFormatted' => app(\App\Services\CurrencyFormatter::class)->format((float) $invoice->net_total, $invoice->currency_code),
                    'method' => (string) config('medipath.payment_methods.'.$invoice->payment_method, $invoice->payment_method),
                    'status' => $invoice->payment_status?->value,
                    'statusLabel' => $invoice->payment_status?->label(),
                    'items' => $invoice->items->map(fn ($item): array => [
                        'description' => $item->description,
                        'quantity' => (int) $item->quantity,
                        'lineFormatted' => app(\App\Services\CurrencyFormatter::class)->format((float) $item->line_total, $invoice->currency_code),
                    ])->values(),
                    'pdfUrl' => route('pharmacist.invoices.pdf', $invoice),
                ],
                'paymentMethods' => collect(config('medipath.payment_methods'))->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
                'dataState' => 'success',
                'lastKnownAt' => now()->toIso8601String(),
            ]);
        }

        abort_unless($prescription->status === PrescriptionStatus::PendingDispense, 404);

        $quote = $this->dispense->quote($prescription);
        $money = app(\App\Services\CurrencyFormatter::class);

        return Inertia::render('Pharmacist/Dispense', [
            'prescription' => [
                'id' => $prescription->id,
                'code' => $prescription->prescription_code,
                'statusLabel' => $prescription->status?->label(),
            ],
            'patient' => [
                'name' => $prescription->patient?->name,
                'code' => $prescription->patient?->patient_code,
                'initials' => $prescription->patient?->initials,
            ],
            'lines' => $prescription->items->map(fn ($item): array => [
                'id' => $item->id,
                'name' => trim(($item->medicine?->name ?? 'Medicine').' '.($item->medicine?->strength ?? '')),
                'batch' => $item->medicine?->batch_number,
                'expiryDate' => $item->medicine?->expiry_date?->toDateString(),
                'stock' => (int) ($item->medicine?->stock_quantity ?? 0),
                'prescribed' => (int) $item->quantity,
                'unitFormatted' => $money->format((float) ($item->unit_price_snapshot ?: $item->medicine?->unit_price)),
                'unitPrice' => (float) ($item->unit_price_snapshot ?: $item->medicine?->unit_price),
                'stockStatus' => $this->lineStockStatus($item->medicine, (int) $item->quantity),
            ])->values(),
            'receipt' => null,
            'consultationFee' => (string) ($quote['consultation_fee'] ?? 0),
            'currencyCode' => app(\App\Services\SettingsService::class)->currencyCode(),
            'paymentMethods' => collect(config('medipath.payment_methods'))->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'safety' => $quote['safety'],
            'dataState' => $this->state($request) ?? 'success',
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function quoteTotals(Request $request, Prescription $prescription): JsonResponse
    {
        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.prescription_item_id' => ['required', 'integer', 'distinct'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'consultation_fee' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'discount_kind' => ['required', Rule::in(['none', 'fixed', 'percent'])],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);

        $prescription->loadMissing('items.medicine');
        $money = app(\App\Services\CurrencyFormatter::class);
        $quantities = collect($validated['lines'])->keyBy('prescription_item_id');
        $subtotal = 0.0;
        $lines = [];

        foreach ($prescription->items as $item) {
            $qty = (int) ($quantities[$item->id]['quantity'] ?? $item->quantity);
            $unit = (float) ($item->unit_price_snapshot ?: $item->medicine?->unit_price);
            $line = $unit * $qty;
            $subtotal += $line;
            $stock = (int) ($item->medicine?->stock_quantity ?? 0);
            $lines[] = [
                'id' => $item->id,
                'lineFormatted' => $money->format($line),
                'available' => $stock,
                'insufficient' => $qty > $stock,
                'after' => $stock - $qty,
            ];
        }

        $consultation = round((float) ($validated['consultation_fee'] ?? 0), 2);
        $subtotal += $consultation;
        $kind = $validated['discount_kind'];
        $value = (float) ($validated['discount_value'] ?? 0);
        $discount = $kind === 'percent'
            ? round($subtotal * min(100, max(0, $value)) / 100, 2)
            : ($kind === 'fixed' ? round(min($subtotal, max(0, $value)), 2) : 0.0);
        $net = round(max(0, $subtotal - $discount), 2);

        return response()->json([
            'subtotalFormatted' => $money->format($subtotal),
            'discountFormatted' => $money->format($discount),
            'netFormatted' => $money->format($net),
            'currencyCode' => app(\App\Services\SettingsService::class)->currencyCode(),
            'lines' => $lines,
        ])->header('Cache-Control', 'no-store');
    }

    public function dispense(Request $request, Prescription $prescription): RedirectResponse
    {
        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.prescription_item_id' => ['required', 'integer', 'distinct'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'consultation_fee' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'discount_kind' => ['required', Rule::in(['none', 'fixed', 'percent'])],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'payment_method' => ['required', Rule::in(array_keys(config('medipath.payment_methods')))],
            'mark_paid' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'safety_ack' => ['accepted'],
        ]);

        $invoice = $this->dispense->dispense($prescription, $validated, $this->pharmacist($request), $request->user());

        return redirect()->route('pharmacist.prescriptions.dispense', $prescription)
            ->with('success', "Dispensed and billed as {$invoice->invoice_code}.");
    }

    public function refuse(Request $request, Prescription $prescription): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $this->dispense->refuse($prescription, $validated['reason'], $request->user());

        return redirect()->route('pharmacist.prescriptions.index')
            ->with('success', "Prescription {$prescription->prescription_code} returned to the doctor.");
    }

    private function pharmacist(Request $request): Pharmacist
    {
        $pharmacist = $request->user()->pharmacist;
        abort_unless($pharmacist, 403, 'No pharmacist profile is linked to this account.');

        return $pharmacist;
    }

    private function stockState(Prescription $prescription): string
    {
        $out = $prescription->items->contains(fn ($item) => (int) ($item->medicine?->stock_quantity ?? 0) <= 0);
        if ($out) {
            return 'out_of_stock';
        }

        return $prescription->items->contains(fn ($item) => (int) ($item->medicine?->stock_quantity ?? 0) < (int) $item->quantity)
            ? 'partial'
            : 'all_in_stock';
    }

    private function lineStockStatus(?Medicine $medicine, int $quantity): string
    {
        $stock = (int) ($medicine?->stock_quantity ?? 0);

        if ($stock < $quantity) {
            return 'out_of_stock';
        }

        $after = $stock - $quantity;

        return match (true) {
            $after <= 0 => 'out_of_stock',
            $after <= (int) ($medicine?->min_stock_threshold ?? 0) => 'low',
            $after <= (int) ($medicine?->min_stock_threshold ?? 0) * 2 => 'critical',
            default => 'healthy',
        };
    }

    private function state(Request $request): ?string
    {
        $state = (string) $request->query('state', '');

        return in_array($state, ['success', 'skeleton', 'empty', 'error'], true) ? $state : null;
    }
}