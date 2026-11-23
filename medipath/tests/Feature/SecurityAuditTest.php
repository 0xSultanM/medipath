<?php

use App\Enums\PrescriptionStatus;
use App\Models\Invoice;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/** Part 9 — role matrix, IDOR, injection payloads and session revocation. */
it('enforces the portal role matrix for every authenticated role', function () {
    $admin = makeUser(['role' => 'admin']);
    $doctorUser = makeUser(['role' => 'doctor', 'verification_status' => 'verified']);
    makeDoctorRecord($doctorUser);
    $pharmacistUser = makeUser(['role' => 'pharmacist']);
    makePharmacistRecord($pharmacistUser);
    $patientUser = makeUser(['role' => 'patient']);
    makePatientRecord($patientUser);

    $users = ['admin' => $admin, 'doctor' => $doctorUser, 'pharmacist' => $pharmacistUser, 'patient' => $patientUser];
    $portals = [
        'admin' => '/admin/dashboard',
        'doctor' => '/doctor/dashboard',
        'pharmacist' => '/pharmacist/dashboard',
        'patient' => '/patient/dashboard',
    ];

    foreach ($users as $role => $user) {
        foreach ($portals as $portal => $path) {
            $response = $this->actingAs($user)->get($path);

            if ($role === $portal) {
                $response->assertOk();
            } else {
                $response->assertForbidden();
            }
        }
    }
});

it('stops cross-patient and cross-doctor PDF access', function () {
    $doctorUser = makeUser(['role' => 'doctor', 'verification_status' => 'verified']);
    $doctor = makeDoctorRecord($doctorUser);
    $otherDoctorUser = makeUser(['role' => 'doctor', 'verification_status' => 'verified']);
    $otherDoctor = makeDoctorRecord($otherDoctorUser);

    $patientUser = makeUser(['role' => 'patient']);
    $patient = makePatientRecord($patientUser);
    $otherPatientUser = makeUser(['role' => 'patient']);
    $otherPatient = makePatientRecord($otherPatientUser);

    $prescription = Prescription::query()->create([
        'prescription_code' => 'RX-SEC-'.uniqid(),
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'status' => PrescriptionStatus::PendingDispense,
    ]);
    PrescriptionItem::query()->create([
        'prescription_id' => $prescription->id,
        'medicine_id' => makeMedicine()->id,
        'dosage' => '1 tablet',
        'schedule' => 'Once daily',
        'duration' => '7 days',
        'quantity' => 7,
        'unit_price_snapshot' => 5,
    ]);
    $invoice = Invoice::query()->create([
        'invoice_code' => 'INV-SEC-'.uniqid(),
        'patient_id' => $patient->id,
        'pharmacist_id' => makePharmacistRecord(makeUser(['role' => 'pharmacist']))->id,
        'prescription_id' => $prescription->id,
        'subtotal' => 35,
        'discount_kind' => 'fixed',
        'discount_value' => 0,
        'discount' => 0,
        'net_total' => 35,
        'currency_code' => 'BDT',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
    ]);

    $this->actingAs($otherPatientUser)->get("/patient/prescriptions/{$prescription->id}/pdf")->assertForbidden();
    $this->actingAs($otherPatientUser)->get("/patient/bills/{$invoice->id}/pdf")->assertForbidden();

    $this->actingAs($otherDoctorUser)->get("/doctor/prescriptions/{$prescription->id}/pdf")->assertForbidden();
    $this->actingAs($doctorUser)->get("/doctor/prescriptions/{$prescription->id}/pdf")->assertOk();

    expect($otherDoctor->id)->not->toBe($doctor->id)
        ->and($otherPatient->id)->not->toBe($patient->id);
});

it('treats search injection payloads as inert text', function () {
    $admin = makeUser(['role' => 'admin', 'name' => 'Admin One']);
    $pharmacistUser = makeUser(['role' => 'pharmacist']);
    makePharmacistRecord($pharmacistUser);
    makeMedicine(['name' => 'Amlodipine']);

    $payload = "%' OR 1=1 --";

    $this->actingAs($admin)->get('/admin/users?q='.urlencode($payload))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Users')->has('users', 0));

    $this->actingAs($pharmacistUser)->get('/pharmacist/inventory?q='.urlencode($payload))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Pharmacist/Inventory')->has('medicines', 0));
});

it('escapes stored script payloads in the rendered shell', function () {
    $admin = makeUser(['role' => 'admin']);
    $payload = '<script>alert("xss")</script>';
    makeUser(['role' => 'patient', 'name' => $payload, 'email' => 'xss.patient@medipath.test']);

    $response = $this->actingAs($admin)->get('/admin/users?q='.urlencode('xss.patient'));
    $response->assertOk();

    expect($response->getContent())->not->toContain($payload)
        ->and($response->getContent())->toContain('xss.patient@medipath.test');
});

it('shares a CSRF token and revokes other sessions on a pharmacist password change', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('csrfToken', fn ($token) => is_string($token) && strlen($token) > 20));

    $pharmacistUser = makeUser(['role' => 'pharmacist']);
    makePharmacistRecord($pharmacistUser);
    $before = (int) $pharmacistUser->session_version;

    $this->actingAs($pharmacistUser)->put('/pharmacist/settings/password', [
        'current_password' => 'MediPath#2026',
        'password' => 'Secure#Pharmacy9',
        'password_confirmation' => 'Secure#Pharmacy9',
    ])->assertRedirect();

    $pharmacistUser->refresh();
    expect((int) $pharmacistUser->session_version)->toBe($before + 1)
        ->and(password_verify('Secure#Pharmacy9', (string) $pharmacistUser->password))->toBeTrue();
});

it('ignores privilege fields on pharmacist medicine creation', function () {
    $pharmacistUser = makeUser(['role' => 'pharmacist']);
    makePharmacistRecord($pharmacistUser);

    $this->actingAs($pharmacistUser)->post('/pharmacist/inventory', [
        'name' => 'Privilege Test',
        'category' => 'Test',
        'form' => 'tablet',
        'unit_price' => 1,
        'min_stock_threshold' => 1,
        'stock_quantity' => 5,
        'is_active' => false,
        'archived_at' => now()->toIso8601String(),
    ])->assertRedirect();

    $medicine = \App\Models\Medicine::query()->where('name', 'Privilege Test')->firstOrFail();
    expect($medicine->is_active)->toBeTrue()
        ->and($medicine->archived_at)->toBeNull();
});

it('keeps unverified doctors and missing pharmacist profiles out', function () {
    $unverified = makeUser(['role' => 'doctor', 'verification_status' => 'pending']);
    makeDoctorRecord($unverified, ['verification_status' => 'pending']);
    $pharmacistWithoutProfile = makeUser(['role' => 'pharmacist']);

    $this->actingAs($unverified)->get('/doctor/dashboard')->assertForbidden();
    $this->actingAs($pharmacistWithoutProfile)->get('/pharmacist/dashboard')->assertForbidden();
    $this->actingAs($pharmacistWithoutProfile)->getJson('/pharmacist/inventory')->assertForbidden();
});