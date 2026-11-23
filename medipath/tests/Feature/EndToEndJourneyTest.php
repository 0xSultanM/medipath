<?php

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\PrescriptionStatus;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Models\InventoryLedger;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Prescription;
use App\Models\User;
use App\Support\ActivityActions;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Part 9 — one real journey across every portal:
 * registration → booking → doctor decision → prescribing → pharmacy gate →
 * atomic dispense → invoice payment → patient discharge.
 */
it('completes the full registration-to-discharge journey', function () {
    $date = now()->addDays(2)->toDateString();

    $this->post('/register', [
        'role' => 'patient',
        'name' => 'Journey Patient',
        'email' => 'journey.patient@medipath.test',
        'phone' => '+880 1700 778899',
        'password' => 'Journey#2026',
        'password_confirmation' => 'Journey#2026',
        'terms' => true,
        'age' => 34,
        'gender' => 'Female',
        'condition' => 'Persistent dry cough for three weeks',
    ])->assertRedirect(route('patient.dashboard'));

    $patientUser = User::query()->where('email', 'journey.patient@medipath.test')->firstOrFail();
    $patient = $patientUser->patient;
    expect($patient)->not->toBeNull()
        ->and($patientUser->role->value)->toBe('patient');

    $this->assertAuthenticatedAs($patientUser);

    $doctorUser = makeUser(['role' => 'doctor', 'verification_status' => 'verified', 'name' => 'Dr. Journey']);
    $doctor = makeDoctorRecord($doctorUser, ['fee' => 900]);
    DoctorSchedule::query()->create([
        'doctor_id' => $doctor->id,
        'day_of_week' => now()->addDays(2)->dayOfWeek,
        'start_time' => '09:00',
        'end_time' => '17:00',
        'slot_duration' => 30,
        'is_active' => true,
    ]);
    $medicine = makeMedicine([
        'name' => 'Montelukast',
        'generic_name' => 'Montelukast sodium',
        'category' => 'Leukotriene antagonist',
        'strength' => '10 mg',
        'unit_price' => 14,
        'stock_quantity' => 100,
        'min_stock_threshold' => 10,
        'batch_number' => 'MON-P9',
    ]);

    $this->post('/patient/appointments', [
        'doctor_code' => $doctor->doctor_code,
        'date' => $date,
        'time' => '09:00',
        'visit_reason' => 'Persistent dry cough; requesting assessment and treatment plan.',
    ])->assertRedirect(route('patient.appointments.index'));

    $appointment = Appointment::query()->where('patient_id', $patient->id)->firstOrFail();
    expect($appointment->status)->toBe(AppointmentStatus::Pending)
        ->and(Notification::query()->where('user_id', $doctorUser->id)->where('type', 'appointment.requested')->exists())->toBeTrue();

    $this->actingAs($doctorUser)->post("/doctor/requests/{$appointment->id}/accept")->assertRedirect();
    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Accepted);

    $this->actingAs($doctorUser)->post("/doctor/requests/{$appointment->id}/complete")->assertRedirect();
    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Completed);

    $this->actingAs($doctorUser)->post('/doctor/prescriptions', [
        'patient_id' => $patient->id,
        'appointment_id' => $appointment->id,
        'items' => [[
            'medicine_id' => $medicine->id,
            'dosage' => '1 tablet',
            'schedule' => 'Once nightly',
            'duration' => '14 days',
            'quantity' => 14,
        ]],
        'clinical_notes' => 'Cough-variant asthma suspected; trial montelukast and review in two weeks.',
        'patient_notes' => 'Take at bedtime. Report mood changes or headache.',
        'pharmacy_notes' => 'Dispense 14 tablets; counsel on bedtime dosing.',
    ])->assertRedirect();

    $prescription = Prescription::query()->where('patient_id', $patient->id)->firstOrFail();
    expect($prescription->status)->toBe(PrescriptionStatus::PendingDispense)
        ->and($prescription->items()->count())->toBe(1)
        ->and(Notification::query()->where('user_id', $patientUser->id)->where('type', 'prescription.created')->exists())->toBeTrue();

    $pharmacistUser = makeUser(['role' => 'pharmacist']);
    $pharmacist = makePharmacistRecord($pharmacistUser);

    $this->actingAs($pharmacistUser)->get("/pharmacist/prescriptions/{$prescription->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pharmacist/SafetyGate')
            ->where('safety.blocked', false)
            ->where('safety.allergies', [])
            ->where('canDispense', true));

    $item = $prescription->items()->firstOrFail();

    $this->actingAs($pharmacistUser)->post("/pharmacist/prescriptions/{$prescription->id}/dispense", [
        'lines' => [['prescription_item_id' => $item->id, 'quantity' => 14]],
        'consultation_fee' => 900,
        'discount_kind' => 'percent',
        'discount_value' => 5,
        'payment_method' => 'card',
        'safety_ack' => true,
    ])->assertRedirect(route('pharmacist.prescriptions.dispense', $prescription->id));

    $invoice = Invoice::query()->where('prescription_id', $prescription->id)->firstOrFail();
    $subtotal = 14 * 14 + 900;
    $discount = round($subtotal * 0.05, 2);
    $net = round($subtotal - $discount, 2);

    expect($prescription->fresh()->status)->toBe(PrescriptionStatus::Dispensed)
        ->and($medicine->fresh()->stock_quantity)->toBe(86)
        ->and((float) $invoice->subtotal)->toBe((float) $subtotal)
        ->and((float) $invoice->net_total)->toBe($net)
        ->and($invoice->payment_status)->toBe(PaymentStatus::Pending)
        ->and($invoice->items()->count())->toBe(2)
        ->and(InventoryLedger::query()->where('reference', $prescription->prescription_code)->value('quantity_change'))->toBe(-14)
        ->and(Notification::query()->where('user_id', $patientUser->id)->where('type', 'prescription.dispensed')->exists())->toBeTrue();

    $this->actingAs($pharmacistUser)->post("/pharmacist/invoices/{$invoice->id}/status", [
        'payment_status' => 'paid',
    ])->assertRedirect();

    expect($invoice->fresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($invoice->fresh()->paid_at)->not->toBeNull()
        ->and(Notification::query()->where('user_id', $patientUser->id)->where('type', 'invoice.paid')->exists())->toBeTrue();

    $this->actingAs($patientUser)->get('/patient/prescriptions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Patient/Prescriptions')
            ->has('prescriptions', 1)
            ->where('prescriptions.0.code', $prescription->prescription_code)
            ->where('prescriptions.0.status', 'dispensed'));

    $this->actingAs($patientUser)->get('/patient/bills')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Patient/Bills')
            ->has('invoices', 1)
            ->where('invoices.0.code', $invoice->invoice_code)
            ->where('invoices.0.status', 'paid'));

    $this->actingAs($patientUser)->get("/patient/prescriptions/{$prescription->id}/pdf")
        ->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($patientUser)->get("/patient/bills/{$invoice->id}/pdf")
        ->assertOk()->assertHeader('content-type', 'application/pdf');

    $this->actingAs($pharmacistUser)->get('/pharmacist/workflow?q='.$patient->patient_code)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pharmacist/Workflow')
            ->where('stockBlocked', false)
            ->has('stages', 6)
            ->where('stages.0.done', true)
            ->where('stages.1.done', true)
            ->where('stages.2.done', true)
            ->where('stages.3.done', true)
            ->where('stages.4.done', true)
            ->where('stages.5.done', true)
            ->where('invoice.status', 'paid'));

    $actions = ActivityLog::query()->orderBy('id')->pluck('action')->all();
    foreach ([
        ActivityActions::AUTH_LOGIN,
        ActivityActions::APPOINTMENT_BOOKED,
        ActivityActions::APPOINTMENT_ACCEPTED,
        ActivityActions::APPOINTMENT_COMPLETED,
        ActivityActions::PRESCRIPTION_CREATED,
        ActivityActions::PRESCRIPTION_DISPENSED,
        ActivityActions::INVOICE_CREATED,
        ActivityActions::INVOICE_PAID,
    ] as $expected) {
        expect($actions)->toContain($expected);
    }

    expect($pharmacist->id)->not->toBeNull();
});