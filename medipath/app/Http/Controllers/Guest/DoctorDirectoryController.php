<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Services\DoctorAvailabilityService;
use App\Services\DoctorPresenter;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public doctor directory (PLAN Sprint 1.4). All filtering, sorting and
 * pagination are server-side; the URL is the single source of truth.
 */
class DoctorDirectoryController extends Controller
{
    public function __construct(
        private readonly DoctorAvailabilityService $availability,
        private readonly DoctorPresenter $presenter,
    ) {}

    public function __invoke(Request $request): Response
    {
        $filters = $this->filters($request);

        $doctors = Doctor::query()
            ->verified()
            ->whereHas('user', fn ($query) => $query->where('status', 'approved')->whereNull('archived_at'))
            ->with(['user', 'specialty'])
            ->withAvg(['reviews as avg_rating' => fn ($query) => $query->where('moderation_status', 'approved')], 'rating')
            ->withCount(['reviews as approved_reviews_count' => fn ($query) => $query->where('moderation_status', 'approved')])
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $filters['q']).'%';
                $query->where(function ($inner) use ($like) {
                    $inner->whereHas('user', fn ($user) => $user->where('name', 'like', $like))
                        ->orWhereHas('specialty', fn ($specialty) => $specialty->where('name', 'like', $like))
                        ->orWhere('clinic_name', 'like', $like)
                        ->orWhere('bio', 'like', $like);
                });
            })
            ->when($filters['specialty'] !== '', function ($query) use ($filters) {
                $query->whereHas('specialty', fn ($specialty) => $specialty->where('slug', $filters['specialty']));
            })
            ->orderBy('id')
            ->limit(200)
            ->get();

        $rows = $doctors->map(function (Doctor $doctor) {
            $days = $this->availability->forDoctor($doctor, null, 7);
            $today = $days[0] ?? null;
            $availableDays = collect($days)
                ->filter(fn (array $day) => $day['open_slots'] > 0)
                ->pluck('day_of_week')
                ->unique()
                ->values()
                ->all();
            $openDates = collect($days)
                ->filter(fn (array $day) => $day['open_slots'] > 0)
                ->pluck('date')
                ->all();

            $next = $this->availability->nextAvailable($doctor, 14);

            return [
                'doctor' => $doctor,
                'summary' => $this->presenter->summary($doctor, $next),
                'next_at' => $next,
                'available_today' => (($today['open_slots'] ?? 0) > 0) || (($today['status'] ?? null) === 'available'),
                'available_days' => $availableDays,
                'open_dates' => $openDates,
            ];
        });

        $filtered = $rows
            ->when($filters['min_rating'] > 0, fn (Collection $items) => $items->filter(
                fn (array $row) => $row['summary']['rating'] >= $filters['min_rating']
            ))
            ->when($filters['available_today'], fn (Collection $items) => $items->filter(
                fn (array $row) => $row['available_today']
            ))
            ->when($filters['weekdays'] !== [], fn (Collection $items) => $items->filter(
                fn (array $row) => count(array_intersect($row['available_days'], $filters['weekdays'])) > 0
            ))
            ->when($filters['date'] !== null, function (Collection $items) use ($filters) {
                $target = Carbon::parse($filters['date']);
                $withinWindow = $target->betweenIncluded(today(), today()->addDays(6));

                return $items->filter(fn (array $row) => $withinWindow
                    ? in_array($target->toDateString(), $row['open_dates'], true)
                    : in_array($target->dayOfWeek, $row['available_days'], true));
            })
            ->values();

        $filtered = match ($filters['sort']) {
            'rating' => $filtered->sortByDesc(fn (array $row) => $row['summary']['rating'])->values(),
            'experience' => $filtered->sortByDesc(fn (array $row) => $row['summary']['experienceYears'])->values(),
            'fee' => $filtered->sortBy(fn (array $row) => (float) $row['doctor']->fee)->values(),
            default => $filtered->sortBy(fn (array $row) => $row['next_at']?->getTimestamp() ?? PHP_INT_MAX)->values(),
        };

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 9;
        $total = $filtered->count();
        $items = $filtered->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => route('doctors'),
            'query' => $request->query(),
        ]);

        $forced = $this->forcedState($request);
        $state = $forced ?? ($total === 0 ? 'empty' : 'success');

        return Inertia::render('Guest/Directory', [
            'doctors' => $items->pluck('summary')->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'specialties' => Specialty::query()->active()->get(['id', 'name', 'slug']),
            'filters' => $filters,
            'tags' => $this->tags($filters, $request),
            'dataState' => $state,
            'weekdayOptions' => [
                ['value' => 1, 'label' => 'Mon'],
                ['value' => 2, 'label' => 'Tue'],
                ['value' => 3, 'label' => 'Wed'],
                ['value' => 4, 'label' => 'Thu'],
                ['value' => 5, 'label' => 'Fri'],
                ['value' => 6, 'label' => 'Sat'],
                ['value' => 0, 'label' => 'Sun'],
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        $weekdays = $request->input('weekdays', []);
        if (is_string($weekdays)) {
            $weekdays = explode(',', $weekdays);
        }
        $weekdays = collect(is_array($weekdays) ? $weekdays : [])
            ->map(fn ($day) => (int) $day)
            ->filter(fn (int $day) => $day >= 0 && $day <= 6)
            ->unique()
            ->values()
            ->all();

        $sort = (string) $request->query('sort', 'next_available');

        return [
            'q' => trim((string) $request->query('q', '')),
            'specialty' => (string) $request->query('specialty', ''),
            'min_rating' => min(5, max(0, (float) $request->query('min_rating', 0))),
            'available_today' => $request->boolean('today'),
            'weekdays' => $weekdays,
            'sort' => in_array($sort, ['next_available', 'rating', 'experience', 'fee'], true) ? $sort : 'next_available',
            'date' => $this->validDate($request->query('date')),
        ];
    }

    /** @return array<int, array{key:string,label:string,valueLabel?:string}> */
    private function tags(array $filters, Request $request): array
    {
        $tags = [];

        if ($filters['q'] !== '') {
            $tags[] = ['key' => 'q', 'label' => 'Search', 'valueLabel' => $filters['q']];
        }

        if ($filters['specialty'] !== '') {
            $specialtyName = Specialty::query()->where('slug', $filters['specialty'])->value('name') ?? $filters['specialty'];
            $tags[] = ['key' => 'specialty', 'label' => 'Specialty', 'valueLabel' => $specialtyName];
        }

        if ($filters['min_rating'] > 0) {
            $tags[] = ['key' => 'min_rating', 'label' => 'Rating', 'valueLabel' => $filters['min_rating'].'+'];
        }

        if ($filters['available_today']) {
            $tags[] = ['key' => 'today', 'label' => 'Available today'];
        }

        if ($filters['weekdays'] !== []) {
            $names = [0 => 'Sun', 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat'];
            $tags[] = [
                'key' => 'weekdays',
                'label' => 'Available on',
                'valueLabel' => collect($filters['weekdays'])->map(fn ($day) => $names[$day] ?? '')->implode(', '),
            ];
        }

        if ($filters['date'] !== null) {
            $tags[] = [
                'key' => 'date',
                'label' => 'Preferred date',
                'valueLabel' => Carbon::parse($filters['date'])->format('d M Y'),
            ];
        }

        return $tags;
    }

    private function validDate(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = Carbon::createFromFormat('Y-m-d', $value);

        return $date && $date->toDateString() === $value && $date->gte(today()) ? $value : null;
    }

    private function forcedState(Request $request): ?string
    {
        $state = (string) $request->query('state', '');

        return in_array($state, ['success', 'skeleton', 'empty', 'error'], true) ? $state : null;
    }
}
