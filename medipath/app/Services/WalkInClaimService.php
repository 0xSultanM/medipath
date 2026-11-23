<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\PatientMedication;
use App\Models\Prescription;
use App\Models\Review;
use App\Models\User;
use App\Support\ActivityActions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Safe walk-in claim (PLAN Sprint 1.2). Only a walk-in record created by the
 * clinic (placeholder user @walkin.medipath.local) can be claimed. We never
 * repoint an existing patient.user_id (that link is immutable); instead the
 * clinical history is moved to the new account and the placeholder is archived.
 */
class WalkInClaimService
{
    public function __construct(private readonly ActivityLogService $activity) {}

    public function claim(Patient $newPatient, string $code, User $actor): void
    {
        $walkIn = Patient::query()->where('walk_in_code', $code)->first();

        if (! $walkIn) {
            throw ValidationException::withMessages([
                'claim_code' => 'That claim code was not found. Check the receipt or leave the field empty.',
            ]);
        }

        $placeholder = $walkIn->user;

        if (! $placeholder || ! str_ends_with((string) $placeholder->email, '@walkin.medipath.local')) {
            throw ValidationException::withMessages([
                'claim_code' => 'That claim code is already linked to an active account. Contact the clinic desk for help.',
            ]);
        }

        DB::transaction(function () use ($newPatient, $walkIn, $actor, $code): void {
            Appointment::query()->where('patient_id', $walkIn->id)->update(['patient_id' => $newPatient->id]);
            Prescription::query()->where('patient_id', $walkIn->id)->update(['patient_id' => $newPatient->id]);
            Invoice::query()->where('patient_id', $walkIn->id)->update(['patient_id' => $newPatient->id]);
            Review::query()->where('patient_id', $walkIn->id)->update(['patient_id' => $newPatient->id]);
            PatientMedication::query()->where('patient_id', $walkIn->id)->update(['patient_id' => $newPatient->id]);

            foreach ($walkIn->allergies()->get() as $allergy) {
                PatientAllergy::query()->updateOrCreate(
                    ['patient_id' => $newPatient->id, 'substance' => $allergy->substance],
                    [
                        'allergy_type' => $allergy->allergy_type,
                        'severity' => $allergy->severity,
                        'reaction_notes' => $allergy->reaction_notes,
                        'recorded_by' => $allergy->recorded_by,
                    ],
                );
            }
            PatientAllergy::query()->where('patient_id', $walkIn->id)->delete();

            $walkIn->forceFill([
                'walk_in_code' => null,
                'archived_at' => now(),
            ])->save();

            $newPatient->forceFill([
                'is_walk_in' => true,
                'walk_in_code' => null,
            ])->save();

            $this->activity->record(ActivityActions::USER_UPDATED, $newPatient, [
                'event' => 'walk_in_claim',
                'claimed_patient_code' => $walkIn->patient_code,
                'claim_code' => $code,
            ], $actor);
        });
    }
}
