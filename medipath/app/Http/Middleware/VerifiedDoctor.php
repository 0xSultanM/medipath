<?php

namespace App\Http\Middleware;

use App\Enums\VerificationStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Doctors may only enter clinical features when verified on both records. */
class VerifiedDoctor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $doctor = $user?->doctor;

        $verified = $user?->role?->value === 'doctor'
            && $user->verification_status === VerificationStatus::Verified
            && $doctor?->verification_status === VerificationStatus::Verified
            && $doctor?->archived_at === null;

        if (! $verified) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Doctor verification is required before clinical access.',
                    'code' => 'doctor_unverified',
                ], 403);
            }

            abort(403, 'Your license has not been verified yet.');
        }

        return $next($request);
    }
}
