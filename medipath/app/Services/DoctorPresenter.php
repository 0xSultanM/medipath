<?php

namespace App\Services;

use App\Models\Doctor;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/** Shapes a Doctor model into the DoctorCard / profile payload. */
class DoctorPresenter
{
    public function __construct(
        private readonly DoctorAvailabilityService $availability,
        private readonly CurrencyFormatter $money,
    ) {}

    /** @return array<string, mixed> */
    public function summary(Doctor $doctor, ?CarbonInterface $next = null): array
    {
        if ($next === null) {
            $next = $this->availability->nextAvailable($doctor);
        }

        $rating = $doctor->avg_rating !== null
            ? round((float) $doctor->avg_rating, 1)
            : round((float) $doctor->approvedReviews()->avg('rating'), 1);

        $count = isset($doctor->approved_reviews_count)
            ? (int) $doctor->approved_reviews_count
            : $doctor->approvedReviews()->count();

        return [
            'id' => $doctor->id,
            'name' => $doctor->user->name,
            'initials' => $doctor->user->initials,
            'photoUrl' => $doctor->photo_path ? asset('storage/'.$doctor->photo_path) : null,
            'specialty' => $doctor->specialty->name,
            'verified' => $doctor->isVerified(),
            'experienceYears' => $doctor->experience_years,
            'clinic' => $doctor->clinic_name,
            'address' => $doctor->clinic_address,
            'rating' => $rating,
            'reviewCount' => $count,
            'feeFormatted' => $this->money->format($doctor->fee),
            'nextAvailable' => $this->availability->label($next),
            'hasSlots' => $next !== null,
            'profileUrl' => route('doctors.show', $doctor->doctor_code),
        ];
    }

    /** @return array<string, mixed> */
    public function profile(Doctor $doctor): array
    {
        $next = $this->availability->nextAvailable($doctor);

        return [
            ...$this->summary($doctor, $next),
            'doctorCode' => $doctor->doctor_code,
            'licenseAuthority' => $doctor->license_authority,
            'licenseMasked' => '••••'.substr($doctor->license_number, -4),
            'verifiedAt' => $doctor->verified_at?->toDateString(),
            'bio' => $doctor->bio,
            'room' => $doctor->room,
            'followUpFeeFormatted' => $doctor->follow_up_fee ? $this->money->format($doctor->follow_up_fee) : null,
        ];
    }

    public function nextAt(Doctor $doctor, int $days = 14): ?Carbon
    {
        return $this->availability->nextAvailable($doctor, $days);
    }
}
