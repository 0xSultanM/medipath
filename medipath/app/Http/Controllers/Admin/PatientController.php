<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PatientRequest;
use App\Models\Patient;
use App\Services\ActivityLogService;
use App\Services\AdminPatientService;
use App\Support\ActivityActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Patient directory CRUD + read-only as-patient portal preview (admin/05). */
class PatientController extends Controller
{
    public function __construct(
        private readonly AdminPatientService $patients,
        private readonly ActivityLogService $activity,
    ) {}

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $scope = (string) $request->query('scope', 'active');
        $tab = $request->query('tab') === 'portal' ? 'portal' : 'directory';
        $portalId = (int) $request->query('portal', 0);

        $patients = Patient::query()
            ->with('user:id,name,email,status,archived_at')
            ->when($scope === 'archived', fn ($query) => $query->whereNotNull('archived_at'))
            ->when($scope === 'active', fn ($query) => $query->whereNull('archived_at'))
            ->search($q)
            ->orderBy('patient_code')
            ->paginate(15)
            ->withQueryString();

        $portal = null;
        $portalTarget = null;

        if ($tab === 'portal' && $portalId > 0) {
            $portalTarget = Patient::query()
                ->with('user')
                ->find($portalId);

            if ($portalTarget) {
                $portal = $this->patients->portalData($portalTarget);

                $this->activity->record(ActivityActions::PATIENT_PORTAL_VIEWED, $portalTarget, [
                    'patient_id' => $portalTarget->id,
                    'read_only' => true,
                ], $request->user());
            }
        }

        $forced = (string) $request->query('state', '');
        $state = in_array($forced, ['success', 'skeleton', 'empty', 'error'], true)
            ? $forced
            : ($patients->total() === 0 ? 'empty' : 'success');

        return Inertia::render('Admin/Patients', [
            'patients' => $patients->getCollection()->map(fn (Patient $patient): array => [
                'id' => $patient->id,
                'code' => $patient->patient_code,
                'name' => $patient->name,
                'initials' => $patient->initials,
                'age' => $patient->age,
                'gender' => $patient->gender,
                'phone' => $patient->phone,
                'bloodGroup' => $patient->blood_group,
                'condition' => $patient->condition,
                'address' => $patient->address,
                'isWalkIn' => $patient->is_walk_in,
                'walkInCode' => $patient->walk_in_code,
                'accountStatus' => $patient->user?->status?->value,
                'accountStatusLabel' => $patient->user?->status?->label(),
                'accountEmail' => $patient->user?->email,
                'archivedAt' => $patient->archived_at?->toIso8601String(),
                'dependencies' => $this->patients->dependencyCounts($patient),
            ])->values(),
            'meta' => [
                'current_page' => $patients->currentPage(),
                'last_page' => $patients->lastPage(),
                'per_page' => $patients->perPage(),
                'total' => $patients->total(),
                'from' => $patients->firstItem(),
                'to' => $patients->lastItem(),
            ],
            'filters' => ['q' => $q, 'scope' => $scope],
            'genders' => config('medipath.registration.genders'),
            'tab' => $tab,
            'portalId' => $portalId,
            'portal' => $portal,
            'dataState' => $state,
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function store(PatientRequest $request): RedirectResponse
    {
        $patient = $this->patients->create($request->validated(), $request->user());

        return redirect()->route('admin.patients.index')
            ->with('success', "Patient {$patient->patient_code} registered. A temporary-password notification was sent.");
    }

    public function update(PatientRequest $request, Patient $patient): RedirectResponse
    {
        $this->patients->update($patient, $request->validated(), $request->user());

        return back()->with('success', 'Patient record updated.');
    }

    public function archive(Request $request, Patient $patient): RedirectResponse
    {
        $validated = $request->validate([
            'confirm_name' => ['required', 'string', 'max:160'],
        ]);

        $this->patients->archive($patient, (string) $validated['confirm_name'], $request->user());

        return back()->with('success', 'Patient archived (soft delete). Clinical and billing records are preserved.');
    }

    public function restore(Request $request, Patient $patient): RedirectResponse
    {
        $this->patients->restore($patient, $request->user());

        return back()->with('success', 'Patient restored.');
    }

    public function portal(Request $request, Patient $patient): Response
    {
        $this->activity->record(ActivityActions::PATIENT_PORTAL_VIEWED, $patient, [
            'patient_id' => $patient->id,
            'read_only' => true,
        ], $request->user());

        return Inertia::render('Admin/PatientPortal', [
            'portal' => $this->patients->portalData($patient),
            'dataState' => 'success',
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }
}