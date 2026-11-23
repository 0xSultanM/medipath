<?php

namespace App\Support;

/** Canonical activity-log action names (avoid string drift in services). */
final class ActivityActions
{
    public const AUTH_LOGIN = 'auth.login';

    public const AUTH_LOGOUT = 'auth.logout';

    public const AUTH_LOGIN_FAILED = 'auth.login_failed';

    public const AUTH_PASSWORD_RESET = 'auth.password_reset';

    public const AUTH_SESSION_REVOKED = 'auth.sessions_revoked';

    public const USER_CREATED = 'user.created';

    public const USER_UPDATED = 'user.updated';

    public const USER_ROLE_CHANGED = 'user.role_changed';

    public const USER_SUSPENDED = 'user.suspended';

    public const USER_ARCHIVED = 'user.archived';

    public const USER_RESTORED = 'user.restored';

    public const APPROVAL_APPROVED = 'approval.approved';

    public const APPROVAL_REJECTED = 'approval.rejected';

    public const DOCTOR_VERIFIED = 'doctor.verified';

    public const DOCTOR_VERIFICATION_REVOKED = 'doctor.verification_revoked';

    public const DOCTOR_RESUBMISSION_REQUESTED = 'doctor.resubmission_requested';

    public const SETTINGS_UPDATED = 'settings.updated';

    public const REVENUE_CONFIGURED = 'revenue.configured';

    public const EMERGENCY_RULE_MATCHED = 'safety.emergency_rule_matched';

    public const SAFETY_OVERRIDE = 'safety.override_recorded';

    public const PATIENT_PORTAL_VIEWED = 'patient.portal_viewed';

    public const CREDENTIAL_PREVIEWED = 'credential.previewed';

    public const APPOINTMENT_CANCELLED = 'appointment.cancelled_by_admin';

    public const FAQ_CREATED = 'faq.created';

    public const FAQ_UPDATED = 'faq.updated';

    public const REVIEW_MODERATED = 'review.moderated';

    public const SAFETY_UPDATED = 'safety.config_updated';

    public const REPORT_EXPORTED = 'report.exported';

    public const DATA_EXPORTED = 'data.exported';

    public const DATA_IMPORTED = 'data.imported';

    public const BACKUP_CREATED = 'backup.created';

    public const BACKUP_DELETED = 'backup.deleted';

    public const SECURITY_PASSWORD_CHANGED = 'security.password_changed';

    public const SECURITY_SESSIONS_REVOKED = 'security.sessions_revoked';

    public const APPOINTMENT_BOOKED = 'appointment.booked';

    public const APPOINTMENT_RESCHEDULED = 'appointment.rescheduled';

    public const APPOINTMENT_CANCELLED_PATIENT = 'appointment.cancelled_by_patient';

    public const ALLERGY_ADDED = 'clinical.allergy_added';

    public const ALLERGY_UPDATED = 'clinical.allergy_updated';

    public const ALLERGY_REMOVED = 'clinical.allergy_removed';

    public const MEDICATION_ADDED = 'clinical.medication_added';

    public const MEDICATION_UPDATED = 'clinical.medication_updated';

    public const REVIEW_SUBMITTED = 'review.submitted';

    public const PATIENT_PROFILE_UPDATED = 'patient.profile_updated';

    public const PATIENT_PASSWORD_CHANGED = 'patient.password_changed';

    public const PATIENT_PREFERENCES_UPDATED = 'patient.preferences_updated';

    public const APPOINTMENT_ACCEPTED = 'appointment.accepted';

    public const APPOINTMENT_REJECTED = 'appointment.rejected';

    public const APPOINTMENT_COMPLETED = 'appointment.completed';

    public const APPOINTMENT_CANCELLED_DOCTOR = 'appointment.cancelled_by_doctor';

    public const SCHEDULE_UPDATED = 'doctor.schedule_updated';

    public const LEAVE_UPDATED = 'doctor.leave_updated';

    public const WALK_IN_REGISTERED = 'patient.walk_in_registered';

    public const PRESCRIPTION_CREATED = 'prescription.created';

    public const DOCTOR_PROFILE_UPDATED = 'doctor.profile_updated';

    public const DOCTOR_PASSWORD_CHANGED = 'doctor.password_changed';

    public const PHARMACIST_PROFILE_UPDATED = 'pharmacist.profile_updated';

    public const PHARMACIST_PASSWORD_CHANGED = 'pharmacist.password_changed';

    public const MEDICINE_CREATED = 'medicine.created';

    public const MEDICINE_UPDATED = 'medicine.updated';

    public const MEDICINE_ARCHIVED = 'medicine.archived';

    public const STOCK_RESTOCKED = 'stock.restocked';

    public const STOCK_DEDUCTED = 'stock.deducted';

    public const PRESCRIPTION_DISPENSED = 'prescription.dispensed';

    public const PRESCRIPTION_REFUSED = 'prescription.refused';

    public const INVOICE_CREATED = 'invoice.created';

    public const INVOICE_PAID = 'invoice.paid';

    public const INVOICE_REFUNDED = 'invoice.refunded';



}
