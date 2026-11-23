<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use App\Services\CurrencyFormatter;
use App\Services\SettingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** Owner-only prescription history + PDF (PLAN Sprint 4.7). */
class PrescriptionController extends Controller
{
    public function index(Request $request): Response
    {
        $patient = $request->user()->patient;
        abort_unless($patient, 403, 'No patient chart is linked to this account.');

        $rows = Prescription::query()
            ->where('patient_id', $patient->id)
            ->with(['doctor.user', 'doctor.specialty', 'items.medicine', 'invoice:id,prescription_id,invoice_code,payment_status,net_total,currency_code'])
            ->latest()
            ->paginate(10);

        $forced = $this->state($request);
        $state = $forced ?? ($rows->total() === 0 ? 'empty' : 'success');

        return Inertia::render('Patient/Prescriptions', [
            'prescriptions' => $rows->getCollection()->map(fn (Prescription $rx): array => [
                'id' => $rx->id,
                'code' => $rx->prescription_code,
                'doctorName' => $rx->doctor?->user?->name,
                'specialty' => $rx->doctor?->specialty?->name,
                'status' => $rx->status?->value,
                'statusLabel' => $rx->status?->label(),
                'createdAt' => $rx->created_at?->toIso8601String(),
                'patientNotes' => $rx->patient_notes,
                'clinicalNotes' => $rx->clinical_notes,
                'safe' => ! $rx->hasOverride(),
                'items' => $rx->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'name' => $item->medicine?->name,
                    'strength' => $item->medicine?->strength,
                    'genericName' => $item->medicine?->generic_name,
                    'dosage' => $item->dosage,
                    'schedule' => $item->schedule,
                    'duration' => $item->duration,
                    'quantity' => $item->quantity,
                ])->values(),
                'invoiceCode' => $rx->invoice?->invoice_code,
                'pdfUrl' => route('patient.prescriptions.pdf', $rx),
            ])->values(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'from' => $rows->firstItem(),
                'to' => $rows->lastItem(),
            ],
            'dataState' => $state,
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function pdf(Request $request, Prescription $prescription, CurrencyFormatter $money, SettingsService $settings): HttpResponse
    {
        $patient = $request->user()->patient;
        abort_unless($patient && (int) $prescription->patient_id === (int) $patient->id, 403);

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

    private function state(Request $request): ?string
    {
        $state = (string) $request->query('state', '');

        return in_array($state, ['success', 'skeleton', 'empty', 'error'], true) ? $state : null;
    }
}