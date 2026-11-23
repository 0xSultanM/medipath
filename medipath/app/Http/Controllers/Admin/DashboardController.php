<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Enums\ModerationStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\PendingApproval;
use App\Models\Prescription;
use App\Models\Review;
use App\Models\User;
use App\Services\CurrencyFormatter;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Inertia;
use Inertia\Response;

/** Master dashboard + system overview (PLAN Sprints 2.2, admin/01). */
class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        SettingsService $settings,
        CurrencyFormatter $money,
    ): Response {
        $range = (int) $request->query('range', 30);
        if (! in_array($range, [7, 30, 90, 365], true)) {
            $range = 30;
        }

        $from = now()->subDays($range)->startOfDay();
        $to = now()->endOfDay();

        $totalUsers = User::query()->whereNull('archived_at')->count();

        $activePatients = User::query()
            ->where('role', 'patient')
            ->where('status', AccountStatus::Approved->value)
            ->whereNull('archived_at')
            ->count();

        $verifiedDoctors = Doctor::query()
            ->verified()
            ->whereHas('user', fn ($query) => $query->where('status', AccountStatus::Approved->value))
            ->count();

        $prescriptions = Prescription::query()->whereBetween('created_at', [$from, $to])->count();
        $dispensed = Prescription::query()->whereBetween('created_at', [$from, $to])
            ->where('status', 'dispensed')->count();
        $completionRate = $prescriptions > 0 ? (int) round(($dispensed / $prescriptions) * 100) : 0;

        $revenue = (float) Invoice::query()
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->sum('net_total');
        $commission = round($revenue * ($settings->revenuePercentage() / 100), 2);

        $appointments = Appointment::query()->whereBetween('date', [$from->toDateString(), $to->toDateString()])->count();
        $activeMedicines = Medicine::query()->active()->count();

        $trend = $this->trend();
        $today = Carbon::today();
        $onDuty = Doctor::query()
            ->verified()
            ->whereHas('user', fn ($query) => $query->where('status', AccountStatus::Approved->value))
            ->whereHas('schedules', fn ($query) => $query->where('day_of_week', $today->dayOfWeek)->where('is_active', true))
            ->whereDoesntHave('leaves', fn ($query) => $query->where('status', 'approved')
                ->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today))
            ->count();

        $lowStock = Medicine::query()->active()->lowStock()->count();
        $outOfStock = Medicine::query()->active()->where('stock_quantity', '<=', 0)->count();
        $expiring = Medicine::query()->active()
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', today()->addDays(90))
            ->count();
        $pendingApprovals = PendingApproval::query()->pending()->count();
        $pendingVerifications = Doctor::query()
            ->whereIn('verification_status', [
                VerificationStatus::Unverified->value,
                VerificationStatus::Pending->value,
                VerificationStatus::Resubmission->value,
            ])
            ->count();
        $pendingReviews = Review::query()->where('moderation_status', ModerationStatus::Pending->value)->count();
        $pendingPrescriptions = Prescription::query()->where('status', 'pending_dispense')->count();

        $dbHealthy = true;
        try {
            DB::select('select 1');
        } catch (\Throwable) {
            $dbHealthy = false;
        }

        $queueSize = 0;
        try {
            $queueSize = (int) Queue::size('notifications');
        } catch (\Throwable) {
            $queueSize = 0;
        }

        $totalDisk = @disk_total_space(storage_path());
        $freeDisk = @disk_free_space(storage_path());
        $storagePercent = $totalDisk && $freeDisk
            ? (int) round((($totalDisk - $freeDisk) / $totalDisk) * 100)
            : null;

        $state = (string) $request->query('state', '');
        $dataState = in_array($state, ['success', 'skeleton', 'empty', 'error'], true) ? $state : 'success';

        return Inertia::render('Admin/Dashboard', [
            'range' => $range,
            'dataState' => $dataState,
            'lastKnownAt' => now()->toIso8601String(),
            'kpis' => [
                'totalUsers' => $totalUsers,
                'awaitingAction' => $pendingApprovals + $pendingVerifications,
                'activePatients' => $activePatients,
                'verifiedDoctors' => $verifiedDoctors,
                'prescriptionsIssued' => $prescriptions,
                'activeMedicines' => $activeMedicines,
                'appointments' => $appointments,
                'revenueFormatted' => $money->format($revenue),
                'revenueCompact' => $money->formatCompact($revenue),
                'commissionCompact' => $money->formatCompact($commission),
                'commissionFormatted' => $money->format($commission),
                'revenuePercent' => $settings->revenuePercentage(),
                'revenueConfigured' => $settings->revenueConfigured(),
                'completionRate' => $completionRate,
            ],
            'system' => [
                'db' => $dbHealthy,
                'queueSize' => $queueSize,
                'storagePercent' => $storagePercent,
                'onDutyDoctors' => $onDuty,
                'activePatients' => $activePatients,
                'pendingPrescriptions' => $pendingPrescriptions,
                'pendingApprovals' => $pendingApprovals,
                'pendingVerifications' => $pendingVerifications,
                'pendingReviews' => $pendingReviews,
                'lowStock' => $lowStock,
                'outOfStock' => $outOfStock,
                'expiring' => $expiring,
                'aiEnabled' => (bool) $settings->get('ai_enabled', false),
                'aiModel' => (string) $settings->get('ai_model', 'fallback'),
            ],
            'attention' => [
                ['key' => 'approvals', 'label' => 'Sign-up approvals waiting', 'count' => $pendingApprovals, 'tone' => 'warning', 'href' => '/admin/approvals'],
                ['key' => 'verification', 'label' => 'Doctor verifications waiting', 'count' => $pendingVerifications, 'tone' => 'warning', 'href' => '/admin/approvals?tab=verification'],
                ['key' => 'reviews', 'label' => 'Reviews awaiting moderation', 'count' => $pendingReviews, 'tone' => 'danger', 'href' => '/admin/faq-reviews'],
                ['key' => 'stock', 'label' => 'Low / out-of-stock medicines', 'count' => $lowStock + $outOfStock, 'tone' => 'neutral', 'href' => '/admin/records?tab=inventory'],
            ],
            'trend' => $trend,
        ]);
    }

    /** @return array<int, array{label:string,booked:int,completed:int}> */
    private function trend(): array
    {
        $start = now()->subWeeks(11)->startOfWeek();
        $appointments = Appointment::query()
            ->where('date', '>=', $start->toDateString())
            ->get(['date', 'status']);

        $weeks = [];
        for ($i = 0; $i < 12; $i++) {
            $weekStart = $start->copy()->addWeeks($i);
            $key = $weekStart->format('Y-m-d');
            $weeks[$key] = [
                'label' => $weekStart->format('d M'),
                'booked' => 0,
                'completed' => 0,
            ];
        }

        foreach ($appointments as $appointment) {
            $key = $appointment->date->copy()->startOfWeek()->format('Y-m-d');
            if (! isset($weeks[$key])) {
                continue;
            }
            $weeks[$key]['booked']++;
            if ($appointment->status?->value === 'completed') {
                $weeks[$key]['completed']++;
            }
        }

        return array_values($weeks);
    }
}