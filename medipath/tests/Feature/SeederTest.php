<?php

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DrugInteraction;
use App\Models\EmergencyContact;
use App\Models\EmergencyRule;
use App\Models\Faq;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\PendingApproval;
use App\Models\Prescription;
use App\Models\Review;
use App\Models\Specialty;
use App\Models\User;
use App\Services\CurrencyFormatter;

beforeEach(function () {
    $this->seed();
});

it('loads the complete deterministic demo graph', function () {
    expect(Specialty::query()->count())->toBe(14)
        ->and(Medicine::query()->count())->toBe(33)
        ->and(Faq::query()->count())->toBe(15)
        ->and(EmergencyContact::query()->count())->toBe(6)
        ->and(EmergencyRule::query()->count())->toBe(7)
        ->and(DrugInteraction::query()->count())->toBe(15)
        ->and(Doctor::query()->verified()->count())->toBe(4)
        ->and(PendingApproval::query()->pending()->count())->toBe(2)
        ->and(Patient::query()->count())->toBe(6)
        ->and(Prescription::query()->count())->toBe(5)
        ->and(Invoice::query()->count())->toBe(3)
        ->and(Review::query()->count())->toBe(5)
        ->and(Appointment::query()->count())->toBe(11);
});

it('seeds every role with realistic, non-placeholder data', function () {
    $roles = User::query()->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');

    expect((int) $roles['admin'])->toBe(1)
        ->and((int) $roles['doctor'])->toBe(6)
        ->and((int) $roles['pharmacist'])->toBe(2)
        ->and((int) $roles['patient'])->toBe(6);

    foreach (User::query()->get() as $user) {
        expect($user->name)->not->toContain('Lorem')
            ->and($user->email)->toMatch('/@(medipath\.test|walkin\.medipath\.local)$/');
    }
});

it('keeps inventory, prescriptions and slot invariants consistent', function () {
    expect(Medicine::query()->where('stock_quantity', '<', 0)->count())->toBe(0)
        ->and(Appointment::query()->whereIn('status', ['pending', 'accepted'])->whereNull('active_slot_key')->count())->toBe(0)
        ->and(Prescription::query()->where('status', 'pending_dispense')->count())->toBe(2)
        ->and(Prescription::query()->where('status', 'dispensed')->count())->toBe(3)
        ->and(Invoice::query()->where('payment_status', 'paid')->count())->toBe(3)
        ->and(Review::query()->where('moderation_status', 'approved')->count())->toBe(4)
        ->and(Review::query()->where('moderation_status', 'pending')->count())->toBe(1);
});

it('notifies each demo role and records seeded audit events', function () {
    expect(Notification::query()->count())->toBeGreaterThanOrEqual(12)
        ->and(ActivityLog::query()->count())->toBeGreaterThanOrEqual(5);

    $admin = User::query()->where('email', 'admin@medipath.test')->firstOrFail();
    $doctor = User::query()->where('email', 'ayesha.rahman@medipath.test')->firstOrFail();
    $pharmacist = User::query()->where('email', 'pharmacist@medipath.test')->firstOrFail();
    $patient = User::query()->where('email', 'patient@medipath.test')->firstOrFail();

    foreach ([$admin, $doctor, $pharmacist, $patient] as $user) {
        expect($user->unreadNotificationCount())->toBeGreaterThan(0);
    }
});

it('formats seeded fees with the configured currency symbol', function () {
    $doctor = Doctor::query()->verified()->firstOrFail();

    $output = app(CurrencyFormatter::class)->format($doctor->fee);

    expect($output)->toContain('৳')
        ->and($output)->toContain('1,200.00')
        ->and($output)->not->toContain('$');
});

it('lets the seeded admin sign in with the documented demo password', function () {
    $this->post('/login', [
        'email' => 'admin@medipath.test',
        'password' => config('medipath.demo.password'),
    ])->assertRedirect(route('admin.dashboard'));
});

it('keeps the demo graph idempotent across repeated seeds', function () {
    $this->seed();

    expect(Medicine::query()->count())->toBe(33)
        ->and(Patient::query()->count())->toBe(6)
        ->and(Appointment::query()->count())->toBe(11)
        ->and(Invoice::query()->count())->toBe(3);
});
