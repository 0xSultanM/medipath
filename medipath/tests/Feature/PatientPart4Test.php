<?php

use App\Enums\AppointmentStatus;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\PatientMedication;
use App\Models\Prescription;
use App\Models\Review;
use App\Models\User;
use App\Support\ActivityActions;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{0:User,1:Patient,2:Doctor} */
function partFourPatientAndDoctor(): array
{
    $patientUser = makeUser(['role' => 'patient', 'name' => 'Portal Patient']);
    $patient = makePatientRecord($patientUser);
    $doctorUser = makeUser(['role' => 'doctor', 'verification_status' => 'verified']);
    $doctor = makeDoctorRecord($doctorUser);
    DoctorSchedule::query()->create([
        'doctor_id' => $doctor->id,
        'day_of_week' => now()->addDay()->dayOfWeek,
        'start_time' => '09:00',
        'end_time' => '12:00',
        'slot_duration' => 30,
        'is_active' => true,
    ]);

    return [$patientUser, $patient, $doctor];
}

it('renders every patient portal screen', function () {
    [$user] = partFourPatientAndDoctor();

    foreach ([
        '/patient/dashboard' => 'Patient/Dashboard',
        '/patient/doctors' => 'Patient/Doctors',
        '/patient/appointments' => 'Patient/Appointments',
        '/patient/prescriptions' => 'Patient/Prescriptions',
        '/patient/bills' => 'Patient/Bills',
        '/patient/clinical-profile' => 'Patient/ClinicalProfile',
        '/patient/reviews' => 'Patient/Reviews',
        '/patient/notifications' => 'Patient/Notifications',
        '/patient/symptom-checker' => 'Patient/SymptomChecker',
        '/patient/settings' => 'Patient/Settings',
    ] as $route => $component) {
        $this->actingAs($user)->get($route)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
    }
});

it('books a leave-aware appointment and renders the booking screen', function () {
    [$user, , $doctor] = partFourPatientAndDoctor();
    $date = now()->addDay()->toDateString();

    $this->actingAs($user)->get("/patient/book/{$doctor->doctor_code}?date={$date}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Patient/Booking')
            ->where('selectedDate', $date)
            ->has('slots'));

    $this->actingAs($user)->post('/patient/appointments', [
        'doctor_code' => $doctor->doctor_code,
        'date' => $date,
        'time' => '10:00',
        'visit_reason' => 'Persistent morning headaches for two weeks.',
    ])->assertRedirect(route('patient.appointments.index'));

    $appointment = Appointment::query()->where('doctor_id', $doctor->id)->firstOrFail();
    expect($appointment->status)->toBe(AppointmentStatus::Pending)
        ->and($appointment->active_slot_key)->toBe('10:00');
    expect(ActivityLog::query()->where('action', ActivityActions::APPOINTMENT_BOOKED)->exists())->toBeTrue();
});

it('cancels and reschedules an owned appointment', function () {
    [$user, $patient, $doctor] = partFourPatientAndDoctor();
    $date = now()->addDay()->toDateString();
    $appointment = Appointment::query()->create([
        'appointment_code' => 'APT-P4-1',
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'date' => $date,
        'start_time' => '09:00',
        'end_time' => '09:30',
        'status' => AppointmentStatus::Accepted,
        'visit_reason' => 'Follow-up for blood pressure control.',
    ]);

    $this->actingAs($user)->post("/patient/appointments/{$appointment->id}/cancel", ['reason' => 'Schedule conflict'])
        ->assertRedirect();
    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled)
        ->and($appointment->fresh()->cancellation_reason)->toBe('Schedule conflict');

    $appointment2 = Appointment::query()->create([
        'appointment_code' => 'APT-P4-2',
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'date' => $date,
        'start_time' => '10:00',
        'end_time' => '10:30',
        'status' => AppointmentStatus::Accepted,
        'visit_reason' => 'Follow-up for blood pressure control.',
    ]);

    $this->actingAs($user)->post("/patient/appointments/{$appointment2->id}/reschedule", [
        'date' => $date,
        'time' => '11:00',
    ])->assertRedirect();

    expect($appointment2->fresh()->status)->toBe(AppointmentStatus::Pending)
        ->and(substr((string) $appointment2->fresh()->start_time, 0, 5))->toBe('11:00')
        ->and($appointment2->fresh()->reschedule_count)->toBe(1);
});

it('manages patient allergies and medications with duplicate protection', function () {
    [$user, $patient] = partFourPatientAndDoctor();

    $this->actingAs($user)->post('/patient/clinical-profile/allergies', [
        'substance' => 'Penicillin',
        'allergy_type' => 'medication',
        'severity' => 'severe',
        'reaction_notes' => 'Rash and throat tightness.',
    ])->assertRedirect();

    $allergy = PatientAllergy::query()->where('patient_id', $patient->id)->firstOrFail();
    expect($allergy->severity->value)->toBe('severe');

    $this->actingAs($user)->post('/patient/clinical-profile/allergies', [
        'substance' => 'Penicillin',
        'allergy_type' => 'medication',
        'severity' => 'moderate',
    ])->assertSessionHasErrors('substance');

    $this->actingAs($user)->put("/patient/clinical-profile/allergies/{$allergy->id}", [
        'substance' => 'Penicillin',
        'allergy_type' => 'medication',
        'severity' => 'life_threatening',
        'reaction_notes' => 'Anaphylaxis in 2019.',
    ])->assertRedirect();
    expect($allergy->fresh()->severity->value)->toBe('life_threatening');

    $this->actingAs($user)->post('/patient/clinical-profile/medications', [
        'medicine_name' => 'Amlodipine',
        'dosage' => '5 mg',
        'frequency' => 'Morning',
        'start_date' => today()->toDateString(),
    ])->assertRedirect();

    $medication = PatientMedication::query()->where('patient_id', $patient->id)->firstOrFail();
    $this->actingAs($user)->post("/patient/clinical-profile/medications/{$medication->id}/toggle")->assertRedirect();
    expect($medication->fresh()->is_active)->toBeFalse();
});

it('serves owner-only prescription and invoice PDFs', function () {
    [$user, $patient, $doctor] = partFourPatientAndDoctor();
    $appointment = Appointment::query()->create([
        'appointment_code' => 'APT-P4-PDF',
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'date' => today()->toDateString(),
        'start_time' => '09:00',
        'end_time' => '09:30',
        'status' => AppointmentStatus::Completed,
        'visit_reason' => 'Routine review.',
    ]);
    $prescription = Prescription::query()->create([
        'prescription_code' => 'RX-P4-PDF',
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'appointment_id' => $appointment->id,
        'status' => 'pending_dispense',
    ]);
    $invoice = Invoice::query()->create([
        'invoice_code' => 'INV-P4-PDF',
        'patient_id' => $patient->id,
        'pharmacist_id' => makePharmacistRecord(makeUser(['role' => 'pharmacist']))->id,
        'prescription_id' => $prescription->id,
        'subtotal' => 50,
        'discount' => 0,
        'net_total' => 50,
        'currency_code' => 'BDT',
        'payment_method' => 'cash',
        'payment_status' => 'pending',
    ]);

    $this->actingAs($user)->get("/patient/prescriptions/{$prescription->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($user)->get("/patient/bills/{$invoice->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');

    $other = makeUser(['role' => 'patient']);
    makePatientRecord($other);
    $this->actingAs($other)->get("/patient/prescriptions/{$prescription->id}/pdf")->assertForbidden();
    $this->actingAs($other)->get("/patient/bills/{$invoice->id}/pdf")->assertForbidden();
});

it('submits a review only for an owned completed visit', function () {
    [$user, $patient, $doctor] = partFourPatientAndDoctor();
    $appointment = Appointment::query()->create([
        'appointment_code' => 'APT-P4-REV',
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'date' => today()->toDateString(),
        'start_time' => '09:00',
        'end_time' => '09:30',
        'status' => AppointmentStatus::Completed,
        'visit_reason' => 'Routine review.',
    ]);

    $this->actingAs($user)->post('/patient/reviews', [
        'appointment_id' => $appointment->id,
        'rating' => 5,
        'comment' => 'Clear explanation and a practical plan.',
    ])->assertRedirect();

    expect(Review::query()->where('appointment_id', $appointment->id)->where('rating', 5)->exists())->toBeTrue();
    expect(ActivityLog::query()->where('action', ActivityActions::REVIEW_SUBMITTED)->exists())->toBeTrue();

    $this->actingAs($user)->post('/patient/reviews', [
        'appointment_id' => $appointment->id,
        'rating' => 4,
    ])->assertSessionHasErrors('appointment_id');
});

it('updates patient profile, password and preferences', function () {
    [$user, $patient] = partFourPatientAndDoctor();
    $version = $user->session_version;

    $this->actingAs($user)->put('/patient/settings/profile', [
        'name' => 'Updated Patient',
        'email' => $user->email,
        'phone' => '+880 1700 777888',
        'age' => 42,
        'gender' => 'Female',
        'blood_group' => 'B+',
        'condition' => 'Hypertension',
        'address' => 'Dhanmondi, Dhaka',
    ])->assertRedirect();

    expect($user->fresh()->name)->toBe('Updated Patient')
        ->and($patient->fresh()->age)->toBe(42);

    $this->actingAs($user)->put('/patient/settings/password', [
        'current_password' => 'MediPath#2026',
        'password' => 'NewPatient#2026',
        'password_confirmation' => 'NewPatient#2026',
    ])->assertRedirect();
    expect($user->fresh()->session_version)->toBe($version + 1);

    $this->actingAs($user)->put('/patient/settings/preferences', [
        'theme' => 'dark',
        'language' => 'en',
        'timezone' => 'Asia/Dhaka',
        'date_format' => 'd M Y',
        'inapp' => true,
        'email' => false,
        'sms' => true,
        'reduce_motion' => true,
    ])->assertRedirect();

    expect($user->fresh()->preferences->theme)->toBe('dark');
});

it('renders notifications and marks them read', function () {
    [$user] = partFourPatientAndDoctor();
    $notification = Notification::query()->create([
        'user_id' => $user->id,
        'type' => 'appointment.requested',
        'category' => 'appointment',
        'title' => 'Appointment requested',
        'message' => 'Your request is pending.',
    ]);

    $this->actingAs($user)->get('/patient/notifications')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Patient/Notifications')->where('counts.unread', 1));

    $this->actingAs($user)->post("/notifications/{$notification->id}/read")->assertRedirect();
    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('blocks non-patients from the patient portal', function () {
    $doctorUser = makeUser(['role' => 'doctor', 'verification_status' => 'verified']);
    makeDoctorRecord($doctorUser);

    $this->actingAs($doctorUser)->get('/patient/dashboard')->assertForbidden();
    $this->actingAs($doctorUser)->get('/patient/appointments')->assertForbidden();
});