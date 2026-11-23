<?php

namespace App\Http\Controllers\Pharmacist;

use App\Enums\PaymentStatus;
use App\Enums\PrescriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Prescription;
use App\Services\CurrencyFormatter;
use App\Services\PrescriptionSafetyService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Pharmacy dashboard, alert tiers and the derived six-stage tracker (Sprints 8.2/8.6/8.10). */
class WorkspaceController extends Controller
{
    public function __construct(
        private readonly CurrencyFormatter $money,
        private readonly PrescriptionSafetyService $safety,
    ) {}

    public function dashboard(Request $request): Response
    {
        $pending = Prescription::query()
            ->with(['patient:id,patient_code,name', 'doctor.user:id,name', 'items.medicine'])
            ->where('status', PrescriptionStatus::PendingDispense)
            ->orderBy('created_at')
            ->limit(5)
            ->get();

        $pendingCount = Prescription::query()->where('status', PrescriptionStatus::PendingDispense)->count();
        $oldest = Prescription::query()->where('status', PrescriptionStatus::PendingDispense)->min('created_at');
        $lowStock = Medicine::query()->active()->whereColumn('stock_quantity', '<=', 'min_stock_threshold')->count();
        $outOfStock = Medicine::query()->active()->where('stock_quantity', '<=', 0)->count();
        $billedToday = Invoice::query()->whereDate('created_at', now()->toDateString());
        $dispensedToday = Prescription::query()->whereDate('dispensed_at', now()->toDateString())->count();

        $stock = Medicine::query()
            ->active()
            ->where(fn ($query) => $query
                ->whereColumn('stock_quantity', '<=', 'min_stock_threshold')
                ->orWhere(fn ($inner) => $inner->whereNotNull('expiry_date')->whereDate('expiry_date', '<=', now()->addDays(90)->toDateString())))
            ->orderBy('stock_quantity')
            ->orderBy('expiry_date')
            ->limit(6)
            ->get();

        $forced = $this->state($request);
        $state = $forced ?? ($pendingCount === 0 && $stock->isEmpty() ? 'empty' : 'success');

        return Inertia::render('Pharmacist/Dashboard', [
            'kpis' => [
                'pending' => $pendingCount,
                'oldestHours' => $oldest ? abs((int) now()->diffInHours($oldest)) : null,
                'lowStock' => $lowStock,
                'outOfStock' => $outOfStock,
                'billedTodayFormatted' => $this->money->format((float) (clone $billedToday)->sum('net_total')),
                'invoicesToday' => (clone $billedToday)->count(),
                'dispensedToday' => $dispensedToday,
            ],
            'queue' => $pending->map(fn (Prescription $rx): array => [
                'id' => $rx->id,
                'code' => $rx->prescription_code,
                'patientName' => $rx->patient?->name,
                'patientCode' => $rx->patient?->patient_code,
                'doctorName' => $rx->doctor?->user?->name,
                'items' => $rx->items->count(),
                'waitingHours' => abs((int) now()->diffInHours($rx->created_at)),
                'hasOverride' => $rx->hasOverride(),
                'stockState' => $this->stockState($rx),
            ])->values(),
            'stock' => $stock->map(fn (Medicine $medicine): array => [
                'id' => $medicine->id,
                'name' => trim($medicine->name.' '.($medicine->strength ?? '')),
                'stock' => (int) $medicine->stock_quantity,
                'threshold' => (int) $medicine->min_stock_threshold,
                'status' => $medicine->stock_status,
                'expiryStatus' => $medicine->expiry_status,
                'expiryDate' => $medicine->expiry_date?->toDateString(),
            ])->values(),
            'dataState' => $state,
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function alerts(Request $request): Response
    {
        $medicines = Medicine::query()->active()->orderBy('name')->get();

        $stock = $medicines->filter(fn (Medicine $m) => $m->stock_status !== 'healthy')
            ->sortBy('stock_quantity')->values();
        $expired = $medicines->filter(fn (Medicine $m) => $m->expiry_status === 'expired')
            ->sortBy('expiry_date')->values();
        $critical = $medicines->filter(fn (Medicine $m) => $m->expiry_status === 'critical')
            ->sortBy('expiry_date')->values();
        $upcoming = $medicines->filter(fn (Medicine $m) => $m->expiry_status === 'upcoming')
            ->sortBy('expiry_date')->values();

        $map = fn ($rows) => $rows->map(fn (Medicine $medicine): array => [
            'id' => $medicine->id,
            'name' => trim($medicine->name.' '.($medicine->strength ?? '')),
            'batch' => $medicine->batch_number,
            'stock' => (int) $medicine->stock_quantity,
            'threshold' => (int) $medicine->min_stock_threshold,
            'status' => $medicine->stock_status,
            'expiryStatus' => $medicine->expiry_status,
            'expiryDate' => $medicine->expiry_date?->toDateString(),
            'valueFormatted' => $this->money->format((float) $medicine->stock_quantity * (float) $medicine->unit_price),
        ])->values();

        return Inertia::render('Pharmacist/Alerts', [
            'tiers' => [
                'stock' => $map($stock),
                'expired' => $map($expired),
                'critical' => $map($critical),
                'upcoming' => $map($upcoming),
            ],
            'counts' => [
                'stock' => $stock->count(),
                'expired' => $expired->count(),
                'critical' => $critical->count(),
                'upcoming' => $upcoming->count(),
            ],
            'dataState' => $this->state($request) ?? ($stock->isEmpty() && $expired->isEmpty() && $critical->isEmpty() ? 'empty' : 'success'),
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function workflow(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $patientId = (int) $request->query('patient', 0);

        $matches = collect();
        $patient = null;

        if ($patientId > 0) {
            $patient = Patient::query()->find($patientId);
        } elseif ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $matches = Patient::query()
                ->where(fn ($query) => $query->where('name', 'like', $like)
                    ->orWhere('patient_code', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('walk_in_code', 'like', $like))
                ->orderBy('name')
                ->limit(8)
                ->get();

            if ($matches->count() === 1) {
                $patient = $matches->first();
            }
        }

        $stages = [];
        $prescription = null;
        $invoice = null;

        if ($patient) {
            $prescription = Prescription::query()
                ->with(['items.medicine', 'doctor.user'])
                ->where('patient_id', $patient->id)
                ->latest()
                ->first();
            $invoice = $prescription
                ? Invoice::query()->where('prescription_id', $prescription->id)->latest()->first()
                : Invoice::query()->where('patient_id', $patient->id)->latest()->first();

            $stockState = $prescription ? $this->stockState($prescription) : null;
            $safety = null;
            if ($prescription && $prescription->status === PrescriptionStatus::PendingDispense) {
                $safety = $this->safety->evaluate(
                    $patient,
                    $prescription->items->map(fn ($item) => [
                        'medicine_id' => $item->medicine_id,
                        'quantity' => (int) $item->quantity,
                    ])->all(),
                );
            }

            $dispensed = $prescription?->status === PrescriptionStatus::Dispensed;
            $billed = $invoice !== null;
            $paid = $invoice?->payment_status === PaymentStatus::Paid;

            $done = [true, $prescription !== null, $dispensed || ($safety !== null && ! $safety['blocked']), $dispensed, $billed, $paid];

            $stages = [
                $this->stage('registration', 'Registration', $done[0], $patient->created_at?->format('d M · H:i').' · '.$patient->patient_code, 'Patient record created'.($patient->walk_in_code ? ' · walk-in code '.$patient->walk_in_code : ''), null),
                $this->stage('prescription', 'Prescription', $done[1], $prescription ? $prescription->prescription_code.' · '.($prescription->doctor?->user?->name ?? '—') : 'No prescription yet', $prescription ? $prescription->items->count().' item(s) · '.($prescription->hasOverride() ? 'sealed override' : 'no override') : 'The doctor has not issued a prescription.', $prescription ? '/pharmacist/prescriptions/'.$prescription->id : null, 'Review gate'),
                $this->stage('stock', 'Stock check', $done[2], $safety ? ($safety['blocked'] ? 'Blocked — resolve before dispensing' : 'All items available') : ($stockState === 'all_in_stock' ? 'All items available' : 'Awaiting stock check'), $prescription ? $prescription->items->map(fn ($item) => ($item->medicine?->name ?? 'Medicine').' '.($item->medicine?->strength ?? ''))->join(' · ') : 'No stock context.', $prescription ? '/pharmacist/prescriptions/'.$prescription->id : null, 'Open gate'),
                $this->stage('dispense', 'Dispense', $done[3], $dispensed ? 'Dispensed '.$prescription->dispensed_at?->format('d M · H:i') : 'Pending at counter', $dispensed ? 'Stock deducted and ledger written atomically.' : 'Safety gate must pass before dispensing.', $prescription && ! $dispensed ? '/pharmacist/prescriptions/'.$prescription->id.'/dispense' : null, 'Dispense & bill'),
                $this->stage('billing', 'Billing', $done[4], $invoice ? $invoice->invoice_code.' · '.$this->money->format((float) $invoice->net_total, $invoice->currency_code) : 'No invoice yet', $invoice ? 'Payment status: '.$invoice->payment_status?->label() : 'Invoice is created with the dispense transaction.', $invoice ? '/pharmacist/invoices?q='.$invoice->invoice_code : null, 'Open invoices'),
                $this->stage('discharged', 'Discharged', $done[5], $paid ? 'Paid · journey complete' : ($billed ? 'Awaiting payment' : 'After payment'), $paid ? 'Patient may leave with the invoice and counselling summary.' : 'Discharge completes when the invoice is paid.', $invoice ? '/pharmacist/invoices?q='.$invoice->invoice_code : null, 'Review invoice'),
            ];
        }

        return Inertia::render('Pharmacist/Workflow', [
            'query' => $q,
            'matches' => $matches->map(fn (Patient $row): array => [
                'id' => $row->id,
                'name' => $row->name,
                'code' => $row->patient_code,
                'phone' => $row->phone,
            ])->values(),
            'patient' => $patient ? [
                'id' => $patient->id,
                'name' => $patient->name,
                'code' => $patient->patient_code,
                'initials' => $patient->initials,
                'phone' => $patient->phone,
                'age' => $patient->age,
                'gender' => $patient->gender,
                'condition' => $patient->condition,
                'walkInCode' => $patient->walk_in_code,
            ] : null,
            'prescription' => $prescription ? [
                'id' => $prescription->id,
                'code' => $prescription->prescription_code,
                'status' => $prescription->status?->value,
                'statusLabel' => $prescription->status?->label(),
                'hasOverride' => $prescription->hasOverride(),
            ] : null,
            'invoice' => $invoice ? [
                'id' => $invoice->id,
                'code' => $invoice->invoice_code,
                'netFormatted' => $this->money->format((float) $invoice->net_total, $invoice->currency_code),
                'status' => $invoice->payment_status?->value,
                'statusLabel' => $invoice->payment_status?->label(),
                'pdfUrl' => route('pharmacist.invoices.pdf', $invoice),
            ] : null,
            'stages' => $stages,
            'stockBlocked' => (bool) ($safety['blocked'] ?? false),
            'dataState' => $this->state($request) ?? ($patient ? 'success' : ($q === '' ? 'empty' : 'empty')),
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    /** @return array<string, mixed> */
    private function stage(string $key, string $label, bool $done, string $sub, string $detail, ?string $href, ?string $action = null): array
    {
        return compact('key', 'label', 'done', 'sub', 'detail', 'href', 'action');
    }

    private function stockState(Prescription $prescription): string
    {
        $hasOut = false;
        $hasPartial = false;

        foreach ($prescription->items as $item) {
            $stock = (int) ($item->medicine?->stock_quantity ?? 0);
            if ($stock <= 0) {
                $hasOut = true;
            } elseif ($stock < (int) $item->quantity) {
                $hasPartial = true;
            }
        }

        return $hasOut ? 'out_of_stock' : ($hasPartial ? 'partial' : 'all_in_stock');
    }

    private function state(Request $request): ?string
    {
        $state = (string) $request->query('state', '');

        return in_array($state, ['success', 'skeleton', 'empty', 'error'], true) ? $state : null;
    }
}