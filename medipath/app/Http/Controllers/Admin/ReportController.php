<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use App\Services\AdminReportService;
use App\Services\CurrencyFormatter;
use App\Support\ActivityActions;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Reports & analytics + CSV/PDF export (admin/08, Sprint 3.4). */
class ReportController extends Controller
{
    public function __construct(
        private readonly AdminReportService $reports,
        private readonly ActivityLogService $activity,
        private readonly CurrencyFormatter $money,
    ) {}

    public function index(Request $request): Response
    {
        $range = (int) $request->query('range', 30);
        $report = $this->reports->build($range);

        $forced = (string) $request->query('state', '');
        $state = in_array($forced, ['success', 'skeleton', 'empty', 'error'], true)
            ? $forced
            : (($report['monthlyRevenue']['totalFormatted'] !== $this->money->format(0) || $report['appointments']['total'] > 0) ? 'success' : 'empty');

        return Inertia::render('Admin/Reports', [
            'report' => $report,
            'sections' => [
                'monthly_revenue' => 'Monthly revenue',
                'patient_growth' => 'Patient growth',
                'prescription_completion' => 'Prescription completion',
                'invoice_collection' => 'Invoice collection',
                'stock_health' => 'Stock health & expiry risk',
                'appointment_conversion' => 'Appointment conversion & cancellation',
                'doctor_leaderboard' => 'Top-doctor leaderboard',
            ],
            'dataState' => $state,
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function csv(Request $request): HttpResponse
    {
        $range = (int) $request->query('range', 30);
        $report = $this->reports->build($range);

        $this->activity->record(ActivityActions::REPORT_EXPORTED, null, [
            'format' => 'csv',
            'range' => $range,
            'from' => $report['from'],
            'to' => $report['to'],
        ], $request->user());

        return response($this->reports->csv($report), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="medipath-report-'.$report['from'].'-to-'.$report['to'].'.csv"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function pdf(Request $request): HttpResponse
    {
        $range = (int) $request->query('range', 30);
        $report = $this->reports->build($range);

        $this->activity->record(ActivityActions::REPORT_EXPORTED, null, [
            'format' => 'pdf',
            'range' => $range,
            'from' => $report['from'],
            'to' => $report['to'],
        ], $request->user());

        $pdf = Pdf::loadView('admin.pdf.report', [
            'report' => $report,
            'money' => $this->money,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('medipath-report-'.$report['from'].'-to-'.$report['to'].'.pdf');
    }
}