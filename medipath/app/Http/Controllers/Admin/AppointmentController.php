<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Services\AdminAppointmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Global appointment oversight (admin/06, Sprint 3.1). */
class AppointmentController extends Controller
{
    public function __construct(private readonly AdminAppointmentService $appointments) {}

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $doctor = (int) $request->query('doctor', 0);
        $status = (string) $request->query('status', '');
        $date = (string) $request->query('date', '');

        $rows = Appointment::query()
            ->with(['patient:id,patient_code,name,is_walk_in', 'doctor:id,user_id', 'doctor.user:id,name'])
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                $query->where(fn ($inner) => $inner
                    ->where('appointment_code', 'like', $like)
                    ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', $like)->orWhere('patient_code', 'like', $like))
                    ->orWhereHas('doctor.user', fn ($u) => $u->where('name', 'like', $like)));
            })
            ->when($doctor > 0, fn ($query) => $query->where('doctor_id', $doctor))
            ->when(in_array($status, AppointmentStatus::values(), true), fn ($query) => $query->where('status', $status))
            ->when($date !== '', fn ($query) => $query->whereDate('date', $date))
            ->orderByDesc('date')
            ->orderByDesc('start_time')
            ->paginate(15)
            ->withQueryString();

        $forced = (string) $request->query('state', '');
        $state = in_array($forced, ['success', 'skeleton', 'empty', 'error'], true)
            ? $forced
            : ($rows->total() === 0 ? 'empty' : 'success');

        return Inertia::render('Admin/Appointments', [
            'appointments' => $rows->getCollection()->map(fn (Appointment $appointment): array => [
                'id' => $appointment->id,
                'code' => $appointment->appointment_code,
                'date' => $appointment->date?->toDateString(),
                'startTime' => substr((string) $appointment->start_time, 0, 5),
                'patientName' => $appointment->patient?->name,
                'patientCode' => $appointment->patient?->patient_code,
                'isWalkIn' => (bool) $appointment->patient?->is_walk_in,
                'doctorName' => $appointment->doctor?->user?->name,
                'status' => $appointment->status?->value,
                'statusLabel' => $appointment->status?->label(),
                'visitReason' => $appointment->visit_reason,
                'cancellationReason' => $appointment->cancellation_reason,
                'canCancel' => in_array($appointment->status, [AppointmentStatus::Pending, AppointmentStatus::Accepted], true),
                'createdAt' => $appointment->created_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'from' => $rows->firstItem(),
                'to' => $rows->lastItem(),
            ],
            'filters' => ['q' => $q, 'doctor' => $doctor, 'status' => $status, 'date' => $date],
            'doctors' => Doctor::query()->with('user:id,name')->whereNull('archived_at')->orderBy('doctor_code')->get()
                ->map(fn (Doctor $d): array => ['id' => $d->id, 'name' => $d->user?->name])->values(),
            'statuses' => collect(AppointmentStatus::cases())->map(fn (AppointmentStatus $s) => ['value' => $s->value, 'label' => $s->label()])->values(),
            'dataState' => $state,
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function cancel(Request $request, Appointment $appointment): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);

        $this->appointments->cancel($appointment, (string) $validated['reason'], $request->user());

        return back()->with('success', 'Appointment cancelled on behalf of the clinic. Both parties were notified and the slot was released.');
    }
}