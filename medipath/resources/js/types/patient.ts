import type { DataState } from '@/types';

export interface PatientNextAppointment {
    id: number;
    code: string;
    doctorName: string | null;
    doctorCode: string | null;
    specialty: string | null;
    date: string | null;
    time: string;
    room: string | null;
    clinic: string | null;
    status: string;
    statusLabel: string;
    feeFormatted: string;
    canCancel: boolean;
    canReschedule: boolean;
}

export interface PatientAppointmentRow {
    id: number;
    code: string;
    date: string | null;
    time: string;
    doctorName: string | null;
    doctorCode: string | null;
    specialty: string | null;
    room: string | null;
    status: string;
    statusLabel: string;
    visitReason: string;
    feeFormatted: string;
    cancellationReason: string | null;
    reviewStatus: string | null;
    canCancel: boolean;
    canReschedule: boolean;
    canReview: boolean;
}

export interface PatientPrescription {
    id: number;
    code: string;
    doctorName: string | null;
    specialty: string | null;
    status: string;
    statusLabel: string;
    createdAt: string | null;
    patientNotes: string | null;
    clinicalNotes: string | null;
    safe: boolean;
    items: Array<{
        id: number;
        name: string | null;
        strength: string | null;
        genericName: string | null;
        dosage: string;
        schedule: string;
        duration: string;
        quantity: number;
    }>;
    invoiceCode: string | null;
    pdfUrl: string;
}

export interface PatientInvoice {
    id: number;
    code: string;
    createdAt: string | null;
    items: string[];
    netFormatted: string;
    method: string | null;
    status: string;
    statusLabel: string;
    prescriptionCode: string | null;
    pdfUrl: string;
}

export interface PatientAllergy {
    id: number;
    substance: string;
    type: string;
    severity: string;
    severityLabel: string;
    reactionNotes: string | null;
    createdAt: string | null;
}

export interface PatientMedication {
    id: number;
    name: string;
    dosage: string;
    frequency: string;
    startDate: string | null;
    endDate: string | null;
    isActive: boolean;
    notes: string | null;
}

export interface PatientReview {
    id: number;
    rating: number;
    comment: string | null;
    status: string;
    statusLabel: string;
    moderationReason: string | null;
    doctorName: string | null;
    specialty: string | null;
    appointmentCode: string | null;
    appointmentDate: string | null;
    createdAt: string | null;
}

export interface PatientNotification {
    id: number;
    type: string;
    category: string;
    title: string;
    message: string;
    actionUrl: string | null;
    readAt: string | null;
    createdAt: string | null;
}

export interface PatientSettings {
    dataState?: DataState;
}

export interface TriageEmergency {
    kind: 'emergency';
    rule: { id: number; title: string; category: string; severity: string; directive: string; while_waiting: string[] };
    contacts: Array<{ key: string; label: string; number: string; tel: string; icon: string | null }>;
    source: 'deterministic';
    ai_attempted: boolean;
    checked_at: string;
}

export interface TriageGuidance {
    kind: 'guidance';
    source: 'ai' | 'deterministic_fallback';
    urgency: 'self_care' | 'monitor' | 'urgent';
    summary: string;
    questions: string[];
    specialty: { id: number | null; name: string | null; slug: string | null; matched_category: string | null; browseUrl: string };
    doctors: Array<{
        id: number;
        name: string;
        initials: string;
        specialty: string;
        verified: boolean;
        experienceYears: number;
        rating: number;
        reviewCount: number;
        feeFormatted: string;
        nextAvailable: string | null;
        hasSlots: boolean;
        bookingUrl: string;
    }>;
    disclaimer: string;
    checked_at: string;
}
