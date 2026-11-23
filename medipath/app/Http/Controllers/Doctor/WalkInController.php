<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Services\DoctorAvailabilityService;
use App\Services\WalkInRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Doctor-side walk-in registration and claim receipt (PLAN Sprint 6.5). */
class WalkInController extends Controller
{
    public function __construct(
        private readonly DoctorAvailabilityService $availability,
        private readonly WalkInRegistrationService $walkIns,
    ) {}

    public function index(Request $request): Response
    {
        $doctor = $this->doctor($request);
        $days = collect($this->availability->forDoctor($doctor, null, 7))
            ->filter(fn (array $day) => $day['open_slots'] > 0)
            ->map(fn (array $day): array => [
                'date' => $day['date'],
                'label' => $day['label'],
                'openTimes' => $day['open_times'],
            ])
            ->values();

        return Inertia::render('Doctor/WalkIn', [
            'days' => $days,
            'receipt' => $request->session()->get('walk_in_receipt'),
            'genders' => config('medipath.registration.genders'),
            'dataState' => $days->isEmpty() ? 'empty' : 'success',
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $doctor = $this->doctor($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'max:32'],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'gender' => ['nullable', Rule::in(config('medipath.registration.genders'))],
            'condition' => ['required', 'string', 'min:3', 'max:200'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
            'link_patient_code' => ['nullable', 'string', 'max:24'],
        ]);

        $receipt = $this->walkIns->register($doctor, $validated, $request->user());

        return back()->with('walk_in_receipt', $receipt)
            ->with('success', "Walk-in {$receipt['appointmentCode']} registered. Claim code {$receipt['claimCode']} — hand it to the patient.");
    }

    private function doctor(Request $request): Doctor
    {
        $doctor = $request->user()->doctor;
        abort_unless($doctor, 403, 'No doctor profile is linked to this account.');

        return $doctor;
    }
}