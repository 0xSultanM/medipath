<?php

use App\Jobs\RegistrationReceived;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\PendingApproval;
use App\Models\Specialty;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function registerPayload(array $overrides = []): array
{
    return [
        'role' => 'patient',
        'name' => 'Nadia Rahman',
        'email' => 'nadia.rahman@medipath.test',
        'phone' => '+880 1712 900100',
        'password' => 'Strong#Pass2026',
        'password_confirmation' => 'Strong#Pass2026',
        'terms' => true,
        'age' => 30,
        'gender' => 'Female',
        'condition' => 'Migraine',
        ...$overrides,
    ];
}

it('registers a patient atomically, auto-approves and signs them in', function () {
    $this->post('/register', registerPayload())
        ->assertRedirect(route('patient.dashboard'));

    $user = User::query()->where('email', 'nadia.rahman@medipath.test')->firstOrFail();
    $this->assertAuthenticatedAs($user);

    expect($user->role->value)->toBe('patient')
        ->and($user->status->value)->toBe('approved')
        ->and($user->patient)->not->toBeNull()
        ->and($user->patient->patient_code)->toStartWith('PT-');

    expect(Notification::query()->where('user_id', $user->id)->where('type', 'account.welcome')->exists())->toBeTrue();
    expect(ActivityLog::query()->where('action', 'user.created')->where('target_id', $user->id)->exists())->toBeTrue();
});

it('holds patients for approval when the global auto-approve setting is off', function () {
    SystemSetting::query()->create([
        'key' => 'patient_auto_approve',
        'value' => false,
        'type' => 'bool',
        'group' => 'registration',
        'label' => 'Auto approve patients',
    ]);
    app(SettingsService::class)->flush();

    $this->post('/register', registerPayload(['email' => 'held.patient@medipath.test']))
        ->assertRedirect(route('pending.approval'));

    $user = User::query()->where('email', 'held.patient@medipath.test')->firstOrFail();

    expect($user->status->value)->toBe('pending')
        ->and(PendingApproval::query()->where('user_id', $user->id)->where('requested_role', 'patient')->exists())->toBeTrue();
});

it('registers a doctor with a private credential and an approval-queue item', function () {
    $this->seed();
    Storage::fake('credentials');
    $specialty = Specialty::query()->where('slug', 'cardiology')->firstOrFail();

    $this->post('/register', registerPayload([
        'role' => 'doctor',
        'email' => 'new.doctor@medipath.test',
        'name' => 'Dr. New Doctor',
        'license_number' => 'BMDC-NEW-001',
        'license_authority' => 'Bangladesh Medical & Dental Council',
        'specialty_id' => $specialty->id,
        'experience_years' => 7,
        'fee' => '1250.00',
        'credential' => UploadedFile::fake()->create('license.pdf', 300, 'application/pdf'),
        'age' => null,
        'gender' => null,
        'condition' => null,
    ]))->assertRedirect(route('pending.approval'));

    $user = User::query()->where('email', 'new.doctor@medipath.test')->firstOrFail();
    $doctor = Doctor::query()->where('user_id', $user->id)->firstOrFail();
    $approval = PendingApproval::query()->where('user_id', $user->id)->firstOrFail();

    $this->assertAuthenticatedAs($user);

    expect($user->status->value)->toBe('pending')
        ->and($doctor->verification_status->value)->toBe('pending')
        ->and($doctor->credential_path)->not->toBeNull()
        ->and($approval->requested_role)->toBe('doctor')
        ->and($approval->license_details['license_number'])->toBe('BMDC-NEW-001');

    Storage::disk('credentials')->assertExists($doctor->credential_path);
});

it('registers a pharmacist with a private license document', function () {
    $this->seed();
    Storage::fake('credentials');

    $this->post('/register', registerPayload([
        'role' => 'pharmacist',
        'email' => 'new.pharmacist@medipath.test',
        'name' => 'New Pharmacist',
        'license_number' => 'BMDC-P-NEW-001',
        'license_authority' => 'Pharmacy Council of Bangladesh',
        'credential' => UploadedFile::fake()->create('license.pdf', 200, 'application/pdf'),
        'age' => null,
        'gender' => null,
        'condition' => null,
    ]))->assertRedirect(route('pending.approval'));

    $user = User::query()->where('email', 'new.pharmacist@medipath.test')->firstOrFail();

    expect($user->role->value)->toBe('pharmacist')
        ->and($user->pharmacist)->not->toBeNull()
        ->and($user->status->value)->toBe('pending');
    Storage::disk('credentials')->assertExists($user->pharmacist->credential_path);
});

it('queues the registration notification job after the transaction', function () {
    Queue::fake();

    $this->post('/register', registerPayload(['email' => 'queued.patient@medipath.test']))
        ->assertRedirect(route('patient.dashboard'));

    Queue::assertPushed(RegistrationReceived::class);
});

it('merges a claimable walk-in history and archives the placeholder', function () {
    $this->seed();

    $this->post('/register', registerPayload([
        'email' => 'claimed.patient@medipath.test',
        'claim_code' => 'WI-9001',
    ]))->assertRedirect(route('patient.dashboard'));

    $new = User::query()->where('email', 'claimed.patient@medipath.test')->firstOrFail()->patient;
    $old = Patient::query()->where('patient_code', 'PT-9001')->firstOrFail();

    expect($new->is_walk_in)->toBeTrue()
        ->and($old->archived_at)->not->toBeNull()
        ->and($old->walk_in_code)->toBeNull()
        ->and(Appointment::query()->where('appointment_code', 'APT-9011')->value('patient_id'))->toBe($new->id);
});

it('refuses a claim code already linked to an active account and rolls back', function () {
    $this->seed();
    $before = User::query()->count();

    $this->from('/register')->post('/register', registerPayload([
        'email' => 'attacker@medipath.test',
        'claim_code' => 'WI-4821',
    ]))->assertRedirect('/register')->assertSessionHasErrors('claim_code');

    expect(User::query()->count())->toBe($before)
        ->and(User::query()->where('email', 'attacker@medipath.test')->exists())->toBeFalse();
});

it('validates role fields, terms, password strength and duplicate licenses', function () {
    $this->seed();
    Storage::fake('credentials');

    $this->from('/register')->post('/register', registerPayload([
        'terms' => false,
        'password' => 'weak',
        'password_confirmation' => 'weak',
    ]))->assertSessionHasErrors(['terms', 'password']);

    $this->from('/register')->post('/register', registerPayload([
        'role' => 'doctor',
        'email' => 'bad.file@medipath.test',
        'license_number' => 'BMDC-BAD-001',
        'license_authority' => 'Bangladesh Medical & Dental Council',
        'specialty_id' => Specialty::query()->value('id'),
        'credential' => UploadedFile::fake()->create('license.exe', 50, 'application/octet-stream'),
    ]))->assertSessionHasErrors('credential');

    $this->from('/register')->post('/register', registerPayload([
        'role' => 'doctor',
        'email' => 'duplicate.license@medipath.test',
        'license_number' => 'BMDC-A-48213',
        'license_authority' => 'Bangladesh Medical & Dental Council',
        'specialty_id' => Specialty::query()->value('id'),
        'credential' => UploadedFile::fake()->create('license.pdf', 100, 'application/pdf'),
    ]))->assertSessionHasErrors('license_number');

    $this->from('/register')->post('/register', registerPayload(['role' => 'admin']))
        ->assertSessionHasErrors('role');
});

it('hashes the stored password and never returns it', function () {
    $this->post('/register', registerPayload(['email' => 'hash.patient@medipath.test']))
        ->assertRedirect(route('patient.dashboard'));

    $user = User::query()->where('email', 'hash.patient@medipath.test')->firstOrFail();

    expect(Hash::check('Strong#Pass2026', $user->password))->toBeTrue()
        ->and($user->password)->not->toBe('Strong#Pass2026');
});
