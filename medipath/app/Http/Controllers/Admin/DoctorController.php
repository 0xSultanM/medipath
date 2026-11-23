<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DoctorRequest;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Services\AdminDoctorService;
use App\Services\CurrencyFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Doctor directory CRUD with soft archive (admin/04). */
class DoctorController extends Controller
{
    public function __construct(private readonly AdminDoctorService $doctors) {}

    public function index(Request $request, CurrencyFormatter $money): Response
    {
        $q = trim((string) $request->query('q', ''));
        $specialty = (int) $request->query('specialty', 0);
        $verification = (string) $request->query('verification', '');
        $scope = (string) $request->query('scope', 'active');

        $doctors = Doctor::query()
            ->with(['user', 'specialty'])
            ->when($scope === 'archived', fn ($query) => $query->whereNotNull('archived_at'))
            ->when($scope === 'active', fn ($query) => $query->whereNull('archived_at'))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                $query->where(fn ($inner) => $inner
                    ->where('license_number', 'like', $like)
                    ->orWhereHas('user', fn ($user) => $user
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)));
            })
            ->when($specialty > 0, fn ($query) => $query->where('specialty_id', $specialty))
            ->when(in_array($verification, ['unverified', 'pending', 'verified', 'resubmission', 'revoked'], true),
                fn ($query) => $query->where('verification_status', $verification))
            ->orderBy('is_featured', 'desc')
            ->orderBy('doctor_code')
            ->paginate(15)
            ->withQueryString();

        $forced = (string) $request->query('state', '');
        $state = in_array($forced, ['success', 'skeleton', 'empty', 'error'], true)
            ? $forced
            : ($doctors->total() === 0 ? 'empty' : 'success');

        return Inertia::render('Admin/Doctors', [
            'doctors' => $doctors->getCollection()->map(fn (Doctor $doctor): array => [
                'id' => $doctor->id,
                'name' => $doctor->user?->name,
                'initials' => $doctor->user?->initials,
                'email' => $doctor->user?->email,
                'phone' => $doctor->user?->phone,
                'code' => $doctor->doctor_code,
                'specialtyId' => $doctor->specialty_id,
                'specialty' => $doctor->specialty?->name,
                'licenseNumber' => $doctor->license_number,
                'licenseAuthority' => $doctor->license_authority,
                'experienceYears' => $doctor->experience_years,
                'fee' => (float) $doctor->fee,
                'feeFormatted' => $money->format((float) $doctor->fee),
                'bio' => $doctor->bio,
                'clinicName' => $doctor->clinic_name,
                'clinicAddress' => $doctor->clinic_address,
                'room' => $doctor->room,
                'isFeatured' => $doctor->is_featured,
                'verificationStatus' => $doctor->verification_status?->value,
                'verificationLabel' => $doctor->verification_status?->label(),
                'bookings' => $doctor->appointments()->count(),
                'archivedAt' => $doctor->archived_at?->toIso8601String(),
                'dependencies' => $this->doctors->dependencyCounts($doctor),
            ])->values(),
            'meta' => [
                'current_page' => $doctors->currentPage(),
                'last_page' => $doctors->lastPage(),
                'per_page' => $doctors->perPage(),
                'total' => $doctors->total(),
                'from' => $doctors->firstItem(),
                'to' => $doctors->lastItem(),
            ],
            'filters' => [
                'q' => $q,
                'specialty' => $specialty,
                'verification' => $verification,
                'scope' => $scope,
            ],
            'specialties' => Specialty::query()->active()->orderBy('name')->get(['id', 'name']),
            'statuses' => collect(config('medipath.verification_statuses'))
                ->map(fn ($meta, $value) => ['value' => $value, 'label' => $meta['label']])
                ->values(),
            'dataState' => $state,
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function store(DoctorRequest $request): RedirectResponse
    {
        $doctor = $this->doctors->create($request->validated(), $request->user());

        return redirect()->route('admin.doctors.index')
            ->with('success', "Doctor profile {$doctor->doctor_code} created. License verification is pending.");
    }

    public function update(DoctorRequest $request, Doctor $doctor): RedirectResponse
    {
        $this->doctors->update($doctor, $request->validated(), $request->user());

        return back()->with('success', 'Doctor record updated.');
    }

    public function archive(Request $request, Doctor $doctor): RedirectResponse
    {
        $validated = $request->validate([
            'confirm_name' => ['required', 'string', 'max:160'],
        ]);

        $this->doctors->archive($doctor, (string) $validated['confirm_name'], $request->user());

        return back()->with('success', 'Doctor archived (soft delete). Linked records remain readable forever.');
    }

    public function restore(Request $request, Doctor $doctor): RedirectResponse
    {
        $this->doctors->restore($doctor, $request->user());

        return back()->with('success', 'Doctor restored. Booking access depends on verification status.');
    }
}