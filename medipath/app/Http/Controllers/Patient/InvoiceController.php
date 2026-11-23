<?php

namespace App\Http\Controllers\Patient;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\CurrencyFormatter;
use App\Services\SettingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** Owner-only billing history + PDF (PLAN Sprint 4.8). */
class InvoiceController extends Controller
{
    public function index(Request $request, CurrencyFormatter $money): Response
    {
        $patient = $request->user()->patient;
        abort_unless($patient, 403, 'No patient chart is linked to this account.');

        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $base = Invoice::query()->where('patient_id', $patient->id);

        $rows = (clone $base)
            ->with(['items:id,invoice_id,description,medicine_id', 'items.medicine:id,name', 'prescription:id,prescription_code'])
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(fn ($inner) => $inner->where('invoice_code', 'like', $like)
                    ->orWhereHas('items', fn ($items) => $items->where('description', 'like', $like)
                        ->orWhereHas('medicine', fn ($medicine) => $medicine->where('name', 'like', $like))));
            })
            ->when(in_array($status, array_column(PaymentStatus::cases(), 'value'), true), fn ($query) => $query->where('payment_status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $outstanding = (float) (clone $base)->where('payment_status', PaymentStatus::Pending->value)->sum('net_total');
        $paid = (float) (clone $base)->where('payment_status', PaymentStatus::Paid->value)->sum('net_total');
        $refunded = (float) (clone $base)->where('payment_status', PaymentStatus::Refunded->value)->sum('net_total');

        $forced = $this->state($request);
        $state = $forced ?? ($rows->total() === 0 ? 'empty' : 'success');

        return Inertia::render('Patient/Bills', [
            'invoices' => $rows->getCollection()->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'code' => $invoice->invoice_code,
                'createdAt' => $invoice->created_at?->toIso8601String(),
                'items' => $invoice->items->map(fn ($item): string => $item->description ?? $item->medicine?->name ?? 'Item')->all(),
                'netFormatted' => $money->format((float) $invoice->net_total, $invoice->currency_code),
                'method' => $invoice->payment_method,
                'status' => $invoice->payment_status?->value,
                'statusLabel' => $invoice->payment_status?->label(),
                'prescriptionCode' => $invoice->prescription?->prescription_code,
                'pdfUrl' => route('patient.bills.pdf', $invoice),
            ])->values(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'from' => $rows->firstItem(),
                'to' => $rows->lastItem(),
            ],
            'filters' => ['q' => $q, 'status' => $status],
            'kpis' => [
                'outstandingFormatted' => $money->format($outstanding),
                'outstandingCount' => (clone $base)->where('payment_status', PaymentStatus::Pending->value)->count(),
                'paidFormatted' => $money->format($paid),
                'paidCount' => (clone $base)->where('payment_status', PaymentStatus::Paid->value)->count(),
                'refundedFormatted' => $money->format($refunded),
                'refundedCount' => (clone $base)->where('payment_status', PaymentStatus::Refunded->value)->count(),
            ],
            'statuses' => collect(PaymentStatus::cases())->map(fn (PaymentStatus $s) => ['value' => $s->value, 'label' => $s->label()])->values(),
            'dataState' => $state,
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function pdf(Request $request, Invoice $invoice, CurrencyFormatter $money, SettingsService $settings): HttpResponse
    {
        $patient = $request->user()->patient;
        abort_unless($patient && (int) $invoice->patient_id === (int) $patient->id, 403);

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

    private function state(Request $request): ?string
    {
        $state = (string) $request->query('state', '');

        return in_array($state, ['success', 'skeleton', 'empty', 'error'], true) ? $state : null;
    }
}