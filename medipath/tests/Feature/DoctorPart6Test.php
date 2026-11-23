<?php

use App\Enums\AppointmentStatus;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorLeave;
use App\Models\DoctorSchedule;
use App\Models\DrugInteraction;
use App\Models\Medicine;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use App\Support\ActivityActions;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{0:User,1:Doctor,2:User,3:Patient,4:Appointment} */
function partSixDoctorPatient(): array
{
    $doctorUser = makeUser(['role' => 'doctor', 'verification_status' => 'verified']);
    $doctor = makeDoctorRecord($doctorUser);
    $patientUser = makeUser(['role' => 'patient', 'name' => 'Part Six Patient']);
    $patient = makePatientRecord($patientUser);
    DoctorSchedule::query()->create([
        'doctor_id' => $doctor->id,
        'day_of_week' => now()->addDay()->dayOfWeek,
        'start_time' => '09:00',
        'end_time' => '12:00',
        'slot_duration' => 30,
        'is_active' => true,
    ]);
    $appointment = Appointment::query()->create([
        'appointment_code' => 'APT-P6-'.uniqid(),
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'date' => now()->addDay()->toDateString(),
        'start_time' => '10:00',
        'end_time' => '10:30',
        'status' => AppointmentStatus::Pending,
        'visit_reason' => 'Follow-up consultation for persistent symptoms.',
    ]);

    return [$doctorUser, $doctor, $patientUser, $patient, $appointment];
}

it('renders every doctor workspace screen', function () {
    [$doctorUser, , , $patient, $appointment] = partSixDoctorPatient();

    foreach ([
        '/doctor/dashboard' => 'Doctor/Dashboard',
        '/doctor/schedule' => 'Doctor/Schedule',
        '/doctor/requests' => 'Doctor/Requests',
        '/doctor/patients' => 'Doctor/Patients',
        "/doctor/patients/{$patient->id}" => 'Doctor/PatientDetail',
        '/doctor/walk-in' => 'Doctor/WalkIn',
        "/doctor/prescriptions/new?patient={$patient->id}" => 'Doctor/PrescriptionBuilder',
        '/doctor/reviews' => 'Doctor/Reviews',
        '/doctor/settings' => 'Doctor/Settings',
    ] as $route => $component) {
        $this->actingAs($doctorUser)->get($route)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
    }
});

it('adds weekly blocks and rejects overlapping blocks', function () {
    [$doctorUser, $doctor] = partSixDoctorPatient();

    $this->actingAs($doctorUser)->post('/doctor/schedule/blocks', [
        'day_of_week' => 3,
        'start_time' => '14:00',
        'end_time' => '17:00',
        'slot_duration' => 30,
    ])->assertRedirect();

    expect(DoctorSchedule::query()->where('doctor_id', $doctor->id)->where('day_of_week', 3)->exists())->toBeTrue();

    $this->actingAs($doctorUser)->post('/doctor/schedule/blocks', [
        'day_of_week' => 3,
        'start_time' => '16:00',
        'end_time' => '18:00',
        'slot_duration' => 30,
    ])->assertSessionHasErrors('start_time');
});

it('creates leave atomically and cancels overlapping appointments with notification', function () {
    [$doctorUser, $doctor, $patientUser] = partSixDoctorPatient();

    $this->actingAs($doctorUser)->post('/doctor/schedule/leaves', [
        'start_date' => now()->addDay()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'reason' => 'Medical conference',
    ])->assertRedirect();

    $leave = DoctorLeave::query()->where('doctor_id', $doctor->id)->firstOrFail();
    expect($leave->status)->toBe('approved');
    expect(Appointment::query()->where('doctor_id', $doctor->id)->firstOrFail()->status)->toBe(AppointmentStatus::Cancelled);
    expect(Notification::query()->where('user_id', $patientUser->id)->where('type', 'appointment.cancelled_leave')->exists())->toBeTrue();
});

it('accepts, rejects, completes and cancels owned requests', function () {
    [$doctorUser, $doctor, $patientUser, $patient] = partSixDoctorPatient();

    $pending = Appointment::query()->where('doctor_id', $doctor->id)->firstOrFail();

    $this->actingAs($doctorUser)->post("/doctor/requests/{$pending->id}/accept")->assertRedirect();
    expect($pending->fresh()->status)->toBe(AppointmentStatus::Accepted);

    $this->actingAs($doctorUser)->post("/doctor/requests/{$pending->id}/complete")->assertRedirect();
    expect($pending->fresh()->status)->toBe(AppointmentStatus::Completed);

    $second = Appointment::query()->create([
        'appointment_code' => 'APT-P6-2-'.uniqid(),
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'date' => now()->addDays(2)->toDateString(),
        'start_time' => '11:00',
        'end_time' => '11:30',
        'status' => AppointmentStatus::Pending,
        'visit_reason' => 'Second opinion on chest discomfort.',
    ]);

    $this->actingAs($doctorUser)->post("/doctor/requests/{$second->id}/reject", ['reason' => 'Please see the on-call cardiologist.'])->assertRedirect();
    expect($second->fresh()->status)->toBe(AppointmentStatus::Rejected);

    $third = Appointment::query()->create([
        'appointment_code' => 'APT-P6-3-'.uniqid(),
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'date' => now()->addDays(2)->toDateString(),
        'start_time' => '12:00',
        'end_time' => '12:30',
        'status' => AppointmentStatus::Accepted,
        'visit_reason' => 'Review of home BP log.',
    ]);

    $this->actingAs($doctorUser)->post("/doctor/requests/{$third->id}/cancel", ['reason' => 'Clinic emergency closure.'])->assertRedirect();
    expect($third->fresh()->status)->toBe(AppointmentStatus::Cancelled);

    expect(ActivityLog::query()->where('action', ActivityActions::APPOINTMENT_ACCEPTED)->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('action', ActivityActions::APPOINTMENT_REJECTED)->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('action', ActivityActions::APPOINTMENT_COMPLETED)->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('action', ActivityActions::APPOINTMENT_CANCELLED_DOCTOR)->exists())->toBeTrue();
});

it('scopes patient charts to patients the doctor has treated', function () {
    [$doctorUser, , , $patient] = partSixDoctorPatient();
    $otherDoctorUser = makeUser(['role' => 'doctor', 'verification_status' => 'verified']);
    $otherDoctor = makeDoctorRecord($otherDoctorUser);
    $otherPatientUser = makeUser(['role' => 'patient']);
    $otherPatient = makePatientRecord($otherPatientUser);
    Appointment::query()->create([
        'appointment_code' => 'APT-OTHER-'.uniqid(),
        'patient_id' => $otherPatient->id,
        'doctor_id' => $otherDoctor->id,
        'date' => now()->toDateString(),
        'start_time' => '09:00',
        'end_time' => '09:30',
        'status' => AppointmentStatus::Completed,
        'visit_reason' => 'Other doctor visit.',
    ]);

    $this->actingAs($doctorUser)->get('/doctor/patients')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Doctor/Patients')->has('patients', 1));
    $this->actingAs($doctorUser)->get("/doctor/patients/{$otherPatient->id}")->assertForbidden();
});

it('registers a walk-in atomically and returns a claim code', function () {
    [$doctorUser, $doctor] = partSixDoctorPatient();

    $this->actingAs($doctorUser)->post('/doctor/walk-in', [
        'name' => 'Walk-in Patient',
        'phone' => '+880 1700 123456',
        'age' => 38,
        'gender' => 'Female',
        'condition' => 'Acute ankle sprain after a fall.',
        'date' => now()->addDay()->toDateString(),
        'time' => '11:00',
    ])->assertRedirect();

    $patient = Patient::query()->where('name', 'Walk-in Patient')->firstOrFail();
    expect($patient->is_walk_in)->toBeTrue()
        ->and($patient->walk_in_code)->toStartWith('WI-');

    $appointment = Appointment::query()->where('patient_id', $patient->id)->firstOrFail();
    expect($appointment->status)->toBe(AppointmentStatus::Accepted)
        ->and($appointment->is_walk_in)->toBeTrue();

    expect(ActivityLog::query()->where('action', ActivityActions::WALK_IN_REGISTERED)->exists())->toBeTrue();
});

it('searches live stock and blocks unsafe prescriptions until justified', function () {
    [$doctorUser, $doctor, , $patient, $appointment] = partSixDoctorPatient();
    $medicine = Medicine::query()->create([
        'name' => 'Amoxicillin',
        'generic_name' => 'Amoxicillin',
        'category' => 'Antibiotic',
        'strength' => '500 mg',
        'form' => 'capsule',
        'unit_price' => 10,
        'stock_quantity' => 100,
        'min_stock_threshold' => 20,
        'expiry_date' => now()->addYear()->toDateString(),
        'batch_number' => 'AMOX-P6',
        'is_active' => true,
    ]);
    \App\Models\PatientAllergy::query()->create([
        'patient_id' => $patient->id,
        'substance' => 'Penicillin',
        'allergy_type' => 'medication',
        'severity' => 'severe',
    ]);
    app(\App\Services\SettingsService::class)->set('ai_enabled', false);

    $this->actingAs($doctorUser)->get('/doctor/medicines/search?q=Amox')
        ->assertOk()
        ->assertJsonPath('items.0.name', 'Amoxicillin');

    $items = [[
        'medicine_id' => $medicine->id,
        'dosage' => '1 capsule',
        'schedule' => 'Three times daily',
        'duration' => '7 days',
        'quantity' => 21,
    ]];

    $this->actingAs($doctorUser)->postJson('/doctor/prescriptions/safety-check', [
        'patient_id' => $patient->id,
        'items' => $items,
    ])->assertOk()->assertJsonPath('blocked', true)->assertJsonPath('allergies.0.substance', 'Penicillin');

    $this->actingAs($doctorUser)->post('/doctor/prescriptions', [
        'patient_id' => $patient->id,
        'appointment_id' => $appointment->id,
        'items' => $items,
        'clinical_notes' => 'Culture-confirmed infection.',
    ])->assertSessionHasErrors('override_justification');

    $this->actingAs($doctorUser)->post('/doctor/prescriptions', [
        'patient_id' => $patient->id,
        'appointment_id' => $appointment->id,
        'items' => $items,
        'clinical_notes' => 'Culture-confirmed infection.',
        'override_justification' => 'Culture-confirmed strep; cardiology co-sign and desensitisation protocol documented.',
    ])->assertRedirect();

    $rx = Prescription::query()->where('patient_id', $patient->id)->firstOrFail();
    expect($rx->doctor_id)->toBe($doctor->id)
        ->and($rx->hasOverride())->toBeTrue()
        ->and($rx->items()->count())->toBe(1);
    expect(ActivityLog::query()->where('action', ActivityActions::PRESCRIPTION_CREATED)->exists())->toBeTrue();
});

it('updates doctor profile, password and preferences', function () {
    [$doctorUser, $doctor] = partSixDoctorPatient();
    $version = $doctorUser->session_version;

    $this->actingAs($doctorUser)->put('/doctor/settings/profile', [
        'name' => 'Dr. Updated',
        'email' => $doctorUser->email,
        'phone' => '+880 1700 555000',
        'fee' => 1300,
        'follow_up_fee' => 700,
        'bio' => 'Updated professional bio.',
        'clinic_name' => 'MediPath Central Clinic',
        'clinic_address' => 'Banani, Dhaka',
        'room' => 'Room 210',
    ])->assertRedirect();

    expect($doctorUser->fresh()->name)->toBe('Dr. Updated')
        ->and((float) $doctor->fresh()->fee)->toBe(1300.0);

    $this->actingAs($doctorUser)->put('/doctor/settings/password', [
        'current_password' => 'MediPath#2026',
        'password' => 'NewDoctor#2026',
        'password_confirmation' => 'NewDoctor#2026',
    ])->assertRedirect();
    expect($doctorUser->fresh()->session_version)->toBe($version + 1);

    $this->actingAs($doctorUser)->put('/doctor/settings/preferences', [
        'theme' => 'dark',
        'language' => 'en',
        'timezone' => 'Asia/Dhaka',
        'date_format' => 'd M Y',
        'reduce_motion' => true,
    ])->assertRedirect();
    expect($doctorUser->fresh()->preferences->theme)->toBe('dark');
});

it('blocks non-doctors from the doctor workspace', function () {
    $patientUser = makeUser(['role' => 'patient']);
    makePatientRecord($patientUser);

    $this->actingAs($patientUser)->get('/doctor/dashboard')->assertForbidden();
    $this->actingAs($patientUser)->get('/doctor/schedule')->assertForbidden();
});