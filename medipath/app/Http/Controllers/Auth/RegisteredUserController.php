<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Specialty;
use App\Services\ActivityLogService;
use App\Services\RegistrationService;
use App\Support\ActivityActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/** Guest registration UI + atomic service dispatch (PLAN Sprint 1.2). */
class RegisteredUserController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registrations,
        private readonly ActivityLogService $activity,
    ) {}

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            return redirect()->route(
                $user->status === AccountStatus::Approved ? $user->dashboard_route : 'pending.approval'
            );
        }

        return Inertia::render('Guest/Register', [
            'specialties' => Specialty::query()->active()->get(['id', 'name']),
            'authorities' => config('medipath.registration.license_authorities'),
            'genders' => config('medipath.registration.genders'),
            'roles' => [
                'patient' => config('medipath.roles.patient.label'),
                'doctor' => config('medipath.roles.doctor.label'),
                'pharmacist' => config('medipath.roles.pharmacist.label'),
            ],
            'passwordMin' => (int) config('medipath.security.password_min'),
            'demoWalkInCode' => app()->environment('local') ? 'WI-9001' : null,
            'preselectedRole' => in_array($request->query('role'), ['patient', 'doctor', 'pharmacist'], true)
                ? $request->query('role')
                : 'patient',
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = $this->registrations->register(
            $request->validated(),
            $request->file('credential'),
            $request,
        );

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('auth.password_changed_at', $user->password_changed_at?->getTimestamp());
        $request->session()->put('auth.session_version', (int) $user->session_version);

        $this->activity->record(ActivityActions::AUTH_LOGIN, $user, ['event' => 'auto_after_registration'], $user);

        if ($user->status !== AccountStatus::Approved) {
            return redirect()->route('pending.approval')
                ->with('success', 'Account created. Your application is now with the clinic administrator.');
        }

        return redirect()->route($user->dashboard_route)
            ->with('success', 'Welcome to MediPath. Your patient portal is ready.');
    }
}
