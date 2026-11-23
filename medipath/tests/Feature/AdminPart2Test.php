<?php

use App\Models\ActivityLog;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\PendingApproval;
use App\Models\Specialty;
use App\Models\User;
use App\Support\ActivityActions;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array<string, mixed> */
function adminUserPayload(array $overrides = []): array
{
    return [
        'name' => 'Internal Operator',
        'email' => 'operator@medipath.test',
        'phone' => '+880 1700 111222',
        'role' => 'admin',
        'password' => 'Strong#Pass2026',
        'password_confirmation' => 'Strong#Pass2026',
        ...$overrides,
    ];
}

/** @return array<string, mixed> */
function adminDoctorPayload(array $overrides = []): array
{
    return [
        'name' => 'Dr. New Hire',
        'email' => 'new.hire@medipath.test',
        'phone' => '+880 1700 333444',
        'password' => 'Strong#Pass2026',
        'password_confirmation' => 'Strong#Pass2026',
        'specialty_id' => makeSpecialty('Neurology')->id,
        'license_number' => 'BMDC-N-90001',
        'license_authority' => 'Bangladesh Medical & Dental Council',
        'experience_years' => 5,
        'fee' => 850,
        ...$overrides,
    ];
}

/** @return array<string, mixed> */
function adminPatientPayload(array $overrides = []): array
{
    return [
        'name' => 'New Patient',
        'email' => 'new.patient@medipath.test',
        'phone' => '+880 1700 555666',
        'password' => 'Strong#Pass2026',
        'password_confirmation' => 'Strong#Pass2026',
        'age' => 34,
        'gender' => 'Female',
        'blood_group' => 'B+',
        'condition' => 'Hypertension',
        'address' => 'Dhanmondi, Dhaka',
        ...$overrides,
    ];
}

it('renders the master dashboard with server KPI and system props', function () {
    $admin = makeUser(['role' => 'admin']);

    $this->actingAs($admin)->get('/admin/dashboard?range=7')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->where('range', 7)
            ->has('kpis')
            ->has('system')
            ->has('attention')
            ->has('trend', 12));
});

it('expires an idle admin session server-side', function () {
    $admin = makeUser(['role' => 'admin']);

    $this->actingAs($admin)
        ->withSession(['admin.last_activity' => time() - (16 * 60)])
        ->get('/admin/dashboard')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('creates an internal user and a linked patient chart', function () {
    $admin = makeUser(['role' => 'admin']);

    $this->actingAs($admin)
        ->post('/admin/users', adminUserPayload(['role' => 'patient', 'email' => 'chart.patient@medipath.test']))
        ->assertRedirect();

    $user = User::query()->where('email', 'chart.patient@medipath.test')->firstOrFail();

    expect($user->role->value)->toBe('patient')
        ->and($user->status->value)->toBe('approved')
        ->and($user->patient)->not->toBeNull();

    expect(ActivityLog::query()->where('action', ActivityActions::USER_CREATED)->where('target_id', $user->id)->exists())->toBeTrue();
});

it('changes a role atomically and revokes existing sessions', function () {
    $admin = makeUser(['role' => 'admin']);
    $target = makeUser(['role' => 'patient']);
    makePatientRecord($target);

    $before = $target->session_version;

    DB::table('sessions')->insert([
        'id' => 'role-change-session',
        'user_id' => $target->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => base64_encode(serialize([])),
        'last_activity' => time(),
    ]);

    $this->actingAs($admin)
        ->post("/admin/users/{$target->id}/role", [
            'role' => 'doctor',
            'reason' => 'Moved to the clinical roster after credential review',
            'license_number' => 'BMDC-D-77881',
            'license_authority' => 'Bangladesh Medical & Dental Council',
            'specialty_id' => makeSpecialty('Dermatology')->id,
            'experience_years' => 6,
            'fee' => 900,
        ])
        ->assertRedirect();

    $target->refresh();

    expect($target->role->value)->toBe('doctor')
        ->and($target->session_version)->toBe($before + 1)
        ->and($target->doctor)->not->toBeNull()
        ->and((int) $target->doctor->specialty_id)->toBe(makeSpecialty('Dermatology')->id)
        ->and(DB::table('sessions')->where('user_id', $target->id)->count())->toBe(0);

    expect(ActivityLog::query()->where('action', ActivityActions::USER_ROLE_CHANGED)->where('target_id', $target->id)->exists())->toBeTrue();
});

it('forbids changing your own role', function () {
    $admin = makeUser(['role' => 'admin']);

    $this->actingAs($admin)
        ->from('/admin/users')
        ->post("/admin/users/{$admin->id}/role", [
            'role' => 'patient',
            'reason' => 'Trying to demote myself by mistake',
        ])
        ->assertRedirect('/admin/users')
        ->assertSessionHasErrors('role');
});

it('suspends, restores and archives accounts with typed confirmation', function () {
    $admin = makeUser(['role' => 'admin']);
    $target = makeUser(['role' => 'patient', 'name' => 'Archive Target']);
    makePatientRecord($target);

    $this->actingAs($admin)
        ->post("/admin/users/{$target->id}/suspend", ['reason' => 'Repeated policy violations logged'])
        ->assertRedirect();

    expect($target->fresh()->status->value)->toBe('suspended');

    $this->actingAs($admin)->post("/admin/users/{$target->id}/restore")->assertRedirect();
    expect($target->fresh()->status->value)->toBe('approved');

    $this->actingAs($admin)
        ->post("/admin/users/{$target->id}/archive", ['confirm_name' => 'Wrong Name'])
        ->assertSessionHasErrors('confirm_name');

    $this->actingAs($admin)
        ->post("/admin/users/{$target->id}/archive", ['confirm_name' => 'Archive Target'])
        ->assertRedirect();

    expect($target->fresh()->status->value)->toBe('archived')
        ->and($target->fresh()->archived_at)->not->toBeNull()
        ->and($target->patient->fresh()->archived_at)->not->toBeNull();

    expect(ActivityLog::query()->where('action', ActivityActions::USER_ARCHIVED)->where('target_id', $target->id)->exists())->toBeTrue();
});

it('approves a pending sign-up and closes the queue item', function () {
    $admin = makeUser(['role' => 'admin']);
    $applicant = makeUser(['role' => 'patient', 'status' => 'pending']);
    $approval = PendingApproval::query()->create([
        'user_id' => $applicant->id,
        'requested_role' => 'patient',
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->post("/admin/approvals/{$approval->id}/approve")
        ->assertRedirect();

    expect($approval->fresh()->status)->toBe('approved')
        ->and($approval->fresh()->reviewer_id)->toBe($admin->id)
        ->and($applicant->fresh()->status->value)->toBe('approved');

    expect(ActivityLog::query()->where('action', ActivityActions::APPROVAL_APPROVED)->exists())->toBeTrue();
});

it('rejects a sign-up only with a substantive reason', function () {
    $admin = makeUser(['role' => 'admin']);
    $applicant = makeUser(['role' => 'patient', 'status' => 'pending']);
    $approval = PendingApproval::query()->create([
        'user_id' => $applicant->id,
        'requested_role' => 'patient',
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->post("/admin/approvals/{$approval->id}/reject", ['review_reason' => 'short'])
        ->assertSessionHasErrors('review_reason');

    $this->actingAs($admin)
        ->post("/admin/approvals/{$approval->id}/reject", ['review_reason' => 'Document is expired and unreadable.'])
        ->assertRedirect();

    expect($approval->fresh()->status)->toBe('rejected')
        ->and($approval->fresh()->review_reason)->toBe('Document is expired and unreadable.')
        ->and($applicant->fresh()->status->value)->toBe('rejected');
});

it('serves credentials only to admins and stamps each preview', function () {
    $admin = makeUser(['role' => 'admin']);
    $patient = makeUser(['role' => 'patient']);
    $approval = PendingApproval::query()->create([
        'user_id' => $patient->id,
        'requested_role' => 'doctor',
        'status' => 'pending',
        'license_details' => [
            'license_number' => 'BMDC-PREVIEW-1',
            'license_authority' => 'Bangladesh Medical & Dental Council',
        ],
        'credential_path' => 'registrations/doctor/missing-demo.pdf',
    ]);

    $this->actingAs($admin)
        ->get("/admin/approvals/{$approval->id}/credential")
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

    expect(ActivityLog::query()->where('action', ActivityActions::CREDENTIAL_PREVIEWED)->exists())->toBeTrue();
});

it('verifies, resubmits and revokes doctor credentials', function () {
    $admin = makeUser(['role' => 'admin']);

    foreach (['approve' => 'verified', 'resubmit' => 'resubmission', 'revoke' => 'revoked'] as $decision => $expected) {
        $doctorUser = makeUser(['role' => 'doctor', 'status' => $decision === 'approve' ? 'pending' : 'approved']);
        $doctor = makeDoctorRecord($doctorUser, [
            'verification_status' => 'pending',
            'license_number' => 'BMDC-VERIFY-'.$decision,
        ]);

        $this->actingAs($admin)
            ->post("/admin/doctors/{$doctor->id}/verification", [
                'decision' => $decision,
                'reason' => $decision === 'approve' ? null : 'License document expired; upload the renewed copy.',
            ])
            ->assertRedirect();

        expect($doctor->fresh()->verification_status->value)->toBe($expected);
    }

    expect(ActivityLog::query()->where('action', ActivityActions::DOCTOR_VERIFIED)->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('action', ActivityActions::DOCTOR_RESUBMISSION_REQUESTED)->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('action', ActivityActions::DOCTOR_VERIFICATION_REVOKED)->exists())->toBeTrue();
});

it('creates, edits, soft-archives and restores a doctor', function () {
    $admin = makeUser(['role' => 'admin']);

    $this->actingAs($admin)->post('/admin/doctors', adminDoctorPayload())->assertRedirect(route('admin.doctors.index'));

    $doctor = Doctor::query()->where('license_number', 'BMDC-N-90001')->firstOrFail();
    $doctorUser = $doctor->user;

    expect($doctor->verification_status->value)->toBe('pending')
        ->and($doctorUser->role->value)->toBe('doctor');

    $this->actingAs($admin)
        ->put("/admin/doctors/{$doctor->id}", adminDoctorPayload([
            'email' => $doctorUser->email,
            'password' => null,
            'password_confirmation' => null,
            'fee' => 975,
            'experience_years' => 6,
        ]))
        ->assertRedirect();

    expect((float) $doctor->fresh()->fee)->toBe(975.0);

    $this->actingAs($admin)
        ->post("/admin/doctors/{$doctor->id}/archive", ['confirm_name' => 'Dr. New Hire'])
        ->assertRedirect();

    expect($doctor->fresh()->archived_at)->not->toBeNull()
        ->and($doctorUser->fresh()->status->value)->toBe('archived');

    $this->actingAs($admin)->post("/admin/doctors/{$doctor->id}/restore")->assertRedirect();

    expect($doctor->fresh()->archived_at)->toBeNull()
        ->and($doctorUser->fresh()->status->value)->toBe('approved');
});

it('registers a patient and audits the read-only portal preview', function () {
    $admin = makeUser(['role' => 'admin']);

    $this->actingAs($admin)->post('/admin/patients', adminPatientPayload())->assertRedirect(route('admin.patients.index'));

    $patient = Patient::query()->where('name', 'New Patient')->firstOrFail();

    $this->actingAs($admin)
        ->get("/admin/patients?tab=portal&portal={$patient->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Patients')
            ->where('tab', 'portal')
            ->where('portal.patient.code', $patient->patient_code));

    expect(ActivityLog::query()->where('action', ActivityActions::PATIENT_PORTAL_VIEWED)->where('target_id', $patient->id)->exists())->toBeTrue();
});

it('blocks non-admins from every admin governance endpoint', function () {
    $patient = makeUser(['role' => 'patient']);
    makePatientRecord($patient);

    $this->actingAs($patient)->get('/admin/users')->assertForbidden();
    $this->actingAs($patient)->get('/admin/approvals')->assertForbidden();
    $this->actingAs($patient)->get('/admin/doctors')->assertForbidden();
    $this->actingAs($patient)->get('/admin/patients')->assertForbidden();
    $this->actingAs($patient)->post('/admin/users', adminUserPayload())->assertForbidden();
});