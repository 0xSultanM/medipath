<?php

use App\Enums\AccountStatus;
use App\Enums\AppointmentStatus;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\BackupSnapshot;
use App\Models\Doctor;
use App\Models\Faq;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Review;
use App\Models\RevenueConfig;
use App\Models\Specialty;
use App\Models\SymptomSpecialtyMap;
use App\Models\User;
use App\Support\ActivityActions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array<string, mixed> */
function partThreeAppointment(array $overrides = []): array
{
    $patientUser = makeUser(['role' => 'patient']);
    $patient = makePatientRecord($patientUser);
    $doctorUser = makeUser(['role' => 'doctor', 'verification_status' => 'verified']);
    $doctor = makeDoctorRecord($doctorUser);

    $appointment = Appointment::query()->create([
        'appointment_code' => 'APT-T'.uniqid(),
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'date' => now()->addDay()->toDateString(),
        'start_time' => '10:00',
        'end_time' => '10:30',
        'status' => AppointmentStatus::Accepted,
        'visit_reason' => 'Follow-up consultation',
        'accepted_at' => now(),
        ...$overrides,
    ]);

    return [$appointment, $patient, $doctor];
}

it('renders every Part 3 admin screen', function () {
    $admin = makeUser(['role' => 'admin']);

    foreach ([
        '/admin/appointments' => 'Admin/Appointments',
        '/admin/records' => 'Admin/Records',
        '/admin/reports' => 'Admin/Reports',
        '/admin/activity' => 'Admin/ActivityLog',
        '/admin/faq-reviews' => 'Admin/Knowledge',
        '/admin/safety' => 'Admin/Safety',
        '/admin/settings' => 'Admin/Settings',
        '/admin/data' => 'Admin/Data',
        '/admin/security' => 'Admin/Security',
    ] as $route => $component) {
        $this->actingAs($admin)->get($route)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
    }
});

it('cancels an appointment on behalf with a reason and audit', function () {
    [$appointment] = partThreeAppointment();
    $admin = makeUser(['role' => 'admin']);

    $this->actingAs($admin)
        ->post("/admin/appointments/{$appointment->id}/cancel", ['reason' => 'Clinic closure due to flooding.'])
        ->assertRedirect();

    $appointment->refresh();
    expect($appointment->status)->toBe(AppointmentStatus::Cancelled)
        ->and((int) $appointment->cancelled_by)->toBe($admin->id)
        ->and($appointment->active_slot_key)->toBeNull();

    expect(ActivityLog::query()->where('action', ActivityActions::APPOINTMENT_CANCELLED)->where('target_id', $appointment->id)->exists())->toBeTrue();
});

it('exports reports as CSV and PDF', function () {
    $admin = makeUser(['role' => 'admin']);

    $csv = $this->actingAs($admin)->get('/admin/reports/export/csv?range=30');
    $csv->assertOk();
    expect($csv->headers->get('content-type'))->toContain('text/csv');

    $this->actingAs($admin)->get('/admin/reports/export/pdf?range=30')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect(ActivityLog::query()->where('action', ActivityActions::REPORT_EXPORTED)->count())->toBe(2);
});

it('creates, updates and toggles FAQ content', function () {
    $admin = makeUser(['role' => 'admin']);

    $this->actingAs($admin)->post('/admin/faqs', [
        'question' => 'How do I update my phone number?',
        'answer' => 'Open Settings and save the new number after verifying your password.',
        'category' => 'Account',
        'keywords' => 'phone, profile, update',
        'is_published' => true,
        'sort_order' => 21,
    ])->assertRedirect();

    $faq = Faq::query()->where('question', 'How do I update my phone number?')->firstOrFail();
    expect($faq->is_published)->toBeTrue();

    $this->actingAs($admin)->put("/admin/faqs/{$faq->id}", [
        'question' => $faq->question,
        'answer' => 'Open Settings → Profile and save the new number.',
        'category' => 'Account',
        'keywords' => 'phone, profile',
        'is_published' => true,
        'sort_order' => 21,
    ])->assertRedirect();

    $this->actingAs($admin)->post("/admin/faqs/{$faq->id}/toggle")->assertRedirect();
    expect($faq->fresh()->is_published)->toBeFalse();

    $this->actingAs($admin)->delete("/admin/faqs/{$faq->id}")->assertRedirect();
    expect(Faq::query()->whereKey($faq->id)->exists())->toBeFalse();
});

it('moderates a review with a reviewer note', function () {
    [$appointment, $patient, $doctor] = partThreeAppointment();
    $review = Review::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'appointment_id' => $appointment->id,
        'rating' => 4,
        'comment' => 'Good visit, but the pharmacy closes early.',
        'moderation_status' => 'pending',
    ]);
    $admin = makeUser(['role' => 'admin']);

    $this->actingAs($admin)->post("/admin/reviews/{$review->id}/moderate", [
        'decision' => 'reject',
        'note' => 'Contains another patient name.',
    ])->assertRedirect();

    expect($review->fresh()->moderation_status->value)->toBe('rejected')
        ->and($review->fresh()->moderation_reason)->toBe('Contains another patient name.')
        ->and((int) $review->fresh()->moderated_by)->toBe($admin->id);
});

it('manages safety contacts, red-flag rules and symptom mappings', function () {
    $admin = makeUser(['role' => 'admin']);
    $specialty = makeSpecialty('Neurology');

    $this->actingAs($admin)->post('/admin/safety/contacts', [
        'key' => 'pediatric_er',
        'label' => 'Pediatric ER',
        'number' => '+880 1700 009999',
        'icon' => '🏥',
        'sort_order' => 8,
        'is_active' => true,
    ])->assertRedirect();

    $this->actingAs($admin)->post('/admin/safety/mappings', [
        'symptom_category' => 'severe headache',
        'keywords' => 'headache, migraine, aura',
        'specialty_id' => $specialty->id,
        'priority' => 1,
        'is_active' => true,
    ])->assertRedirect();

    $mapping = SymptomSpecialtyMap::query()->where('symptom_category', 'severe headache')->firstOrFail();
    $this->actingAs($admin)->post("/admin/safety/mappings/{$mapping->id}/toggle")->assertRedirect();
    expect($mapping->fresh()->is_active)->toBeFalse();

    expect(ActivityLog::query()->where('action', ActivityActions::SAFETY_UPDATED)->count())->toBeGreaterThanOrEqual(3);
});

it('saves settings and creates an effective-dated revenue config', function () {
    $admin = makeUser(['role' => 'admin']);

    $this->actingAs($admin)->put('/admin/settings', [
        'clinic_name' => 'MediPath Test Clinic',
        'clinic_address' => 'Banani, Dhaka',
        'clinic_phone' => '+880 2 0000 0000',
        'pdf_header' => 'MediPath Test Clinic · official',
        'pdf_footer' => 'Generated electronically.',
        'currency_code' => 'BDT',
        'locale' => 'en',
        'timezone' => 'Asia/Dhaka',
        'date_format' => 'd M Y',
        'patient_auto_approve' => true,
        'cancellation_cutoff_hours' => 8,
        'ai_enabled' => false,
        'ai_model' => 'fallback',
        'revenue_percentage' => 9.5,
        'revenue_effective_from' => now()->toDateString(),
        'revenue_note' => 'Board-approved change',
    ])->assertRedirect();

    expect(RevenueConfig::query()->where('percentage', 9.5)->exists())->toBeTrue();
    expect(ActivityLog::query()->where('action', ActivityActions::REVENUE_CONFIGURED)->exists())->toBeTrue();
});

it('exports data, creates backups and runs the two-step import', function () {
    Storage::fake('backups');
    $admin = makeUser(['role' => 'admin']);
    makePatientRecord(makeUser(['role' => 'patient']));

    $this->actingAs($admin)->get('/admin/data/export/patients')
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $this->actingAs($admin)->post('/admin/data/backups', ['label' => 'Pest snapshot'])->assertRedirect();
    $snapshot = BackupSnapshot::query()->firstOrFail();
    expect($snapshot->status)->toBe('ready');

    $this->actingAs($admin)->post("/admin/data/backups/{$snapshot->id}/verify")->assertRedirect();
    expect($snapshot->fresh()->status)->toBe('verified');

    $csv = "name,generic_name,category,strength,form,unit_price,stock_quantity,min_stock_threshold,expiry_date,batch_number\n"
        ."Paracetamol,Acetaminophen,Analgesic,500 mg,tablet,2.50,100,20,2027-12-31,BATCH-PEST-1\n";
    $file = UploadedFile::fake()->createWithContent('medicines.csv', $csv);

    $this->actingAs($admin)->post('/admin/data/import/dry-run', ['file' => $file])->assertRedirect();
    $preview = session('import_preview');
    expect($preview['valid'])->toBe(1)
        ->and($preview['invalid'])->toBe(0);

    $this->actingAs($admin)->post('/admin/data/import/apply', [
        'token' => $preview['token'],
        'confirm' => true,
    ])->assertRedirect();

    expect(Medicine::query()->where('batch_number', 'BATCH-PEST-1')->exists())->toBeTrue();
});

it('updates admin profile, password and revokes other sessions', function () {
    $admin = makeUser(['role' => 'admin']);
    $before = $admin->session_version;

    $this->actingAs($admin)->put('/admin/security/profile', [
        'name' => 'Security Admin',
        'email' => $admin->email,
        'phone' => '+880 1700 000111',
        'idle_minutes' => 20,
    ])->assertRedirect();

    expect($admin->fresh()->name)->toBe('Security Admin');

    $this->actingAs($admin)->put('/admin/security/password', [
        'current_password' => 'MediPath#2026',
        'password' => 'NewStrong#2026',
        'password_confirmation' => 'NewStrong#2026',
    ])->assertRedirect();

    expect($admin->fresh()->session_version)->toBe($before + 1);

    $this->actingAs($admin)->post('/admin/security/sessions/revoke-others')->assertRedirect();
    expect($admin->fresh()->session_version)->toBe($before + 2);
});

it('blocks non-admins from Part 3 endpoints', function () {
    $patient = makeUser(['role' => 'patient']);
    makePatientRecord($patient);

    foreach (['/admin/appointments', '/admin/records', '/admin/reports', '/admin/activity', '/admin/faq-reviews', '/admin/safety', '/admin/settings', '/admin/data', '/admin/security'] as $route) {
        $this->actingAs($patient)->get($route)->assertForbidden();
    }
});

it('renders prescription and invoice PDFs', function () {
    $admin = makeUser(['role' => 'admin']);
    $pharmacist = makePharmacistRecord(makeUser(['role' => 'pharmacist']));
    [$appointment] = partThreeAppointment();
    $prescription = \App\Models\Prescription::query()->create([
        'prescription_code' => 'RX-PDF-'.uniqid(),
        'patient_id' => $appointment->patient_id,
        'doctor_id' => $appointment->doctor_id,
        'appointment_id' => $appointment->id,
        'status' => 'pending_dispense',
        'clinical_notes' => 'Review in two weeks.',
    ]);
    $invoice = \App\Models\Invoice::query()->create([
        'invoice_code' => 'INV-PDF-'.uniqid(),
        'patient_id' => $appointment->patient_id,
        'pharmacist_id' => $pharmacist->id,
        'prescription_id' => $prescription->id,
        'subtotal' => 100,
        'discount' => 0,
        'net_total' => 100,
        'currency_code' => 'BDT',
        'payment_method' => 'cash',
        'payment_status' => 'pending',
    ]);

    $this->actingAs($admin)->get("/admin/records/prescriptions/{$prescription->id}/pdf")
        ->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($admin)->get("/admin/records/invoices/{$invoice->id}/pdf")
        ->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('deletes failed backup snapshots through the guarded danger action', function () {
    Storage::fake('backups');
    $admin = makeUser(['role' => 'admin']);

    $this->actingAs($admin)->post('/admin/data/backups', ['label' => 'Will fail'])->assertRedirect();
    $snapshot = BackupSnapshot::query()->firstOrFail();
    $snapshot->forceFill(['status' => 'failed'])->save();

    $this->actingAs($admin)->post('/admin/data/danger', [
        'action' => 'delete_failed_backups',
        'password' => 'MediPath#2026',
        'confirmation' => 'DELETE',
    ])->assertRedirect();

    expect(BackupSnapshot::query()->whereKey($snapshot->id)->exists())->toBeFalse();
    expect(ActivityLog::query()->where('action', ActivityActions::BACKUP_DELETED)->exists())->toBeTrue();
});
