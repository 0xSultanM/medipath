<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PrescriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Services\CurrencyFormatter;
use App\Services\SettingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** Clinical + billing record oversight (admin/07, Sprint 3.2). */
class RecordController extends Controller
{
    public function index(Request $request, CurrencyFormatter $money): Response
    {
        $tab = (string) $request->query('tab', 'prescriptions');
        $tab = in_array($tab, ['prescriptions', 'invoices', 'inventory'], true) ? $tab : 'prescriptions';
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        if ($tab === 'invoices') {
            $rows = Invoice::query()
                ->with('patient:id,patient_code,name')
                ->when($q !== '', function ($query) use ($q) {
                    $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                    $query->where(fn ($inner) => $inner->where('invoice_code', 'like', $like)
                        ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', $like)->orWhere('patient_code', 'like', $like)));
                })
                ->when($status !== '', fn ($query) => $query->where('payment_status', $status))
                ->latest()
                ->paginate(15)
                ->withQueryString();
        } elseif ($tab === 'inventory') {
            $rows = Medicine::query()
                ->when($q !== '', function ($query) use ($q) {
                    $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                    $query->where(fn ($inner) => $inner->where('name', 'like', $like)
                        ->orWhere('generic_name', 'like', $like)
                        ->orWhere('batch_number', 'like', $like));
                })
                ->orderBy('stock_quantity')
                ->paginate(15)
                ->withQueryString();
        } else {
            $rows = Prescription::query()
                ->with(['patient:id,patient_code,name', 'doctor:id,user_id', 'doctor.user:id,name', 'items:id,prescription_id'])
                ->when($q !== '', function ($query) use ($q) {
                    $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                    $query->where(fn ($inner) => $inner->where('prescription_code', 'like', $like)
                        ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', $like)->orWhere('patient_code', 'like', $like)));
                })
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->latest()
                ->paginate(15)
                ->withQueryString();
        }

        $forced = (string) $request->query('state', '');
        $state = in_array($forced, ['success', 'skeleton', 'empty', 'error'], true)
            ? $forced
            : ($rows->total() === 0 ? 'empty' : 'success');

        $payload = match ($tab) {
            'invoices' => $rows->getCollection()->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'code' => $invoice->invoice_code,
                'patientCode' => $invoice->patient?->patient_code,
                'patientName' => $invoice->patient?->name,
                'netFormatted' => $money->format((float) $invoice->net_total, $invoice->currency_code),
                'currencyCode' => $invoice->currency_code,
                'status' => $invoice->payment_status?->value,
                'statusLabel' => $invoice->payment_status?->label(),
                'method' => $invoice->payment_method,
                'createdAt' => $invoice->created_at?->toIso8601String(),
                'pdfUrl' => route('admin.records.invoices.pdf', $invoice),
            ])->values(),
            'inventory' => $rows->getCollection()->map(fn (Medicine $medicine): array => [
                'id' => $medicine->id,
                'name' => $medicine->name,
                'genericName' => $medicine->generic_name,
                'strength' => $medicine->strength,
                'batch' => $medicine->batch_number,
                'stock' => $medicine->stock_quantity,
                'threshold' => $medicine->min_stock_threshold,
                'stockStatus' => $medicine->stock_status,
                'expiryStatus' => $medicine->expiry_status,
                'expiryDate' => $medicine->expiry_date?->toDateString(),
                'priceFormatted' => $money->format((float) $medicine->unit_price),
            ])->values(),
            default => $rows->getCollection()->map(fn (Prescription $rx): array => [
                'id' => $rx->id,
                'code' => $rx->prescription_code,
                'patientCode' => $rx->patient?->patient_code,
                'patientName' => $rx->patient?->name,
                'doctorName' => $rx->doctor?->user?->name,
                'items' => $rx->items->count(),
                'safety' => $rx->hasOverride() ? 'override' : 'clean',
                'safetyLabel' => $rx->hasOverride() ? '1 override sealed' : 'clean',
                'status' => $rx->status?->value,
                'statusLabel' => $rx->status?->label(),
                'createdAt' => $rx->created_at?->toIso8601String(),
                'pdfUrl' => route('admin.records.prescriptions.pdf', $rx),
            ])->values(),
        };

        return Inertia::render('Admin/Records', [
            'tab' => $tab,
            'items' => $payload,
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'from' => $rows->firstItem(),
                'to' => $rows->lastItem(),
            ],
            'filters' => ['q' => $q, 'status' => $status],
            'counts' => [
                'prescriptions' => Prescription::query()->count(),
                'invoices' => Invoice::query()->count(),
                'inventory' => Medicine::query()->count(),
            ],
            'statuses' => $tab === 'invoices'
                ? collect(\App\Enums\PaymentStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->values()
                : collect(PrescriptionStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->values(),
            'dataState' => $state,
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function prescriptionPdf(Prescription $prescription, CurrencyFormatter $money, SettingsService $settings): HttpResponse
    {
        $prescription->load(['patient', 'doctor.user', 'doctor.specialty', 'items.medicine', 'invoice']);

        $pdf = Pdf::loadView('admin.pdf.prescription', [
            'prescription' => $prescription,
            'money' => $money,
            'clinic' => $settings->clinicName(),
            'header' => (string) $settings->get('pdf_header', $settings->clinicName()),
            'footer' => (string) $settings->get('pdf_footer', 'Generated electronically by MediPath.'),
        ])->setPaper('a4');

        return $pdf->download($prescription->prescription_code.'.pdf');
    }

    public function invoicePdf(Invoice $invoice, CurrencyFormatter $money, SettingsService $settings): HttpResponse
    {
        $invoice->load(['patient', 'items.medicine', 'prescription.doctor.user']);

        $pdf = Pdf::loadView('admin.pdf.invoice', [
            'invoice' => $invoice,
            'money' => $money,
            'clinic' => $settings->clinicName(),
            'header' => (string) $settings->get('pdf_header', $settings->clinicName()),
            'footer' => (string) $settings->get('pdf_footer', 'Generated electronically by MediPath.'),
        ])->setPaper('a4');

        return $pdf->download($invoice->invoice_code.'.pdf');
    }
}