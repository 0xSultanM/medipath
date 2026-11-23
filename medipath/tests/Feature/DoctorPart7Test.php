<?php

use App\Enums\AppointmentStatus;
use App\Enums\PrescriptionStatus;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Medicine;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use App\Support\ActivityActions;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{0:User,1:Doctor,2:User,3:Patient,4:Appointment} */
function partSevenDoctorPatient(): array
{
    $doctorUser = makeUser(['role' => 'doctor', 'verification_status' => 'verified']);
    $doctor = makeDoctorRecord($doctorUser);
    $patientUser = makeUser(['role' => 'patient', 'name' => 'Part Seven Patient']);
    $patient = makePatientRecord($patientUser);
    $appointment = Appointment::query()->create([
        'appointment_code' => 'APT-P7-'.uniqid(),
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'date' => now()->toDateString(),
        'start_time' => '10:00',
        'end_time' => '10:30',
        'status' => AppointmentStatus::Completed,
        'visit_reason' => 'Prescription history review.',
    ]);

    return [$doctorUser, $doctor, $patientUser, $patient, $appointment];
}

function partSevenMedicine(): Medicine
{
    return Medicine::query()->create([
        'name' => 'Amlodipine',
        'generic_name' => 'Amlodipine besylate',
        'category' => 'Antihypertensive',
        'strength' => '5 mg',
        'form' => 'tablet',
        'unit_price' => 4,
        'stock_quantity' => 250,
        'min_stock_threshold' => 25,
        'expiry_date' => now()->addYear()->toDateString(),
        'batch_number' => 'AMLO-P7',
        'is_active' => true,
    ]);
}

function partSevenPrescription(Doctor $doctor, Patient $patient, string $status = 'pending_dispense'): Prescription
{
    return Prescription::query()->create([
        'prescription_code' => 'RX-P7-'.strtoupper(uniqid()),
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'status' => $status,
        'clinical_notes' => 'History record for Part 7 verification.',
        'ai_safety_summary' => 'Deterministic checks passed.',
        'ai_status' => 'fallback',
    ]);
}

it('renders prescription history and doctor notifications', function () {
    [$doctorUser, $doctor, , $patient] = partSevenDoctorPatient();
    partSevenPrescription($doctor, $patient);
    Notification::query()->create([
        'user_id' => $doctorUser->id,
        'type' => 'appointment.requested',
        'category' => 'appointment',
        'title' => 'New appointment request',
        'message' => 'A patient requested a consultation.',
        'action_url' => '/doctor/requests',
    ]);

    $this->actingAs($doctorUser)->get('/doctor/prescriptions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Doctor/Prescriptions')
            ->has('prescriptions', 1)
            ->where('prescriptions.0.patientName', $patient->name)
            ->has('statuses', 4));

    $this->actingAs($doctorUser)->get('/doctor/notifications')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Doctor/Notifications')
            ->has('notifications', 1)
            ->where('counts.all', 1)
            ->where('counts.unread', 1));

    $this->actingAs($doctorUser)->get('/doctor/notifications?filter=unread')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('filter', 'unread')->has('notifications', 1));
});

it('filters prescription history and never leaks another doctor records', function () {
    [$doctorUser, $doctor, , $patient] = partSevenDoctorPatient();
    partSevenPrescription($doctor, $patient, PrescriptionStatus::PendingDispense->value);
    partSevenPrescription($doctor, $patient, PrescriptionStatus::Dispensed->value);

    $otherDoctorUser = makeUser(['role' => 'doctor', 'verification_status' => 'verified']);
    $otherDoctor = makeDoctorRecord($otherDoctorUser);
    $otherPatientUser = makeUser(['role' => 'patient', 'name' => 'Other Doctor Patient']);
    $otherPatient = makePatientRecord($otherPatientUser);
    partSevenPrescription($otherDoctor, $otherPatient);

    $this->actingAs($doctorUser)->get('/doctor/prescriptions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('prescriptions', 2));

    $this->actingAs($doctorUser)->get('/doctor/prescriptions?status=dispensed')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('prescriptions', 1)
            ->where('prescriptions.0.status', 'dispensed'));

    $this->actingAs($doctorUser)->get('/doctor/prescriptions?q=Other Doctor Patient')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('prescriptions', 0));
});

it('returns a labelled deterministic AI safety fallback', function () {
    [$doctorUser, , , $patient] = partSevenDoctorPatient();
    $medicine = partSevenMedicine();
    app(\App\Services\SettingsService::class)->set('ai_enabled', true);

    $this->actingAs($doctorUser)->postJson('/doctor/prescriptions/ai-review', [
        'patient_id' => $patient->id,
        'items' => [[
            'medicine_id' => $medicine->id,
            'dosage' => '1 tablet',
            'schedule' => 'Once daily',
            'duration' => '30 days',
            'quantity' => 30,
        ]],
    ])->assertOk()
        ->assertJsonPath('ai.status', 'fallback')
        ->assertJsonPath('ai.source', 'deterministic_fallback')
        ->assertJsonPath('safety.blocked', false);

    $response = $this->actingAs($doctorUser)->postJson('/doctor/prescriptions/ai-review', [
        'patient_id' => $patient->id,
        'items' => [['medicine_id' => $medicine->id, 'dosage' => '1 tablet', 'schedule' => 'Once daily', 'duration' => '30 days', 'quantity' => 30]],
    ]);
    expect((string) $response->json('ai.summary'))->toContain('Deterministic');
});

it('stores the AI provenance with the saved prescription', function () {
    [$doctorUser, $doctor, , $patient, $appointment] = partSevenDoctorPatient();
    $medicine = partSevenMedicine();
    app(\App\Services\SettingsService::class)->set('ai_enabled', false);

    $this->actingAs($doctorUser)->post('/doctor/prescriptions', [
        'patient_id' => $patient->id,
        'appointment_id' => $appointment->id,
        'items' => [[
            'medicine_id' => $medicine->id,
            'dosage' => '1 tablet',
            'schedule' => 'Once daily',
            'duration' => '30 days',
            'quantity' => 30,
        ]],
        'clinical_notes' => 'Stage 1 hypertension, continue therapy.',
        'ai_requested' => true,
    ])->assertRedirect();

    $prescription = Prescription::query()->where('doctor_id', $doctor->id)->firstOrFail();
    expect($prescription->ai_status)->toBe('fallback')
        ->and((string) $prescription->ai_safety_summary)->toContain('Deterministic')
        ->and(ActivityLog::query()->where('action', ActivityActions::PRESCRIPTION_CREATED)->exists())->toBeTrue();
});

it('marks only the signed-in doctor notifications as read', function () {
    [$doctorUser] = partSevenDoctorPatient();
    $otherUser = makeUser(['role' => 'patient']);
    $mine = Notification::query()->create([
        'user_id' => $doctorUser->id, 'type' => 'appointment.requested', 'category' => 'appointment',
        'title' => 'Mine', 'message' => 'Mine to read.',
    ]);
    $theirs = Notification::query()->create([
        'user_id' => $otherUser->id, 'type' => 'appointment.requested', 'category' => 'appointment',
        'title' => 'Theirs', 'message' => 'Not mine.',
    ]);

    $this->actingAs($doctorUser)->post("/notifications/{$mine->id}/read")->assertRedirect();
    expect($mine->fresh()->read_at)->not->toBeNull();

    $this->actingAs($doctorUser)->post("/notifications/{$theirs->id}/read")->assertForbidden();
    expect($theirs->fresh()->read_at)->toBeNull();

    $this->actingAs($doctorUser)->post('/notifications/read-all')->assertRedirect();
    expect(Notification::query()->where('user_id', $otherUser->id)->whereNull('read_at')->count())->toBe(1);
});

it('blocks non-doctors from prescription history and notifications', function () {
    $patientUser = makeUser(['role' => 'patient']);
    makePatientRecord($patientUser);

    $this->actingAs($patientUser)->get('/doctor/prescriptions')->assertForbidden();
    $this->actingAs($patientUser)->get('/doctor/notifications')->assertForbidden();
    $this->actingAs($patientUser)->postJson('/doctor/prescriptions/ai-review', [])->assertForbidden();
});