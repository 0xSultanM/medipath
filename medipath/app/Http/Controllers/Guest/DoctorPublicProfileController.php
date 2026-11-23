<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Review;
use App\Services\DoctorAvailabilityService;
use App\Services\DoctorPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Public doctor profile with leave-aware availability and approved reviews. */
class DoctorPublicProfileController extends Controller
{
    public function __construct(
        private readonly DoctorAvailabilityService $availability,
        private readonly DoctorPresenter $presenter,
    ) {}

    public function __invoke(Request $request, string $doctorCode): Response
    {
        $doctor = Doctor::query()
            ->verified()
            ->whereHas('user', fn ($query) => $query->where('status', 'approved')->whereNull('archived_at'))
            ->where('doctor_code', $doctorCode)
            ->with(['user', 'specialty'])
            ->withAvg(['reviews as avg_rating' => fn ($query) => $query->where('moderation_status', 'approved')], 'rating')
            ->withCount(['reviews as approved_reviews_count' => fn ($query) => $query->where('moderation_status', 'approved')])
            ->firstOrFail();

        $reviews = Review::query()
            ->where('doctor_id', $doctor->id)
            ->where('moderation_status', 'approved')
            ->with('patient')
            ->latest()
            ->paginate(5, ['*'], 'reviews_page');

        $distribution = Review::query()
            ->where('doctor_id', $doctor->id)
            ->where('moderation_status', 'approved')
            ->select('rating', DB::raw('count(*) as total'))
            ->groupBy('rating')
            ->pluck('total', 'rating');

        $distributionMap = [];
        foreach (range(1, 5) as $star) {
            $distributionMap[$star] = (int) ($distribution[$star] ?? 0);
        }

        $forced = $this->forcedState($request);
        $profileState = $forced ?? 'success';
        $reviewsState = $forced ?? ($reviews->total() === 0 ? 'empty' : 'success');

        $user = $request->user();
        $bookingGate = match (true) {
            $user === null => [
                'label' => 'Sign in to book',
                'href' => route('login', ['redirect' => route('doctors.show', $doctor->doctor_code)]),
                'note' => 'Booking and reviews require a signed-in patient account.',
                'disabled' => false,
            ],
            $user->role?->value === 'patient' => [
                'label' => 'Continue in patient portal',
                'href' => route('patient.dashboard'),
                'note' => 'The guided booking flow opens in the patient portal.',
                'disabled' => false,
            ],
            default => [
                'label' => 'Patient account required',
                'href' => null,
                'note' => 'Only patient accounts can book consultations.',
                'disabled' => true,
            ],
        };

        return Inertia::render('Guest/DoctorProfile', [
            'doctor' => $this->presenter->profile($doctor),
            'availability' => $this->availability->forDoctor($doctor, null, 7),
            'availabilityState' => $profileState,
            'reviews' => [
                'data' => $reviews->getCollection()->map(fn (Review $review) => [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'createdAt' => $review->created_at?->toIso8601String(),
                    'patientName' => $this->maskName($review->patient?->name ?? 'Patient'),
                    'initials' => $this->initials($review->patient?->name ?? 'Patient'),
                ])->values(),
                'meta' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                    'from' => $reviews->firstItem(),
                    'to' => $reviews->lastItem(),
                ],
            ],
            'ratingSummary' => [
                'average' => round((float) ($doctor->avg_rating ?? 0), 1),
                'total' => (int) ($doctor->approved_reviews_count ?? 0),
                'distribution' => $distributionMap,
            ],
            'reviewsState' => $reviewsState,
            'bookingGate' => $bookingGate,
            'breadcrumbSpecialty' => $doctor->specialty->name,
        ]);
    }

    private function maskName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        if (count($parts) === 1) {
            return $parts[0];
        }

        return $parts[0].' '.mb_substr(end($parts), 0, 1).'.';
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $value = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $value .= mb_substr($part, 0, 1);
        }

        return mb_strtoupper($value !== '' ? $value : 'PT');
    }

    private function forcedState(Request $request): ?string
    {
        $state = (string) $request->query('state', '');

        return in_array($state, ['success', 'skeleton', 'empty', 'error'], true) ? $state : null;
    }
}
