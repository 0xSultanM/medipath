<?php

namespace App\Http\Controllers\Pharmacist;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\CurrencyFormatter;
use App\Services\InvoiceService;
use App\Services\SettingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** Invoice search, payment state and PDF (PLAN Sprint 8.9). */
class InvoiceController extends Controller
{
    public function __construct(
        private readonly CurrencyFormatter $money,
        private readonly InvoiceService $invoices,
    ) {}

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $method = (string) $request->query('method', '');

        $rows = Invoice::query()
            ->with(['patient:id,patient_code,name', 'prescription:id,prescription_code'])
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                $query->where(fn ($inner) => $inner->where('invoice_code', 'like', $like)
                    ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', $like)->orWhere('patient_code', 'like', $like))
                    ->orWhereHas('prescription', fn ($p) => $p->where('prescription_code', 'like', $like)));
            })
            ->when(in_array($status, array_column(PaymentStatus::cases(), 'value'), true), fn ($query) => $query->where('payment_status', $status))
            ->when($method !== '' && array_key_exists($method, config('medipath.payment_methods')), fn ($query) => $query->where('payment_method', $method))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $today = Invoice::query()->whereDate('created_at', now()->toDateString());

        return Inertia::render('Pharmacist/Invoices', [
            'invoices' => $rows->getCollection()->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'code' => $invoice->invoice_code,
                'patientName' => $invoice->patient?->name,
                'patientCode' => $invoice->patient?->patient_code,
                'prescriptionCode' => $invoice->prescription?->prescription_code,
                'netFormatted' => $this->money->format((float) $invoice->net_total, $invoice->currency_code),
                'currencyCode' => $invoice->currency_code,
                'method' => $invoice->payment_method,
                'methodLabel' => (string) config('medipath.payment_methods.'.$invoice->payment_method, $invoice->payment_method),
                'status' => $invoice->payment_status?->value,
                'statusLabel' => $invoice->payment_status?->label(),
                'paidFormatted' => $this->money->format((float) $invoice->paid_amount, $invoice->currency_code),
                'createdAt' => $invoice->created_at?->toIso8601String(),
                'pdfUrl' => route('pharmacist.invoices.pdf', $invoice),
            ])->values(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'from' => $rows->firstItem(),
                'to' => $rows->lastItem(),
            ],
            'filters' => ['q' => $q, 'status' => $status, 'method' => $method],
            'statuses' => collect(PaymentStatus::cases())->map(fn (PaymentStatus $case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ])->values(),
            'methods' => collect(config('medipath.payment_methods'))->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'summary' => [
                'pending' => Invoice::query()->where('payment_status', PaymentStatus::Pending->value)->count(),
                'paid' => Invoice::query()->where('payment_status', PaymentStatus::Paid->value)->count(),
                'refunded' => Invoice::query()->where('payment_status', PaymentStatus::Refunded->value)->count(),
                'todayFormatted' => $this->money->format((float) (clone $today)->sum('net_total')),
                'todayCount' => (clone $today)->count(),
            ],
            'dataState' => $this->state($request) ?? ($rows->total() === 0 ? 'empty' : 'success'),
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function updateStatus(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'payment_status' => ['required', Rule::in([PaymentStatus::Paid->value, PaymentStatus::Refunded->value])],
            'refund_reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ]);

        $this->invoices->updateStatus($invoice, $validated, $request->user());

        return back()->with('success', "Invoice {$invoice->invoice_code} updated.");
    }

    public function pdf(Request $request, Invoice $invoice, SettingsService $settings): HttpResponse
    {
        $invoice->load(['items.medicine', 'patient', 'prescription', 'pharmacist.user']);

        $pdf = Pdf::loadView('admin.pdf.invoice', [
            'invoice' => $invoice,
            'money' => $this->money,
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