<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\ModerationStatus;
use App\Enums\PaymentStatus;
use App\Enums\PrescriptionStatus;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Review;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Admin reports & analytics + export rows (PLAN Sprint 3.4). */
class AdminReportService
{
    public function __construct(private readonly CurrencyFormatter $money) {}

    /** @return array<string, mixed> */
    public function build(int $days): array
    {
        $days = in_array($days, [30, 90, 365], true) ? $days : 30;
        $from = now()->subDays($days)->startOfDay();
        $to = now()->endOfDay();

        $monthly = $this->monthlySeries($from);
        $growth = $this->growthSeries($from);

        $prescriptions = Prescription::query()->whereBetween('created_at', [$from, $to])->get();
        $dispensed = $prescriptions->where('status', PrescriptionStatus::Dispensed)->count();
        $cancelled = $prescriptions->where('status', PrescriptionStatus::Cancelled)->count();
        $durations = $prescriptions
            ->filter(fn (Prescription $rx): bool => $rx->dispensed_at !== null)
            ->map(fn (Prescription $rx): float => abs((float) $rx->created_at->diffInMinutes($rx->dispensed_at)))
            ->sort()
            ->values();
        $medianMinutes = $durations->isEmpty() ? null : (int) round((float) $durations[intdiv($durations->count(), 2)]);

        $invoices = Invoice::query()->whereBetween('created_at', [$from, $to])->get();
        $paid = $invoices->where('payment_status', PaymentStatus::Paid);
        $pending = $invoices->where('payment_status', PaymentStatus::Pending);
        $refunded = $invoices->where('payment_status', PaymentStatus::Refunded);

        $stock = $this->stockHealth();
        $appointments = $this->appointmentStats($from, $to);
        $leaderboard = $this->leaderboard($from, $to);

        return [
            'range' => $days,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'monthlyRevenue' => [
                'labels' => $monthly->pluck('label')->all(),
                'values' => $monthly->pluck('value')->all(),
                'totalFormatted' => $this->money->format($monthly->sum('value')),
            ],
            'patientGrowth' => [
                'labels' => $growth->pluck('label')->all(),
                'values' => $growth->pluck('value')->all(),
                'total' => $growth->sum('value'),
                'walkIns' => Patient::query()->where('is_walk_in', true)->whereBetween('created_at', [$from, $to])->count(),
            ],
            'prescriptions' => [
                'total' => $prescriptions->count(),
                'dispensed' => $dispensed,
                'cancelled' => $cancelled,
                'completionRate' => $prescriptions->count() > 0 ? (int) round(($dispensed / $prescriptions->count()) * 100) : 0,
                'refusedRate' => $prescriptions->count() > 0 ? round(($cancelled / $prescriptions->count()) * 100, 1) : 0.0,
                'medianDispenseMinutes' => $medianMinutes,
                'medianDispenseLabel' => $medianMinutes === null ? '—' : intdiv($medianMinutes, 60).'h '.($medianMinutes % 60).'m',
            ],
            'collection' => [
                'paidFormatted' => $this->money->format((float) $paid->sum('net_total')),
                'paidCount' => $paid->count(),
                'pendingFormatted' => $this->money->format((float) $pending->sum('net_total')),
                'pendingCount' => $pending->count(),
                'refundedFormatted' => $this->money->format((float) $refunded->sum('net_total')),
                'refundedCount' => $refunded->count(),
                'aging' => [
                    'under30' => $this->money->format((float) $pending->filter(fn (Invoice $i): bool => $i->created_at->gte(now()->subDays(30)))->sum('net_total')),
                    'between31And60' => $this->money->format((float) $pending->filter(fn (Invoice $i): bool => $i->created_at->lt(now()->subDays(30)) && $i->created_at->gte(now()->subDays(60)))->sum('net_total')),
                    'over60' => $this->money->format((float) $pending->filter(fn (Invoice $i): bool => $i->created_at->lt(now()->subDays(60)))->sum('net_total')),
                ],
            ],
            'stock' => $stock,
            'appointments' => $appointments,
            'leaderboard' => $leaderboard,
        ];
    }

    /** @return Collection<int, array{label:string,value:float}> */
    private function monthlySeries(Carbon $from): Collection
    {
        $start = now()->subMonths(11)->startOfMonth();
        $rows = Invoice::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $start)
            ->get(['paid_at', 'net_total']);

        $series = collect();
        for ($i = 0; $i < 12; $i++) {
            $month = $start->copy()->addMonths($i);
            $series->push([
                'label' => $month->format('M'),
                'value' => (float) $rows->filter(fn (Invoice $invoice): bool => $invoice->paid_at?->format('Y-m') === $month->format('Y-m'))->sum('net_total'),
            ]);
        }

        return $series;
    }

    /** @return Collection<int, array{label:string,value:int}> */
    private function growthSeries(Carbon $from): Collection
    {
        $start = now()->subMonths(11)->startOfMonth();
        $rows = Patient::query()->where('created_at', '>=', $start)->get(['created_at']);

        $series = collect();
        for ($i = 0; $i < 12; $i++) {
            $month = $start->copy()->addMonths($i);
            $series->push([
                'label' => $month->format('M'),
                'value' => $rows->filter(fn (Patient $patient): bool => $patient->created_at?->format('Y-m') === $month->format('Y-m'))->count(),
            ]);
        }

        return $series;
    }

    /** @return array<string, mixed> */
    private function stockHealth(): array
    {
        $active = Medicine::query()->active()->get();

        $lowItems = $active
            ->filter(fn (Medicine $medicine): bool => $medicine->stock_status !== 'healthy')
            ->sortBy('stock_quantity')
            ->take(5)
            ->map(fn (Medicine $medicine): array => [
                'name' => $medicine->name,
                'strength' => $medicine->strength,
                'stock' => $medicine->stock_quantity,
                'status' => $medicine->stock_status,
                'expiry' => $medicine->expiry_status,
            ])
            ->values()
            ->all();

        return [
            'out' => $active->filter(fn (Medicine $m): bool => $m->stock_status === 'out_of_stock')->count(),
            'critical' => $active->filter(fn (Medicine $m): bool => $m->stock_status === 'critical')->count(),
            'low' => $active->filter(fn (Medicine $m): bool => $m->stock_status === 'low')->count(),
            'expiring30' => $active->filter(fn (Medicine $m): bool => $m->expiry_status === 'critical')->count(),
            'expiring90' => $active->filter(fn (Medicine $m): bool => $m->expiry_status === 'upcoming')->count(),
            'items' => $lowItems,
        ];
    }

    /** @return array<string, mixed> */
    private function appointmentStats(Carbon $from, Carbon $to): array
    {
        $rows = Appointment::query()->whereBetween('date', [$from->toDateString(), $to->toDateString()])->get(['status']);
        $total = max(1, $rows->count());
        $accepted = $rows->where('status', AppointmentStatus::Accepted)->count();
        $completed = $rows->where('status', AppointmentStatus::Completed)->count();
        $pending = $rows->where('status', AppointmentStatus::Pending)->count();
        $cancelled = $rows->where('status', AppointmentStatus::Cancelled)->count();
        $doctorCancelled = Appointment::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where('status', AppointmentStatus::Cancelled)
            ->whereHas('doctor.user', fn ($q) => $q->where('role', 'doctor'))
            ->count();

        return [
            'total' => $rows->count(),
            'pending' => $pending,
            'accepted' => $accepted,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'acceptanceRate' => $rows->count() > 0 ? (int) round((($accepted + $completed) / $total) * 100) : 0,
            'completionRate' => ($accepted + $completed) > 0 ? (int) round(($completed / max(1, $accepted + $completed)) * 100) : 0,
            'cancellationRate' => round(($cancelled / $total) * 100, 1),
            'doctorCancelled' => $doctorCancelled,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function leaderboard(Carbon $from, Carbon $to): array
    {
        $appointments = Appointment::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get(['doctor_id', 'status']);

        $invoices = Invoice::query()
            ->with('prescription:id,doctor_id')
            ->where('payment_status', PaymentStatus::Paid)
            ->whereBetween('paid_at', [$from, $to])
            ->get(['id', 'prescription_id', 'net_total']);

        $ratings = Review::query()
            ->where('moderation_status', ModerationStatus::Approved->value)
            ->get(['doctor_id', 'rating']);

        return Doctor::query()->with(['user:id,name', 'specialty:id,name'])->get()
            ->map(function (Doctor $doctor) use ($appointments, $invoices, $ratings): array {
                $doctorAppointments = $appointments->where('doctor_id', $doctor->id);
                $completed = $doctorAppointments->where('status', AppointmentStatus::Completed)->count();
                $total = $doctorAppointments->count();
                $doctorRatings = $ratings->where('doctor_id', $doctor->id);
                $revenue = $invoices->filter(fn (Invoice $invoice): bool => (int) $invoice->prescription?->doctor_id === $doctor->id)->sum('net_total');

                return [
                    'id' => $doctor->id,
                    'name' => $doctor->user?->name,
                    'specialty' => $doctor->specialty?->name,
                    'visits' => $completed,
                    'completion' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
                    'rating' => $doctorRatings->isEmpty() ? null : round((float) $doctorRatings->avg('rating'), 1),
                    'revenueFormatted' => $this->money->format((float) $revenue),
                    'revenue' => (float) $revenue,
                ];
            })
            ->filter(fn (array $row): bool => $row['visits'] > 0 || $row['revenue'] > 0)
            ->sortByDesc('revenue')
            ->take(8)
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $report */
    public function csv(array $report): string
    {
        $lines = [];
        $row = static function (array $values): string {
            $safe = array_map(static function (mixed $value): string {
                $text = (string) $value;
                if (preg_match('/^[=+\-@\t\r]/', $text) === 1) {
                    return "'".$text;
                }

                return $text;
            }, $values);

            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, $safe, ',', '"', '\\');
            rewind($handle);
            $line = (string) stream_get_contents($handle);
            fclose($handle);

            return rtrim($line, "\r\n");
        };

        $lines[] = $row(['MediPath report', $report['from'].' to '.$report['to']]);
        $lines[] = '';
        $lines[] = $row(['1. Monthly revenue']);
        foreach ($report['monthlyRevenue']['labels'] as $index => $label) {
            $lines[] = $row([$label, $report['monthlyRevenue']['values'][$index]]);
        }
        $lines[] = '';
        $lines[] = $row(['2. Patient growth']);
        foreach ($report['patientGrowth']['labels'] as $index => $label) {
            $lines[] = $row([$label, $report['patientGrowth']['values'][$index]]);
        }
        $lines[] = $row(['Walk-ins', $report['patientGrowth']['walkIns']]);
        $lines[] = '';
        $lines[] = $row(['3. Prescription completion']);
        foreach ($report['prescriptions'] as $key => $value) {
            $lines[] = $row([$key, is_scalar($value) ? $value : json_encode($value)]);
        }
        $lines[] = '';
        $lines[] = $row(['4. Invoice collection']);
        foreach ($report['collection'] as $key => $value) {
            $lines[] = $row([$key, is_scalar($value) ? $value : json_encode($value)]);
        }
        $lines[] = '';
        $lines[] = $row(['5. Stock health']);
        foreach ($report['stock'] as $key => $value) {
            $lines[] = $row([$key, is_scalar($value) ? $value : json_encode($value)]);
        }
        $lines[] = '';
        $lines[] = $row(['6. Appointment conversion']);
        foreach ($report['appointments'] as $key => $value) {
            $lines[] = $row([$key, $value]);
        }
        $lines[] = '';
        $lines[] = $row(['7. Top doctors', 'Visits', 'Completion %', 'Rating', 'Revenue']);
        foreach ($report['leaderboard'] as $doctor) {
            $lines[] = $row([$doctor['name'], $doctor['visits'], $doctor['completion'], $doctor['rating'] ?? '', $doctor['revenueFormatted']]);
        }

        return implode("\r\n", $lines)."\r\n";
    }
}