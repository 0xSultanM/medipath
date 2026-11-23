<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ArchiveUserRequest;
use App\Http\Requests\Admin\ChangeUserRoleRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\SuspendUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Specialty;
use App\Models\User;
use App\Services\AdminUserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Admin user management (PLAN Sprint 2.3, admin/02). */
class UserController extends Controller
{
    public function __construct(private readonly AdminUserService $users) {}

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $role = (string) $request->query('role', '');
        $status = (string) $request->query('status', '');
        $idle = $request->boolean('idle');

        $users = User::query()
            ->with([
                'patient:id,user_id,patient_code',
                'doctor:id,user_id,doctor_code,license_number,specialty_id',
                'doctor.specialty:id,name',
                'pharmacist:id,user_id,pharmacist_code',
            ])
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like));
            })
            ->when(in_array($role, UserRole::values(), true), fn ($query) => $query->where('role', $role))
            ->when(in_array($status, AccountStatus::values(), true), fn ($query) => $query->where('status', $status))
            ->when($idle, fn ($query) => $query->where(fn ($inner) => $inner
                ->whereNull('last_login_at')
                ->orWhere('last_login_at', '<', now()->subDays(90))))
            ->orderByRaw("FIELD(role, 'admin','doctor','pharmacist','patient')")
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $forced = (string) $request->query('state', '');
        $state = in_array($forced, ['success', 'skeleton', 'empty', 'error'], true)
            ? $forced
            : ($users->total() === 0 ? 'empty' : 'success');

        return Inertia::render('Admin/Users', [
            'users' => $users->getCollection()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'initials' => $user->initials,
                'role' => $user->role?->value,
                'roleLabel' => $user->role?->label(),
                'status' => $user->status?->value,
                'statusLabel' => $user->status?->label(),
                'verificationLabel' => $user->verification_status?->label(),
                'lastLoginAt' => $user->last_login_at?->toIso8601String(),
                'createdAt' => $user->created_at?->toIso8601String(),
                'patientCode' => $user->patient?->patient_code,
                'doctorCode' => $user->doctor?->doctor_code,
                'pharmacistCode' => $user->pharmacist?->pharmacist_code,
                'licenseNumber' => $user->doctor?->license_number,
                'specialty' => $user->doctor?->specialty?->name,
                'isSelf' => $user->id === $request->user()->id,
                'isFinalAdmin' => $user->role === UserRole::Admin
                    && User::query()->where('role', UserRole::Admin)
                        ->where('status', AccountStatus::Approved)
                        ->whereKeyNot($user->id)
                        ->doesntExist(),
            ])->values(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ],
            'filters' => [
                'q' => $q,
                'role' => $role,
                'status' => $status,
                'idle' => $idle,
            ],
            'roles' => collect(UserRole::cases())->mapWithKeys(fn (UserRole $r) => [$r->value => $r->label()]),
            'statuses' => collect(AccountStatus::cases())->mapWithKeys(fn (AccountStatus $s) => [$s->value => $s->label()]),
            'specialties' => Specialty::query()->active()->get(['id', 'name']),
            'dataState' => $state,
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->users->createInternal($request->validated(), $request->user());

        return back()->with('success', 'Internal user created.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->users->updateContact($user, $request->validated(), $request->user());

        return back()->with('success', 'User contact details updated.');
    }

    public function changeRole(ChangeUserRoleRequest $request, User $user): RedirectResponse
    {
        $this->users->changeRole($user, $request->validated(), $request->user());

        return back()->with('success', 'Role updated and the user’s sessions were revoked.');
    }

    public function suspend(SuspendUserRequest $request, User $user): RedirectResponse
    {
        $this->users->suspend($user, (string) $request->validated('reason'), $request->user());

        return back()->with('success', 'Account suspended and sessions revoked.');
    }

    public function restore(Request $request, User $user): RedirectResponse
    {
        $this->users->restore($user, $request->user());

        return back()->with('success', 'Account restored.');
    }

    public function archive(ArchiveUserRequest $request, User $user): RedirectResponse
    {
        $this->users->archive($user, (string) $request->validated('confirm_name'), $request->user());

        return back()->with('success', 'Account archived. Linked clinical records are preserved.');
    }
}