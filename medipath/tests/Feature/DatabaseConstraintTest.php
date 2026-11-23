<?php

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Pharmacist;
use App\Models\RevenueConfig;
use App\Models\Review;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('rejects negative medicine stock at the database level', function () {
    $medicine = makeMedicine(['stock_quantity' => 10]);

    expect(fn () => DB::table('medicines')->where('id', $medicine->id)->update(['stock_quantity' => -1]))
        ->toThrow(QueryException::class);

    expect($medicine->fresh()->stock_quantity)->toBe(10);
});

it('rejects an out-of-range revenue percentage', function () {
    expect(fn () => RevenueConfig::query()->create([
        'percentage' => 100.01,
        'effective_from' => now()->toDateString(),
    ]))->toThrow(QueryException::class);

    expect(fn () => RevenueConfig::query()->create([
        'percentage' => -0.01,
        'effective_from' => now()->toDateString(),
    ]))->toThrow(QueryException::class);
});

it('rejects a review rating outside 1..5', function () {
    $doctorUser = makeUser(['role' => 'doctor']);
    $doctor = makeDoctorRecord($doctorUser);
    $patient = makePatientRecord(makeUser());
    $appointment = Appointment::query()->create([
        'appointment_code' => 'APT-CONSTRAINT',
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'date' => now()->addDay()->toDateString(),
        'start_time' => '09:00',
        'end_time' => '09:30',
        'status' => 'completed',
        'visit_reason' => 'Constraint test',
    ]);

    expect(fn () => Review::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'appointment_id' => $appointment->id,
        'rating' => 6,
        'moderation_status' => 'pending',
    ]))->toThrow(QueryException::class);
});

it('enforces one active appointment per doctor, date and slot', function () {
    $doctor = makeDoctorRecord(makeUser(['role' => 'doctor']));
    $patient = makePatientRecord(makeUser());

    $base = [
        'doctor_id' => $doctor->id,
        'date' => now()->addDays(3)->toDateString(),
        'start_time' => '10:00',
        'end_time' => '10:30',
        'visit_reason' => 'Booking race test',
    ];

    $first = Appointment::query()->create([
        'appointment_code' => 'APT-RACE-1',
        'patient_id' => $patient->id,
        'status' => 'pending',
        ...$base,
    ]);

    expect(fn () => Appointment::query()->create([
        'appointment_code' => 'APT-RACE-2',
        'patient_id' => $patient->id,
        'status' => 'accepted',
        ...$base,
    ]))->toThrow(QueryException::class);

    // Releasing the first slot (terminal status) allows a new booking.
    $first->update(['status' => 'cancelled']);
    expect($first->fresh()->active_slot_key)->toBeNull();

    $second = Appointment::query()->create([
        'appointment_code' => 'APT-RACE-3',
        'patient_id' => $patient->id,
        'status' => 'pending',
        ...$base,
    ]);

    expect($second->active_slot_key)->toBe('10:00');
});

it('rejects a discount larger than the subtotal', function () {
    $patient = makePatientRecord(makeUser());
    $pharmacist = Pharmacist::query()->create([
        'user_id' => makeUser(['role' => 'pharmacist'])->id,
        'pharmacist_code' => 'PHA-CONSTRAINT',
        'license_number' => 'BMDC-P-CONSTRAINT',
        'license_authority' => 'Bangladesh Medical & Dental Council',
    ]);

    expect(fn () => Invoice::query()->create([
        'invoice_code' => 'INV-CONSTRAINT',
        'patient_id' => $patient->id,
        'pharmacist_id' => $pharmacist->id,
        'subtotal' => 100,
        'discount' => 150,
        'net_total' => 0,
        'currency_code' => 'BDT',
        'payment_method' => 'cash',
        'payment_status' => 'pending',
    ]))->toThrow(QueryException::class);
});

it('backs the role enum with a database check constraint', function () {
    expect(fn () => DB::table('users')->insert([
        'name' => 'Invalid Role',
        'email' => 'invalid-role@medipath.test',
        'password' => bcrypt('MediPath#2026'),
        'role' => 'superadmin',
        'status' => 'approved',
        'verification_status' => 'verified',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
