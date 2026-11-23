<?php

namespace App\Services;

use App\Enums\PrescriptionStatus;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DrugInteraction;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\PatientMedication;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use App\Support\ActivityActions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Allergy + interaction + stock safety gate for prescribing (PLAN Sprint 6.8).
 * The same checks run on the live panel and again inside the save transaction.
 */
class PrescriptionSafetyService
{
    private const ALLERGY_ALIASES = [
        'penicillin' => ['amoxicillin', 'ampicillin', 'penicillin', 'co-amoxiclav', 'amoxicillin-clavulanate', 'flucloxacillin'],
        'sulfa' => ['sulfamethoxazole', 'sulfasalazine', 'sulfadiazine', 'sulfa'],
        'nsaid' => ['ibuprofen', 'naproxen', 'diclofenac', 'aspirin', 'ketorolac'],
        'aspirin' => ['aspirin', 'acetylsalicylic'],
        'codeine' => ['codeine', 'tramadol'],
    ];

    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly NotificationService $notifications,
        private readonly MedicationSafetyAiService $ai,
    ) {}

    /** @param array<int, array<string, mixed>> $items @return array<string, mixed> */
    public function evaluate(Patient $patient, array $items): array
    {
        $medicines = Medicine::query()
            ->whereIn('id', collect($items)->pluck('medicine_id')->filter()->map(fn ($id) => (int) $id))
            ->get()
            ->keyBy('id');

        $allergies = PatientAllergy::query()->where('patient_id', $patient->id)->get();
        $allergyConflicts = [];
        $stock = [];

        foreach ($items as $item) {
            $medicine = $medicines->get((int) ($item['medicine_id'] ?? 0));
            if (! $medicine) {
                continue;
            }

            $haystack = Str::lower($medicine->name.' '.($medicine->generic_name ?? '').' '.($medicine->category ?? ''));
            foreach ($allergies as $allergy) {
                $substance = Str::lower(trim($allergy->substance));
                if ($substance === '') {
                    continue;
                }

                $classMatch = false;
                foreach (self::ALLERGY_ALIASES as $class => $aliases) {
                    if (str_contains($substance, $class) || in_array($substance, $aliases, true)) {
                        foreach ($aliases as $alias) {
                            if (str_contains($haystack, $alias)) {
                                $classMatch = true;
                                break 2;
                            }
                        }
                    }
                }

                if (str_contains($haystack, $substance) || $classMatch) {
                    $allergyConflicts[] = [
                        'medicine' => $medicine->name.' '.$medicine->strength,
                        'substance' => $allergy->substance,
                        'severity' => $allergy->severity?->value,
                        'severityLabel' => $allergy->severity?->label(),
                        'classMatch' => $classMatch,
                        'notes' => $allergy->reaction_notes,
                    ];
                }
            }

            $quantity = (int) ($item['quantity'] ?? 0);
            if ($medicine->stock_quantity <= 0) {
                $stock[] = ['medicine' => $medicine->name, 'kind' => 'out', 'available' => 0, 'requested' => $quantity];
            } elseif ($quantity > $medicine->stock_quantity) {
                $stock[] = ['medicine' => $medicine->name, 'kind' => 'insufficient', 'available' => $medicine->stock_quantity, 'requested' => $quantity];
            }
            if ($medicine->expiry_status === 'expired') {
                $stock[] = ['medicine' => $medicine->name, 'kind' => 'expired', 'available' => $medicine->stock_quantity, 'requested' => $quantity];
            }
        }

        $interactions = $this->interactions($items, $medicines, $patient);
        $stockBlocked = collect($stock)->contains(fn (array $s): bool => in_array($s['kind'], ['out', 'insufficient', 'expired'], true));
        $blocked = $allergyConflicts !== [] || $stockBlocked || collect($interactions)->contains(fn (array $i): bool => $i['severity'] === 'contraindicated');
        $expired = collect($stock)->contains(fn (array $s): bool => $s['kind'] === 'expired');

        return [
            'allergies' => $allergyConflicts,
            'interactions' => $interactions,
            'stock' => $stock,
            'blocked' => $blocked || $expired,
            'expired' => $expired,
            'requiresOverride' => $allergyConflicts !== [] || collect($interactions)->contains(fn (array $i): bool => $i['severity'] === 'contraindicated'),
            'checkedAt' => now()->toIso8601String(),
        ];
    }

    /** @param array<int, array<string, mixed>> $items @param array<string, mixed> $data */
    public function prescribe(Doctor $doctor, Patient $patient, array $items, array $data, User $actor): Prescription
    {
        $this->assertAccess($doctor, $patient, isset($data['appointment_id']) ? (int) $data['appointment_id'] : null);

        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Add at least one medicine.']);
        }

        $safety = $this->evaluate($patient, $items);
        $override = trim((string) ($data['override_justification'] ?? ''));

        if ($safety['blocked'] && mb_strlen($override) < 20) {
            throw ValidationException::withMessages([
                'override_justification' => 'A clinical justification of at least 20 characters is required to override this safety block.',
            ]);
        }

        if ($safety['expired']) {
            throw ValidationException::withMessages(['items' => 'An expired medicine cannot be prescribed.']);
        }
        if (collect($safety['stock'])->contains(fn (array $s): bool => in_array($s['kind'], ['out', 'insufficient'], true))) {
            throw ValidationException::withMessages(['items' => 'Requested quantity exceeds available pharmacy stock.']);
        }

        $ai = (bool) ($data['ai_requested'] ?? false)
            ? $this->ai->summarize($patient, $items, $safety)
            : ['summary' => $this->summary($safety), 'status' => 'not_requested', 'source' => 'deterministic'];

        return DB::transaction(function () use ($doctor, $patient, $items, $data, $actor, $safety, $override, $ai): Prescription {
            $prescription = Prescription::query()->create([
                'prescription_code' => 'RX-'.Str::upper(Str::random(6)),
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'appointment_id' => $data['appointment_id'] ?? null,
                'status' => PrescriptionStatus::PendingDispense,
                'clinical_notes' => $data['clinical_notes'] ?? null,
                'pharmacy_notes' => $data['pharmacy_notes'] ?? null,
                'patient_notes' => $data['patient_notes'] ?? null,
                'safety_snapshot' => [
                    ...$safety,
                    'source' => 'deterministic',
                    'generated_at' => now()->toIso8601String(),
                ],
                'override_justification' => $override !== '' ? $override : null,
                'override_approved_by' => $safety['requiresOverride'] && $override !== '' ? $actor->id : null,
                'ai_safety_summary' => $ai['summary'],
                'ai_status' => $ai['status'],
            ]);

            foreach ($items as $item) {
                $medicine = Medicine::query()->findOrFail((int) $item['medicine_id']);
                PrescriptionItem::query()->create([
                    'prescription_id' => $prescription->id,
                    'medicine_id' => $medicine->id,
                    'dosage' => $item['dosage'],
                    'schedule' => $item['schedule'],
                    'duration' => $item['duration'],
                    'quantity' => (int) $item['quantity'],
                    'instructions' => $item['instructions'] ?? null,
                    'unit_price_snapshot' => $medicine->unit_price,
                ]);
            }

            $this->activity->record(ActivityActions::PRESCRIPTION_CREATED, $prescription, [
                'prescription_code' => $prescription->prescription_code,
                'patient_id' => $patient->id,
                'items' => count($items),
                'override' => $override !== '',
                'blocked_checks' => $safety['blocked'],
            ], $actor);

            if ($patient->user) {
                $this->notifications->send(
                    $patient->user,
                    'prescription.created',
                    'New prescription issued',
                    "Prescription {$prescription->prescription_code} is waiting for pharmacy dispensing.",
                    'prescription',
                    ['prescription_id' => $prescription->id],
                    '/patient/prescriptions',
                );
            }

            return $prescription->fresh(['items.medicine']);
        });
    }

    /** @param array<int, array<string, mixed>> $items @return array<string, mixed> */
    public function aiReview(Patient $patient, array $items): array
    {
        $safety = $this->evaluate($patient, $items);
        $ai = $this->ai->summarize($patient, $items, $safety);

        return ['safety' => $safety, 'ai' => $ai];
    }

    /** @param array<int, array<string, mixed>> $items @param array<int, Medicine> $medicines @return array<int, array<string, mixed>> */
    private function interactions(array $items, $medicines, Patient $patient): array
    {
        $ids = $items ? collect($items)->pluck('medicine_id')->map(fn ($id) => (int) $id)->filter()->all() : [];

        $currentIds = PatientMedication::query()
            ->where('patient_id', $patient->id)
            ->where('is_active', true)
            ->whereNotNull('medicine_id')
            ->pluck('medicine_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $allIds = collect(array_merge($ids, $currentIds))->unique()->values();
        $pairs = [];
        for ($i = 0; $i < $allIds->count(); $i++) {
            for ($j = $i + 1; $j < $allIds->count(); $j++) {
                $pairs[] = DrugInteraction::canonicalPair($allIds[$i], $allIds[$j]);
            }
        }

        if ($pairs === []) {
            return [];
        }

        $rows = DrugInteraction::query()
            ->where(function ($query) use ($pairs) {
                foreach ($pairs as [$a, $b]) {
                    $query->orWhere(fn ($pair) => $pair->where('medicine_a_id', $a)->where('medicine_b_id', $b));
                }
            })
            ->with(['medicineA:id,name,strength,generic_name', 'medicineB:id,name,strength,generic_name'])
            ->get();

        return $rows->map(fn (DrugInteraction $interaction): array => [
            'id' => $interaction->id,
            'a' => trim(optional($interaction->medicineA)->name.' '.optional($interaction->medicineA)->strength),
            'b' => trim(optional($interaction->medicineB)->name.' '.optional($interaction->medicineB)->strength),
            'severity' => $interaction->severity?->value,
            'severityLabel' => $interaction->severity?->label(),
            'description' => $interaction->description,
            'management' => $interaction->management,
            'blocking' => $interaction->severity?->value === 'contraindicated',
        ])->values()->all();
    }

    private function summary(array $safety): string
    {
        if ($safety['allergies'] !== []) {
            return 'Allergy conflict detected; override justification recorded and sealed. Pharmacy must re-verify at dispensing.';
        }
        if ($safety['interactions'] !== []) {
            return 'Pairwise interaction review completed; deterministic findings stored in the safety snapshot.';
        }

        return 'Deterministic allergy, interaction, expiry and stock checks passed.';
    }

    private function assertAccess(Doctor $doctor, Patient $patient, ?int $appointmentId): void
    {
        if ($appointmentId) {
            $owned = Appointment::query()->whereKey($appointmentId)->where('doctor_id', $doctor->id)->where('patient_id', $patient->id)->exists();
            if (! $owned) {
                abort(403);
            }

            return;
        }

        $hasRelationship = Appointment::query()->where('doctor_id', $doctor->id)->where('patient_id', $patient->id)->exists()
            || Prescription::query()->where('doctor_id', $doctor->id)->where('patient_id', $patient->id)->exists();

        abort_unless($hasRelationship, 403, 'You can only prescribe for patients you have treated.');
    }
}