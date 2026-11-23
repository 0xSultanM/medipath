<?php

use App\Enums\PrescriptionStatus;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Part 9 — performance guards: hot-path indexes and eager-loaded queues. */
it('ships the portal performance indexes', function () {
    $expected = [
        'prescriptions' => ['idx_prescriptions_status_created', 'idx_prescriptions_dispensed_at'],
        'invoices' => ['idx_invoices_status_created'],
        'appointments' => ['idx_appointments_doctor_status_date'],
        'medicines' => ['idx_medicines_active_expiry'],
    ];

    foreach ($expected as $table => $indexes) {
        $names = collect(Schema::getIndexes($table))->pluck('name');
        foreach ($indexes as $index) {
            expect($names)->toContain($index);
        }
    }
});

it('loads the pharmacy queue without a query per row', function () {
    $pharmacistUser = makeUser(['role' => 'pharmacist']);
    makePharmacistRecord($pharmacistUser);
    $doctorUser = makeUser(['role' => 'doctor', 'verification_status' => 'verified']);
    $doctor = makeDoctorRecord($doctorUser);
    $patientUser = makeUser(['role' => 'patient']);
    $patient = makePatientRecord($patientUser);
    $medicine = makeMedicine(['name' => 'Queue Medicine']);

    $create = function (string $suffix) use ($doctor, $patient, $medicine): void {
        $prescription = Prescription::query()->create([
            'prescription_code' => 'RX-PERF-'.$suffix,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => PrescriptionStatus::PendingDispense,
        ]);
        PrescriptionItem::query()->create([
            'prescription_id' => $prescription->id,
            'medicine_id' => $medicine->id,
            'dosage' => '1 tablet',
            'schedule' => 'Once daily',
            'duration' => '7 days',
            'quantity' => 7,
            'unit_price_snapshot' => 5,
        ]);
    };

    $measure = function () use ($pharmacistUser): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($pharmacistUser)->get('/pharmacist/prescriptions')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $create('A');
    $one = $measure();

    foreach (range('B', 'J') as $suffix) {
        $create($suffix);
    }
    $ten = $measure();

    expect($ten - $one)->toBeLessThanOrEqual(3);
});

it('keeps the invoice list query count flat as rows grow', function () {
    $pharmacistUser = makeUser(['role' => 'pharmacist']);
    $pharmacist = makePharmacistRecord($pharmacistUser);
    $patientUser = makeUser(['role' => 'patient']);
    $patient = makePatientRecord($patientUser);

    $create = function (string $suffix) use ($pharmacist, $patient): void {
        \App\Models\Invoice::query()->create([
            'invoice_code' => 'INV-PERF-'.$suffix,
            'patient_id' => $patient->id,
            'pharmacist_id' => $pharmacist->id,
            'subtotal' => 50,
            'discount_kind' => 'fixed',
            'discount_value' => 0,
            'discount' => 0,
            'net_total' => 50,
            'currency_code' => 'BDT',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);
    };

    $measure = function () use ($pharmacistUser): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($pharmacistUser)->get('/pharmacist/invoices')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $create('A');
    $one = $measure();

    foreach (range('B', 'J') as $suffix) {
        $create($suffix);
    }
    $ten = $measure();

    expect($ten - $one)->toBeLessThanOrEqual(3);
});