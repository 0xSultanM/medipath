<?php

use App\Models\Doctor;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Pharmacist;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Small test-data helpers (no lorem, no fragile factories)
|--------------------------------------------------------------------------
*/
function makeUser(array $attributes = []): User
{
    static $counter = 0;
    $counter++;

    return User::query()->create([
        'name' => $attributes['name'] ?? 'Test User '.$counter,
        'email' => $attributes['email'] ?? "user{$counter}@medipath.test",
        'password' => 'MediPath#2026',
        'role' => $attributes['role'] ?? 'patient',
        'status' => $attributes['status'] ?? 'approved',
        'verification_status' => $attributes['verification_status'] ?? 'verified',
        'email_verified_at' => now(),
        ...$attributes,
    ]);
}

function makeSpecialty(string $name = 'Cardiology'): Specialty
{
    return Specialty::query()->firstOrCreate(
        ['name' => $name],
        ['slug' => Str::slug($name), 'is_active' => true],
    );
}

function makeDoctorRecord(User $user, array $attributes = []): Doctor
{
    return Doctor::query()->create([
        'user_id' => $user->id,
        'doctor_code' => $attributes['doctor_code'] ?? 'DOC-T'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
        'specialty_id' => $attributes['specialty_id'] ?? makeSpecialty()->id,
        'experience_years' => 8,
        'license_number' => $attributes['license_number'] ?? 'BMDC-A-T'.$user->id,
        'license_authority' => 'Bangladesh Medical & Dental Council',
        'fee' => $attributes['fee'] ?? 1000,
        'verification_status' => $attributes['verification_status'] ?? 'verified',
        'verified_at' => now(),
        ...$attributes,
    ]);
}

function makePatientRecord(User $user, array $attributes = []): Patient
{
    return Patient::query()->create([
        'user_id' => $user->id,
        'patient_code' => $attributes['patient_code'] ?? 'PT-T'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
        'name' => $user->name,
        'phone' => '+880 1700 000000',
        ...$attributes,
    ]);
}

function makePharmacistRecord(User $user, bool $approved = true): Pharmacist
{
    return Pharmacist::query()->create([
        'user_id' => $user->id,
        'pharmacist_code' => 'PHA-T'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
        'license_number' => 'BMDC-P-T'.$user->id,
        'license_authority' => 'Bangladesh Medical & Dental Council',
        'approved_at' => $approved ? now() : null,
    ]);
}

function makeMedicine(array $attributes = []): Medicine
{
    return Medicine::query()->create([
        'name' => $attributes['name'] ?? 'Paracetamol',
        'generic_name' => 'Acetaminophen',
        'category' => 'Analgesic',
        'strength' => '500 mg',
        'form' => 'tablet',
        'unit_price' => 2.5,
        'stock_quantity' => 100,
        'min_stock_threshold' => 20,
        'expiry_date' => now()->addMonths(12)->toDateString(),
        'batch_number' => $attributes['batch_number'] ?? 'BATCH-'.uniqid(),
        'is_active' => true,
        ...$attributes,
    ]);
}
