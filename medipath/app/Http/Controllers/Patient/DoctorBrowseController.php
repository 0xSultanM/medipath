<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Services\DoctorAvailabilityService;
use App\Services\DoctorPresenter;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/** Authenticated doctor browsing with booking context (PLAN Sprint 4.4). */
class DoctorBrowseController extends Controller
{
    public function __construct(
        private readonly DoctorAvailabilityService $availability,
        private readonly DoctorPresenter $presenter,
    ) {}

    public function __invoke(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $specialty = (string) $request->query('specialty', '');
        $today = $request->boolean('today');
        $rating = min(5, max(0, (float) $request->query('min_rating', 0)));
        $sort = (string) $request->query('sort', 'next_available');

        $doctors = Doctor::query()
            ->verified()
            ->whereHas('user', fn ($query) => $query->where('status', 'approved')->whereNull('archived_at'))
            ->with(['user', 'specialty'])
            ->withAvg(['reviews as avg_rating' => fn ($query) => $query->where('moderation_status', 'approved')], 'rating')
            ->withCount(['reviews as approved_reviews_count' => fn ($query) => $query->where('moderation_status', 'approved')])
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                $query->where(fn ($inner) => $inner
                    ->whereHas('user', fn ($u) => $u->where('name', 'like', $like))
                    ->orWhereHas('specialty', fn ($s) => $s->where('name', 'like', $like))
                    ->orWhere('clinic_name', 'like', $like)
                    ->orWhere('bio', 'like', $like));
            })
            ->when($specialty !== '', fn ($query) => $query->whereHas('specialty', fn ($s) => $s->where('slug', $specialty)))
            ->orderBy('id')
            ->limit(200)
            ->get();

        $rows = $doctors->map(function (Doctor $doctor) {
            $days = $this->availability->forDoctor($doctor, null, 7);
            $next = $this->availability->nextAvailable($doctor, 14);

            return [
                'doctor' => $this->presenter->summary($doctor, $next),
                'hasToday' => ($days[0]['open_slots'] ?? 0) > 0,
                'nextAt' => $next,
                'fee' => (float) $doctor->fee,
            ];
        });

        $filtered = $rows
            ->when($rating > 0, fn (Collection $items) => $items->filter(fn (array $row) => $row['doctor']['rating'] >= $rating))
            ->when($today, fn (Collection $items) => $items->filter(fn (array $row) => $row['hasToday']))
            ->values();

        $filtered = match ($sort) {
            'rating' => $filtered->sortByDesc(fn (array $row) => $row['doctor']['rating'])->values(),
            'experience' => $filtered->sortByDesc(fn (array $row) => $row['doctor']['experienceYears'])->values(),
            'fee' => $filtered->sortBy(fn (array $row) => $row['fee'])->values(),
            default => $filtered->sortBy(fn (array $row) => $row['nextAt']?->getTimestamp() ?? PHP_INT_MAX)->values(),
        };

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 9;
        $total = $filtered->count();
        $items = $filtered->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => route('patient.doctors'),
            'query' => $request->query(),
        ]);

        $tags = [];
        if ($q !== '') {
            $tags[] = ['key' => 'q', 'label' => 'Search', 'valueLabel' => $q];
        }
        if ($specialty !== '') {
            $name = Specialty::query()->where('slug', $specialty)->value('name') ?? $specialty;
            $tags[] = ['key' => 'specialty', 'label' => 'Specialty', 'valueLabel' => $name];
        }
        if ($rating > 0) {
            $tags[] = ['key' => 'min_rating', 'label' => 'Rating', 'valueLabel' => $rating.'+'];
        }
        if ($today) {
            $tags[] = ['key' => 'today', 'label' => 'Available today'];
        }

        return Inertia::render('Patient/Doctors', [
            'doctors' => $items->pluck('doctor')->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'specialties' => Specialty::query()->active()->orderBy('name')->get(['id', 'name', 'slug']),
            'filters' => ['q' => $q, 'specialty' => $specialty, 'min_rating' => $rating, 'today' => $today, 'sort' => $sort],
            'tags' => $tags,
            'dataState' => $total === 0 ? 'empty' : 'success',
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }
}