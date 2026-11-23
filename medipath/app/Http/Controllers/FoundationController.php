<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\Faq;
use App\Models\Medicine;
use App\Models\Specialty;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Part 0 foundation status page. Replaced by the designed Landing screen in
 * Phase 1; until then it proves the shell boots and the seed graph is loaded.
 */
class FoundationController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Foundation/Index', [
            'stats' => [
                'specialties' => Specialty::query()->count(),
                'medicines' => Medicine::query()->count(),
                'faqs' => Faq::query()->count(),
                'verifiedDoctors' => Doctor::query()->verified()->count(),
                'users' => User::query()->count(),
            ],
            'demoAccounts' => app()->environment('local') ? User::query()
                ->orderBy('role')
                ->orderBy('name')
                ->get(['name', 'email', 'role', 'status'])
                ->map(fn (User $u) => [
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role->value,
                    'roleLabel' => $u->role->label(),
                    'status' => $u->status->value,
                    'statusLabel' => $u->status->label(),
                ]) : [],
            'roles' => collect(UserRole::cases())->map(fn (UserRole $role) => [
                'value' => $role->value,
                'label' => $role->label(),
                'portal' => $role->portal(),
                'url' => route($role->value === 'admin' ? 'admin.dashboard' : $role->value.'.dashboard'),
            ]),
            'demoPassword' => app()->environment('local') ? config('medipath.demo.password') : null,
        ]);
    }
}
