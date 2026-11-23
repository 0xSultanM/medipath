<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use App\Models\UserPreference;
use App\Support\ActivityActions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Admin patient CRUD and read-only as-patient portal preview. */
class AdminPatientService
{
    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly NotificationService $notifications,
        private readonly SettingsService $settings,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): Patient
    {
        $patient = DB::transaction(function () use ($data, $actor): Patient {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => Str::lower((string) $data['email']),
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'role' => UserRole::Patient,
                'status' => AccountStatus::Approved,
                'verification_status' => VerificationStatus::Verified,
                'email_verified_at' => now(),
                'locale' => $this->settings->locale(),
                'timezone' => $this->settings->timezone(),
            ]);

            UserPreference::query()->create([
                'user_id' => $user->id,
                'theme' => 'system',
                'language' => $this->settings->locale(),
                'timezone' => $this->settings->timezone(),
            ]);

            $patient = Patient::query()->create([
                'user_id' => $user->id,
                'patient_code' => 'PT-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'age' => $data['age'] ?? null,
                'gender' => $data['gender'] ?? null,
                'blood_group' => $data['blood_group'] ?? null,
                'condition' => $data['condition'] ?? null,
                'address' => $data['address'] ?? null,
            ]);

            $this->activity->record(ActivityActions::USER_CREATED, $user, [
                'role' => 'patient',
                'source' => 'admin_patient_crud',
                'patient_id' => $patient->id,
            ], $actor);

            return $patient;
        });

        $this->notifications->send(
            $patient->user,
            'account.created',
            'Your patient record is ready',
            'An administrator registered your patient record. Sign in with the temporary password from the clinic.',
            'account',
            [],
            '/login',
        );

        return $patient->fresh(['user']);
    }

    /** @param array<string, mixed> $data */
    public function update(Patient $patient, array $data, User $actor): Patient
    {
        DB::transaction(function () use ($patient, $data, $actor): void {
            $patient->forceFill([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'age' => $data['age'] ?? null,
                'gender' => $data['gender'] ?? null,
                'blood_group' => $data['blood_group'] ?? null,
                'condition' => $data['condition'] ?? null,
                'address' => $data['address'] ?? null,
            ])->save();

            $patient->user->forceFill([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
            ])->save();

            $this->activity->record(ActivityActions::USER_UPDATED, $patient, [
                'source' => 'admin_patient_crud',
            ], $actor);
        });

        return $patient->fresh(['user']);
    }

    /** @return array<string, int> */
    public function dependencyCounts(Patient $patient): array
    {
        return [
            'appointments' => Appointment::query()->where('patient_id', $patient->id)->count(),
            'prescriptions' => Prescription::query()->where('patient_id', $patient->id)->count(),
            'invoices' => Invoice::query()->where('patient_id', $patient->id)->count(),
        ];
    }

    public function archive(Patient $patient, string $confirmName, User $actor): Patient
    {
        if (trim($confirmName) !== $patient->name) {
            throw ValidationException::withMessages([
                'confirm_name' => 'Type the patient name exactly to confirm archive.',
            ]);
        }

        DB::transaction(function () use ($patient, $actor): void {
            $patient->forceFill(['archived_at' => now()])->save();
            $patient->user->forceFill([
                'status' => AccountStatus::Archived,
                'archived_at' => now(),
                'session_version' => $patient->user->session_version + 1,
            ])->save();

            DB::table('sessions')->where('user_id', $patient->user_id)->delete();

            $this->activity->record(ActivityActions::USER_ARCHIVED, $patient, [
                'source' => 'admin_patient_crud',
                'dependency_counts' => $this->dependencyCounts($patient),
            ], $actor);
        });

        return $patient->fresh(['user']);
    }

    public function restore(Patient $patient, User $actor): Patient
    {
        DB::transaction(function () use ($patient, $actor): void {
            $patient->forceFill(['archived_at' => null])->save();
            $patient->user->forceFill([
                'status' => AccountStatus::Approved,
                'archived_at' => null,
                'session_version' => $patient->user->session_version + 1,
            ])->save();

            $this->activity->record(ActivityActions::USER_RESTORED, $patient, [
                'event' => 'restored',
                'source' => 'admin_patient_crud',
            ], $actor);
        });

        return $patient->fresh(['user']);
    }

    /** @return array<string, mixed> */
    public function portalData(Patient $patient): array
    {
        return [
            'patient' => [
                'id' => $patient->id,
                'code' => $patient->patient_code,
                'name' => $patient->name,
                'age' => $patient->age,
                'gender' => $patient->gender,
                'phone' => $patient->phone,
                'condition' => $patient->condition,
                'accountStatus' => $patient->user?->status->value,
                'accountStatusLabel' => $patient->user?->status->label(),
            ],
            'prescriptions' => $patient->prescriptions()->latest()->limit(10)->get()->map(fn (Prescription $rx) => [
                'id' => $rx->id,
                'code' => $rx->prescription_code,
                'status' => $rx->status->value,
                'statusLabel' => $rx->status->label(),
                'createdAt' => $rx->created_at?->toIso8601String(),
            ]),
            'invoices' => $patient->invoices()->latest()->limit(10)->get()->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'code' => $invoice->invoice_code,
                'netTotal' => app(CurrencyFormatter::class)->format($invoice->net_total, $invoice->currency_code),
                'paymentStatus' => $invoice->payment_status->value,
                'paymentStatusLabel' => $invoice->payment_status->label(),
                'createdAt' => $invoice->created_at?->toIso8601String(),
            ]),
            'allergies' => $patient->allergies()->get()->map(fn ($allergy) => [
                'id' => $allergy->id,
                'substance' => $allergy->substance,
                'severity' => $allergy->severity->value,
                'severityLabel' => $allergy->severity->label(),
            ]),
            'medications' => $patient->medications()->where('is_active', true)->get()->map(fn ($med) => [
                'id' => $med->id,
                'name' => $med->medicine_name,
                'dosage' => $med->dosage,
                'frequency' => $med->frequency,
            ]),
        ];
    }
}