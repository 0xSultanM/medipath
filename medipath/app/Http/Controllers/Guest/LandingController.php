<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\EmergencyContact;
use App\Models\Specialty;
use App\Services\DoctorPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Guest landing page (PLAN Sprint 1.1, prototypes/guest/01-landing.html). */
class LandingController extends Controller
{
    public function __invoke(Request $request, DoctorPresenter $presenter): Response
    {
        $doctors = Doctor::query()
            ->verified()
            ->whereHas('user', fn ($query) => $query->where('status', 'approved')->whereNull('archived_at'))
            ->with(['user', 'specialty'])
            ->withAvg(['reviews as avg_rating' => fn ($query) => $query->where('moderation_status', 'approved')], 'rating')
            ->withCount(['reviews as approved_reviews_count' => fn ($query) => $query->where('moderation_status', 'approved')])
            ->where('is_featured', true)
            ->orderByDesc('verified_at')
            ->limit(3)
            ->get();

        $forced = $this->forcedState($request);
        $state = $forced ?? ($doctors->isEmpty() ? 'empty' : 'success');

        return Inertia::render('Guest/Landing', [
            'featuredDoctors' => $doctors->map(fn (Doctor $doctor) => $presenter->summary($doctor))->values(),
            'specialties' => Specialty::query()->active()->get(['id', 'name', 'slug']),
            'emergencyContact' => EmergencyContact::query()->active()->first(),
            'dataState' => $state,
            'today' => now()->toDateString(),
        ]);
    }

    private function forcedState(Request $request): ?string
    {
        $state = (string) $request->query('state', '');

        return in_array($state, ['success', 'skeleton', 'empty', 'error'], true) ? $state : null;
    }
}
