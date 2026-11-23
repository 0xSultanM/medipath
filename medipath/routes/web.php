<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\AppointmentController;
use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\DataController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DoctorController;
use App\Http\Controllers\Admin\KnowledgeController;
use App\Http\Controllers\Admin\PatientController;
use App\Http\Controllers\Admin\RecordController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SafetyController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DevDesignSystemController;
use App\Http\Controllers\FoundationController;
use App\Http\Controllers\Guest\DoctorDirectoryController;
use App\Http\Controllers\Guest\DoctorPublicProfileController;
use App\Http\Controllers\Guest\EmergencyInfoController;
use App\Http\Controllers\Guest\LandingController;
use App\Http\Controllers\Doctor\MedicineController as DoctorMedicineController;
use App\Http\Controllers\Doctor\NotificationController as DoctorNotificationController;
use App\Http\Controllers\Doctor\PatientController as DoctorPatientController;
use App\Http\Controllers\Doctor\PrescriptionController as DoctorPrescriptionController;
use App\Http\Controllers\Doctor\ProfileController as DoctorProfileController;
use App\Http\Controllers\Doctor\WalkInController as DoctorWalkInController;
use App\Http\Controllers\Doctor\WorkspaceController as DoctorWorkspaceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Pharmacist\InventoryController as PharmacistInventoryController;
use App\Http\Controllers\Pharmacist\InvoiceController as PharmacistInvoiceController;
use App\Http\Controllers\Pharmacist\NotificationController as PharmacistNotificationController;
use App\Http\Controllers\Pharmacist\PrescriptionController as PharmacistPrescriptionController;
use App\Http\Controllers\Pharmacist\ProfileController as PharmacistProfileController;
use App\Http\Controllers\Pharmacist\WorkspaceController as PharmacistWorkspaceController;
use App\Http\Controllers\Patient\AppointmentController as PatientAppointmentController;
use App\Http\Controllers\Patient\ClinicalProfileController as PatientClinicalProfileController;
use App\Http\Controllers\Patient\DashboardController as PatientDashboardController;
use App\Http\Controllers\Patient\DoctorBrowseController as PatientDoctorBrowseController;
use App\Http\Controllers\Patient\InvoiceController as PatientInvoiceController;
use App\Http\Controllers\Patient\NotificationController as PatientNotificationController;
use App\Http\Controllers\Patient\PrescriptionController as PatientPrescriptionController;
use App\Http\Controllers\Patient\ReviewController as PatientReviewController;
use App\Http\Controllers\Patient\SettingsController as PatientSettingsController;
use App\Http\Controllers\Patient\SymptomCheckerController as PatientSymptomCheckerController;
use App\Http\Controllers\PendingApprovalController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\RoleHomeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes — Phase 1 (Guest & Public)
|--------------------------------------------------------------------------
| Role prefixes and gates are already final. Guest pages are public and
| read-only; booking/reviews are gated to a signed-in patient.
*/

// ---------------------------------------------------------------- public
Route::get('/', LandingController::class)->name('home');
Route::get('/doctors', DoctorDirectoryController::class)->name('doctors');
Route::get('/doctors/{doctorCode}', DoctorPublicProfileController::class)->name('doctors.show');
Route::get('/emergency', EmergencyInfoController::class)->name('emergency');

// ---------------------------------------------------------------- auth
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:register')->name('register.store');

    Route::get('/forgot-password', [PasswordResetController::class, 'show'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'send'])
        ->middleware('throttle:forgot-password')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'show'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:reset-password')->name('password.update');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')->name('logout');

// Pending and rejected applicants share the holding screen.
Route::middleware(['auth', 'pending.only'])->group(function () {
    Route::get('/pending', [PendingApprovalController::class, 'show'])->name('pending.approval');
    Route::get('/pending/status', [PendingApprovalController::class, 'status'])
        ->middleware('throttle:pending-status')->name('pending.status');
});

// ---------------------------------------------------------------- authenticated portals
Route::middleware(['auth', 'role:admin', 'admin.idle'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/role', [UserController::class, 'changeRole'])->name('users.role');
    Route::post('/users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
    Route::post('/users/{user}/restore', [UserController::class, 'restore'])->name('users.restore');
    Route::post('/users/{user}/archive', [UserController::class, 'archive'])->name('users.archive');

    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('/approvals/{approval}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('/approvals/{approval}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');
    Route::get('/approvals/{approval}/credential', [ApprovalController::class, 'credential'])->name('approvals.credential');

    Route::get('/doctors', [DoctorController::class, 'index'])->name('doctors.index');
    Route::post('/doctors', [DoctorController::class, 'store'])->name('doctors.store');
    Route::put('/doctors/{doctor}', [DoctorController::class, 'update'])->name('doctors.update');
    Route::post('/doctors/{doctor}/archive', [DoctorController::class, 'archive'])->name('doctors.archive');
    Route::post('/doctors/{doctor}/restore', [DoctorController::class, 'restore'])->name('doctors.restore');
    Route::get('/doctors/{doctor}/credential', [ApprovalController::class, 'doctorCredential'])->name('doctors.credential');
    Route::post('/doctors/{doctor}/verification', [ApprovalController::class, 'verify'])->name('doctors.verification');

    Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
    Route::post('/patients', [PatientController::class, 'store'])->name('patients.store');
    Route::put('/patients/{patient}', [PatientController::class, 'update'])->name('patients.update');
    Route::post('/patients/{patient}/archive', [PatientController::class, 'archive'])->name('patients.archive');
    Route::post('/patients/{patient}/restore', [PatientController::class, 'restore'])->name('patients.restore');
    Route::get('/patients/{patient}/portal', [PatientController::class, 'portal'])->name('patients.portal');

    Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])->name('appointments.cancel');

    Route::get('/records', [RecordController::class, 'index'])->name('records.index');
    Route::get('/records/prescriptions/{prescription}/pdf', [RecordController::class, 'prescriptionPdf'])->name('records.prescriptions.pdf');
    Route::get('/records/invoices/{invoice}/pdf', [RecordController::class, 'invoicePdf'])->name('records.invoices.pdf');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export/csv', [ReportController::class, 'csv'])->name('reports.csv');
    Route::get('/reports/export/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');

    Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');

    Route::get('/faq-reviews', [KnowledgeController::class, 'index'])->name('knowledge.index');
    Route::post('/faqs', [KnowledgeController::class, 'storeFaq'])->name('faqs.store');
    Route::put('/faqs/{faq}', [KnowledgeController::class, 'updateFaq'])->name('faqs.update');
    Route::post('/faqs/{faq}/toggle', [KnowledgeController::class, 'toggleFaq'])->name('faqs.toggle');
    Route::delete('/faqs/{faq}', [KnowledgeController::class, 'destroyFaq'])->name('faqs.destroy');
    Route::post('/reviews/{review}/moderate', [KnowledgeController::class, 'moderateReview'])->name('reviews.moderate');

    Route::get('/safety', [SafetyController::class, 'index'])->name('safety.index');
    Route::post('/safety/contacts', [SafetyController::class, 'storeContact'])->name('safety.contacts.store');
    Route::put('/safety/contacts/{contact}', [SafetyController::class, 'updateContact'])->name('safety.contacts.update');
    Route::post('/safety/contacts/{contact}/toggle', [SafetyController::class, 'toggleContact'])->name('safety.contacts.toggle');
    Route::put('/safety/rules/{rule}', [SafetyController::class, 'updateRule'])->name('safety.rules.update');
    Route::post('/safety/rules/{rule}/toggle', [SafetyController::class, 'toggleRule'])->name('safety.rules.toggle');
    Route::post('/safety/mappings', [SafetyController::class, 'storeMapping'])->name('safety.mappings.store');
    Route::put('/safety/mappings/{mapping}', [SafetyController::class, 'updateMapping'])->name('safety.mappings.update');
    Route::post('/safety/mappings/{mapping}/toggle', [SafetyController::class, 'toggleMapping'])->name('safety.mappings.toggle');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('/data', [DataController::class, 'index'])->name('data.index');
    Route::get('/data/export/{type}', [DataController::class, 'export'])->name('data.export');
    Route::post('/data/backups', [DataController::class, 'backup'])->name('data.backups.store');
    Route::post('/data/backups/{snapshot}/verify', [DataController::class, 'verifyBackup'])->name('data.backups.verify');
    Route::post('/data/backups/{snapshot}/delete', [DataController::class, 'deleteBackup'])->name('data.backups.delete');
    Route::post('/data/import/dry-run', [DataController::class, 'importDryRun'])->name('data.import.dry-run');
    Route::post('/data/import/apply', [DataController::class, 'importApply'])->name('data.import.apply');
    Route::post('/data/danger', [DataController::class, 'danger'])->name('data.danger');

    Route::get('/security', [SecurityController::class, 'index'])->name('security.index');
    Route::put('/security/profile', [SecurityController::class, 'updateProfile'])->name('security.profile');
    Route::put('/security/password', [SecurityController::class, 'updatePassword'])->name('security.password');
    Route::post('/security/sessions/revoke-others', [SecurityController::class, 'revokeOtherSessions'])->name('security.sessions.revoke-others');
});

Route::middleware(['auth', 'role:doctor', 'verified.doctor'])->prefix('doctor')->name('doctor.')->group(function () {
    Route::get('/dashboard', [DoctorWorkspaceController::class, 'dashboard'])->name('dashboard');

    Route::get('/schedule', [DoctorWorkspaceController::class, 'schedule'])->name('schedule');
    Route::post('/schedule/blocks', [DoctorWorkspaceController::class, 'storeBlock'])->name('schedule.blocks.store');
    Route::post('/schedule/blocks/{block}/toggle', [DoctorWorkspaceController::class, 'toggleBlock'])->name('schedule.blocks.toggle');
    Route::post('/schedule/leaves', [DoctorWorkspaceController::class, 'storeLeave'])->name('schedule.leaves.store');
    Route::post('/schedule/leaves/{leave}/cancel', [DoctorWorkspaceController::class, 'cancelLeave'])->name('schedule.leaves.cancel');

    Route::get('/requests', [DoctorWorkspaceController::class, 'requests'])->name('requests');
    Route::post('/requests/{appointment}/accept', [DoctorWorkspaceController::class, 'accept'])->name('requests.accept');
    Route::post('/requests/{appointment}/reject', [DoctorWorkspaceController::class, 'reject'])->name('requests.reject');
    Route::post('/requests/{appointment}/complete', [DoctorWorkspaceController::class, 'complete'])->name('requests.complete');
    Route::post('/requests/{appointment}/cancel', [DoctorWorkspaceController::class, 'cancel'])->name('requests.cancel');

    Route::get('/patients', [DoctorPatientController::class, 'index'])->name('patients.index');
    Route::get('/patients/{patient}', [DoctorPatientController::class, 'show'])->name('patients.show');

    Route::get('/walk-in', [DoctorWalkInController::class, 'index'])->name('walk-in.index');
    Route::post('/walk-in', [DoctorWalkInController::class, 'store'])->name('walk-in.store');

    Route::get('/prescriptions', [DoctorPrescriptionController::class, 'index'])->name('prescriptions.index');
    Route::get('/prescriptions/new', [DoctorPrescriptionController::class, 'create'])->name('prescriptions.create');
    Route::post('/prescriptions/safety-check', [DoctorPrescriptionController::class, 'safetyCheck'])->name('prescriptions.safety');
    Route::post('/prescriptions/ai-review', [DoctorPrescriptionController::class, 'aiReview'])->name('prescriptions.ai');
    Route::post('/prescriptions', [DoctorPrescriptionController::class, 'store'])->name('prescriptions.store');
    Route::get('/prescriptions/{prescription}/pdf', [DoctorPrescriptionController::class, 'pdf'])->name('prescriptions.pdf');

    Route::get('/medicines/search', [DoctorMedicineController::class, 'search'])->name('medicines.search');
    Route::get('/medicines/{medicine}', [DoctorMedicineController::class, 'show'])->name('medicines.show');

    Route::get('/notifications', DoctorNotificationController::class)->name('notifications.index');

    Route::get('/reviews', [DoctorProfileController::class, 'reviews'])->name('reviews');
    Route::get('/settings', [DoctorProfileController::class, 'settings'])->name('settings');
    Route::put('/settings/profile', [DoctorProfileController::class, 'updateProfile'])->name('settings.profile');
    Route::put('/settings/password', [DoctorProfileController::class, 'updatePassword'])->name('settings.password');
    Route::put('/settings/preferences', [DoctorProfileController::class, 'updatePreferences'])->name('settings.preferences');
});

Route::middleware(['auth', 'role:pharmacist', 'pharmacist.approved'])->prefix('pharmacist')->name('pharmacist.')->group(function () {
    Route::get('/dashboard', [PharmacistWorkspaceController::class, 'dashboard'])->name('dashboard');
    Route::get('/alerts', [PharmacistWorkspaceController::class, 'alerts'])->name('alerts');
    Route::get('/workflow', [PharmacistWorkspaceController::class, 'workflow'])->name('workflow');

    Route::get('/prescriptions', [PharmacistPrescriptionController::class, 'index'])->name('prescriptions.index');
    Route::get('/prescriptions/{prescription}', [PharmacistPrescriptionController::class, 'show'])->name('prescriptions.show');
    Route::post('/prescriptions/{prescription}/refuse', [PharmacistPrescriptionController::class, 'refuse'])->name('prescriptions.refuse');
    Route::get('/prescriptions/{prescription}/dispense', [PharmacistPrescriptionController::class, 'dispensePage'])->name('prescriptions.dispense');
    Route::post('/prescriptions/{prescription}/quote', [PharmacistPrescriptionController::class, 'quoteTotals'])->name('prescriptions.quote');
    Route::post('/prescriptions/{prescription}/dispense', [PharmacistPrescriptionController::class, 'dispense'])->name('prescriptions.dispense.store');

    Route::get('/inventory', [PharmacistInventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory', [PharmacistInventoryController::class, 'store'])->name('inventory.store');
    Route::put('/inventory/{medicine}', [PharmacistInventoryController::class, 'update'])->name('inventory.update');
    Route::post('/inventory/{medicine}/restock', [PharmacistInventoryController::class, 'restock'])->name('inventory.restock');
    Route::post('/inventory/{medicine}/archive', [PharmacistInventoryController::class, 'archive'])->name('inventory.archive');

    Route::get('/invoices', [PharmacistInvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{invoice}/pdf', [PharmacistInvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::post('/invoices/{invoice}/status', [PharmacistInvoiceController::class, 'updateStatus'])->name('invoices.status');

    Route::get('/notifications', PharmacistNotificationController::class)->name('notifications.index');

    Route::get('/settings', [PharmacistProfileController::class, 'settings'])->name('settings');
    Route::put('/settings/profile', [PharmacistProfileController::class, 'updateProfile'])->name('settings.profile');
    Route::put('/settings/password', [PharmacistProfileController::class, 'updatePassword'])->name('settings.password');
    Route::put('/settings/preferences', [PharmacistProfileController::class, 'updatePreferences'])->name('settings.preferences');
});

Route::middleware(['auth', 'role:patient'])->prefix('patient')->name('patient.')->group(function () {
    Route::get('/dashboard', PatientDashboardController::class)->name('dashboard');

    Route::get('/doctors', PatientDoctorBrowseController::class)->name('doctors');
    Route::get('/book/{doctorCode}', [PatientAppointmentController::class, 'create'])->name('book');

    Route::get('/appointments', [PatientAppointmentController::class, 'index'])->name('appointments.index');
    Route::post('/appointments', [PatientAppointmentController::class, 'store'])->name('appointments.store');
    Route::post('/appointments/{appointment}/reschedule', [PatientAppointmentController::class, 'reschedule'])->name('appointments.reschedule');
    Route::post('/appointments/{appointment}/cancel', [PatientAppointmentController::class, 'cancel'])->name('appointments.cancel');

    Route::get('/prescriptions', [PatientPrescriptionController::class, 'index'])->name('prescriptions.index');
    Route::get('/prescriptions/{prescription}/pdf', [PatientPrescriptionController::class, 'pdf'])->name('prescriptions.pdf');

    Route::get('/bills', [PatientInvoiceController::class, 'index'])->name('bills.index');
    Route::get('/bills/{invoice}/pdf', [PatientInvoiceController::class, 'pdf'])->name('bills.pdf');

    Route::get('/clinical-profile', [PatientClinicalProfileController::class, 'index'])->name('clinical-profile.index');
    Route::post('/clinical-profile/allergies', [PatientClinicalProfileController::class, 'storeAllergy'])->name('allergies.store');
    Route::put('/clinical-profile/allergies/{allergy}', [PatientClinicalProfileController::class, 'updateAllergy'])->name('allergies.update');
    Route::delete('/clinical-profile/allergies/{allergy}', [PatientClinicalProfileController::class, 'destroyAllergy'])->name('allergies.destroy');
    Route::post('/clinical-profile/medications', [PatientClinicalProfileController::class, 'storeMedication'])->name('medications.store');
    Route::put('/clinical-profile/medications/{medication}', [PatientClinicalProfileController::class, 'updateMedication'])->name('medications.update');
    Route::post('/clinical-profile/medications/{medication}/toggle', [PatientClinicalProfileController::class, 'toggleMedication'])->name('medications.toggle');

    Route::get('/reviews', [PatientReviewController::class, 'index'])->name('reviews.index');
    Route::post('/reviews', [PatientReviewController::class, 'store'])->name('reviews.store');

    Route::get('/notifications', [PatientNotificationController::class, 'index'])->name('notifications.index');

    Route::get('/symptom-checker', PatientSymptomCheckerController::class)->name('symptom-checker');
    Route::post('/symptom-check', [PatientSymptomCheckerController::class, 'check'])->middleware('throttle:chatbot')->name('symptom-check');

    Route::get('/settings', [PatientSettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings/profile', [PatientSettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::put('/settings/password', [PatientSettingsController::class, 'updatePassword'])->name('settings.password');
    Route::put('/settings/preferences', [PatientSettingsController::class, 'updatePreferences'])->name('settings.preferences');
    Route::post('/settings/sessions/revoke-others', [PatientSettingsController::class, 'revokeOtherSessions'])->name('settings.sessions.revoke-others');
});

// Notifications / preferences (unchanged since Phase 0)
Route::middleware('auth')->prefix('notifications')->name('notifications.')->group(function () {
    Route::post('/read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
    Route::post('/{notification}/read', [NotificationController::class, 'markRead'])->name('read');
    Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
});

Route::middleware('auth')->post('/preferences/theme', [PreferenceController::class, 'theme'])
    ->name('preferences.theme');

// ---------------------------------------------------------------- local QA surfaces
Route::prefix('dev')->name('dev.')->group(function () {
    Route::get('/ui', [DevDesignSystemController::class, 'gallery'])->name('ui');
    Route::get('/layouts/{role}', [DevDesignSystemController::class, 'layout'])->name('layouts');
    Route::get('/frames/{width}', [DevDesignSystemController::class, 'frames'])->name('frames');
    Route::get('/foundation', FoundationController::class)->name('foundation');
});
