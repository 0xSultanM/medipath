<?php

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\PrescriptionStatus;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\InventoryLedger;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use App\Support\ActivityActions;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{0:User,1:Doctor,2:Patient,3:Prescription,4:Medicine} */
function partEightPrescription(int $quantity = 5, int $stock = 100): array
{
    $pharmacistUser = makeUser(['role' => 'pharmacist']);
    makePharmacistRecord($pharmacistUser);

    $doctorUser = makeUser(['role' => 'doctor', 'verification_status' => 'verified']);
    $doctor = makeDoctorRecord($doctorUser);
    $patientUser = makeUser(['role' => 'patient', 'name' => 'Pharmacy Patient']);
    $patient = makePatientRecord($patientUser);

    $medicine = makeMedicine([
        'name' => 'Amoxicillin',
        'generic_name' => 'Amoxicillin',
        'category' => 'Antibiotic',
        'strength' => '500 mg',
        'unit_price' => 10,
        'stock_quantity' => $stock,
        'min_stock_threshold' => 10,
        'batch_number' => 'AMOX-P8',
    ]);

    $appointment = Appointment::query()->create([
        'appointment_code' => 'APT-P8-'.uniqid(),
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'date' => now()->toDateString(),
        'start_time' => '10:00',
        'end_time' => '10:30',
        'status' => AppointmentStatus::Completed,
        'visit_reason' => 'Pharmacy verification visit.',
    ]);

    $prescription = Prescription::query()->create([
        'prescription_code' => 'RX-P8-'.strtoupper(uniqid()),
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'appointment_id' => $appointment->id,
        'status' => PrescriptionStatus::PendingDispense,
        'clinical_notes' => 'Pharmacy part-eight verification.',
        'pharmacy_notes' => 'Dispense full course.',
        'ai_safety_summary' => 'Deterministic checks cleared; pharmacist re-verification required.',
        'ai_status' => 'fallback',
    ]);

    PrescriptionItem::query()->create([
        'prescription_id' => $prescription->id,
        'medicine_id' => $medicine->id,
        'dosage' => '1 capsule',
        'schedule' => 'Three times daily',
        'duration' => '5 days',
        'quantity' => $quantity,
        'unit_price_snapshot' => 10,
    ]);

    return [$pharmacistUser, $doctor, $patient, $prescription->fresh('items'), $medicine];
}

it('renders every pharmacist workspace screen', function () {
    [$pharmacistUser, , , $prescription] = partEightPrescription();

    foreach ([
        '/pharmacist/dashboard' => 'Pharmacist/Dashboard',
        '/pharmacist/prescriptions' => 'Pharmacist/Prescriptions',
        "/pharmacist/prescriptions/{$prescription->id}" => 'Pharmacist/SafetyGate',
        "/pharmacist/prescriptions/{$prescription->id}/dispense" => 'Pharmacist/Dispense',
        '/pharmacist/inventory' => 'Pharmacist/Inventory',
        '/pharmacist/alerts' => 'Pharmacist/Alerts',
        '/pharmacist/invoices' => 'Pharmacist/Invoices',
        '/pharmacist/workflow' => 'Pharmacist/Workflow',
        '/pharmacist/notifications' => 'Pharmacist/Notifications',
        '/pharmacist/settings' => 'Pharmacist/Settings',
    ] as $route => $component) {
        $this->actingAs($pharmacistUser)->get($route)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component));
    }
});

it('lists only pending prescriptions and filters them', function () {
    [$pharmacistUser, , , $prescription] = partEightPrescription();

    $other = Prescription::query()->create([
        'prescription_code' => 'RX-DONE-'.uniqid(),
        'patient_id' => $prescription->patient_id,
        'doctor_id' => $prescription->doctor_id,
        'status' => PrescriptionStatus::Dispensed,
        'dispensed_at' => now(),
    ]);

    $this->actingAs($pharmacistUser)->get('/pharmacist/prescriptions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pharmacist/Prescriptions')
            ->has('prescriptions', 1)
            ->where('prescriptions.0.id', $prescription->id)
            ->where('counts.pending', 1));

    $this->actingAs($pharmacistUser)->get('/pharmacist/prescriptions?q=RX-DONE')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('prescriptions', 0));

    expect($other->id)->not->toBe($prescription->id);
});

it('blocks the gate on an allergy and exposes the sealed override', function () {
    [$pharmacistUser, , $patient, $prescription] = partEightPrescription();
    PatientAllergy::query()->create([
        'patient_id' => $patient->id,
        'substance' => 'Penicillin',
        'allergy_type' => 'medication',
        'severity' => 'severe',
    ]);

    $this->actingAs($pharmacistUser)->get("/pharmacist/prescriptions/{$prescription->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pharmacist/SafetyGate')
            ->where('safety.blocked', true)
            ->where('canDispense', false)
            ->where('patient.allergies.0.substance', 'Penicillin'));

    $prescription->forceFill([
        'override_justification' => 'Culture-confirmed infection; desensitisation protocol and co-sign documented.',
    ])->save();

    $this->actingAs($pharmacistUser)->get("/pharmacist/prescriptions/{$prescription->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canDispense', true)->where('prescription.hasOverride', true));
});

it('dispenses atomically with stock ledger, invoice and notifications', function () {
    [$pharmacistUser, , $patient, $prescription, $medicine] = partEightPrescription(5, 100);
    $item = $prescription->items->first();

    $this->actingAs($pharmacistUser)->post("/pharmacist/prescriptions/{$prescription->id}/dispense", [
        'lines' => [['prescription_item_id' => $item->id, 'quantity' => 5]],
        'consultation_fee' => 0,
        'discount_kind' => 'percent',
        'discount_value' => 10,
        'payment_method' => 'cash',
        'safety_ack' => true,
    ])->assertRedirect(route('pharmacist.prescriptions.dispense', $prescription->id));

    expect($medicine->fresh()->stock_quantity)->toBe(95)
        ->and($prescription->fresh()->status)->toBe(PrescriptionStatus::Dispensed)
        ->and($prescription->fresh()->dispensed_by)->toBe($pharmacistUser->id);

    $invoice = Invoice::query()->where('prescription_id', $prescription->id)->firstOrFail();
    expect((float) $invoice->subtotal)->toBe(50.0)
        ->and((float) $invoice->discount)->toBe(5.0)
        ->and((float) $invoice->net_total)->toBe(45.0)
        ->and($invoice->payment_status)->toBe(PaymentStatus::Pending)
        ->and($invoice->items()->count())->toBe(1);

    $ledger = InventoryLedger::query()->where('reference', $prescription->prescription_code)->firstOrFail();
    expect($ledger->type->value)->toBe('dispense')
        ->and($ledger->quantity_change)->toBe(-5)
        ->and($ledger->previous_qty)->toBe(100)
        ->and($ledger->new_qty)->toBe(95);

    expect(Notification::query()->where('user_id', $patient->user_id)->where('type', 'prescription.dispensed')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('action', ActivityActions::PRESCRIPTION_DISPENSED)->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('action', ActivityActions::INVOICE_CREATED)->exists())->toBeTrue();

    $this->actingAs($pharmacistUser)->get("/pharmacist/prescriptions/{$prescription->id}/dispense")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pharmacist/Dispense')
            ->where('receipt.code', $invoice->invoice_code)
            ->where('receipt.netFormatted', app(\App\Services\CurrencyFormatter::class)->format(45)));
});

it('rolls back the entire dispense when stock is insufficient', function () {
    [$pharmacistUser, , , $prescription, $medicine] = partEightPrescription(5, 2);
    $item = $prescription->items->first();

    $this->actingAs($pharmacistUser)->post("/pharmacist/prescriptions/{$prescription->id}/dispense", [
        'lines' => [['prescription_item_id' => $item->id, 'quantity' => 5]],
        'discount_kind' => 'none',
        'payment_method' => 'cash',
        'safety_ack' => true,
    ])->assertSessionHasErrors('lines');

    expect($medicine->fresh()->stock_quantity)->toBe(2)
        ->and($prescription->fresh()->status)->toBe(PrescriptionStatus::PendingDispense)
        ->and(Invoice::query()->where('prescription_id', $prescription->id)->exists())->toBeFalse()
        ->and(InventoryLedger::query()->where('reference', $prescription->prescription_code)->exists())->toBeFalse();
});

it('rejects a partial dispense instead of closing the prescription', function () {
    [$pharmacistUser, , , $prescription, $medicine] = partEightPrescription(5, 100);
    $item = $prescription->items->first();

    $this->actingAs($pharmacistUser)->post("/pharmacist/prescriptions/{$prescription->id}/dispense", [
        'lines' => [['prescription_item_id' => $item->id, 'quantity' => 3]],
        'discount_kind' => 'none',
        'payment_method' => 'cash',
        'safety_ack' => true,
    ])->assertSessionHasErrors('lines');

    expect($prescription->fresh()->status)->toBe(PrescriptionStatus::PendingDispense)
        ->and($medicine->fresh()->stock_quantity)->toBe(100)
        ->and(Invoice::query()->where('prescription_id', $prescription->id)->exists())->toBeFalse();
});

it('refuses a blocked prescription without touching stock', function () {
    [$pharmacistUser, , $patient, $prescription, $medicine] = partEightPrescription(5, 100);

    $this->actingAs($pharmacistUser)->post("/pharmacist/prescriptions/{$prescription->id}/refuse", [
        'reason' => 'Allergy conflict requires the prescriber to review and re-issue.',
    ])->assertRedirect(route('pharmacist.prescriptions.index'));

    expect($prescription->fresh()->status)->toBe(PrescriptionStatus::Cancelled)
        ->and($medicine->fresh()->stock_quantity)->toBe(100)
        ->and(Notification::query()->where('user_id', $patient->user_id)->where('type', 'prescription.refused')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('action', ActivityActions::PRESCRIPTION_REFUSED)->exists())->toBeTrue();
});

it('creates, edits, restocks and archives medicines with ledger entries', function () {
    $pharmacistUser = makeUser(['role' => 'pharmacist']);
    makePharmacistRecord($pharmacistUser);

    $this->actingAs($pharmacistUser)->post('/pharmacist/inventory', [
        'name' => 'Losartan',
        'generic_name' => 'Losartan potassium',
        'category' => 'ARB',
        'strength' => '50 mg',
        'form' => 'tablet',
        'unit_price' => 12,
        'min_stock_threshold' => 20,
        'expiry_date' => now()->addYear()->toDateString(),
        'batch_number' => 'LOS-P8',
        'stock_quantity' => 40,
        'requires_prescription' => true,
        'side_effects' => 'dizziness, hypotension',
    ])->assertRedirect();

    $medicine = Medicine::query()->where('name', 'Losartan')->firstOrFail();
    expect($medicine->stock_quantity)->toBe(40)
        ->and($medicine->side_effects)->toBe(['dizziness', 'hypotension']);
    expect(InventoryLedger::query()->where('medicine_id', $medicine->id)->where('type', 'restock')->exists())->toBeTrue();

    $this->actingAs($pharmacistUser)->put("/pharmacist/inventory/{$medicine->id}", [
        'name' => 'Losartan',
        'generic_name' => 'Losartan potassium',
        'category' => 'ARB',
        'strength' => '50 mg',
        'form' => 'tablet',
        'unit_price' => 14,
        'min_stock_threshold' => 25,
        'expiry_date' => now()->addYear()->toDateString(),
        'batch_number' => 'LOS-P8',
        'requires_prescription' => true,
    ])->assertRedirect();

    $this->actingAs($pharmacistUser)->post("/pharmacist/inventory/{$medicine->id}/restock", [
        'quantity' => 60,
        'batch_number' => 'LOS-P8B',
        'expiry_date' => now()->addYears(2)->toDateString(),
        'reference' => 'GRN-P8',
    ])->assertRedirect();

    expect($medicine->fresh()->stock_quantity)->toBe(100);

    $this->actingAs($pharmacistUser)->post("/pharmacist/inventory/{$medicine->id}/archive")->assertRedirect();
    expect($medicine->fresh()->is_active)->toBeFalse();
    expect(ActivityLog::query()->where('action', ActivityActions::MEDICINE_ARCHIVED)->exists())->toBeTrue();
});

it('classifies stock and expiry alert tiers', function () {
    $pharmacistUser = makeUser(['role' => 'pharmacist']);
    makePharmacistRecord($pharmacistUser);
    makeMedicine(['name' => 'Low Medicine', 'stock_quantity' => 4, 'min_stock_threshold' => 20]);
    makeMedicine(['name' => 'Expired Medicine', 'expiry_date' => now()->subDay()->toDateString()]);
    makeMedicine(['name' => 'Critical Medicine', 'expiry_date' => now()->addDays(10)->toDateString()]);

    $this->actingAs($pharmacistUser)->get('/pharmacist/alerts')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pharmacist/Alerts')
            ->where('counts.expired', 1)
            ->where('counts.critical', 1)
            ->where('counts.stock', 1));
});

it('updates invoice payment state and downloads the PDF', function () {
    [$pharmacistUser, , , $prescription] = partEightPrescription();
    $invoice = Invoice::query()->create([
        'invoice_code' => 'INV-P8-'.strtoupper(uniqid()),
        'patient_id' => $prescription->patient_id,
        'pharmacist_id' => $pharmacistUser->pharmacist->id,
        'prescription_id' => $prescription->id,
        'subtotal' => 100,
        'discount_kind' => 'fixed',
        'discount_value' => 10,
        'discount' => 10,
        'net_total' => 90,
        'currency_code' => 'BDT',
        'payment_method' => 'cash',
        'payment_status' => PaymentStatus::Pending,
    ]);

    $this->actingAs($pharmacistUser)->post("/pharmacist/invoices/{$invoice->id}/status", [
        'payment_status' => 'paid',
    ])->assertRedirect();
    expect($invoice->fresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and((float) $invoice->fresh()->paid_amount)->toBe(90.0);

    $this->actingAs($pharmacistUser)->post("/pharmacist/invoices/{$invoice->id}/status", [
        'payment_status' => 'refunded',
        'refund_reason' => 'Duplicate charge confirmed with the admin.',
    ])->assertRedirect();
    expect($invoice->fresh()->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($invoice->fresh()->refund_reason)->not->toBeNull();

    $this->actingAs($pharmacistUser)->get("/pharmacist/invoices/{$invoice->id}/pdf")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('derives the workflow journey for a patient', function () {
    [$pharmacistUser, , $patient] = partEightPrescription();

    $this->actingAs($pharmacistUser)->get('/pharmacist/workflow?q='.$patient->patient_code)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pharmacist/Workflow')
            ->where('patient.code', $patient->patient_code)
            ->has('stages', 6)
            ->where('stages.0.done', true)
            ->where('stages.1.done', true)
            ->where('stages.3.done', false));
});

it('updates pharmacist profile and preferences', function () {
    $pharmacistUser = makeUser(['role' => 'pharmacist']);
    makePharmacistRecord($pharmacistUser);

    $this->actingAs($pharmacistUser)->put('/pharmacist/settings/profile', [
        'name' => 'Nadia Updated',
        'email' => $pharmacistUser->email,
        'phone' => '+880 1411 000111',
        'pharmacy_name' => 'MediPath Central Pharmacy',
    ])->assertRedirect();

    expect($pharmacistUser->fresh()->name)->toBe('Nadia Updated');

    $this->actingAs($pharmacistUser)->put('/pharmacist/settings/preferences', [
        'theme' => 'dark',
        'language' => 'en',
        'timezone' => 'Asia/Dhaka',
        'date_format' => 'd M Y',
        'reduce_motion' => true,
    ])->assertRedirect();

    expect($pharmacistUser->fresh()->preferences->theme)->toBe('dark');
});

it('blocks non-pharmacists from the pharmacy workspace', function () {
    $patientUser = makeUser(['role' => 'patient']);
    makePatientRecord($patientUser);

    $this->actingAs($patientUser)->get('/pharmacist/dashboard')->assertForbidden();
    $this->actingAs($patientUser)->get('/pharmacist/inventory')->assertForbidden();
});