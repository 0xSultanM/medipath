import type { DataState } from '@/types';

export interface DoctorRequestRow {
    id: number;
    code: string;
    date: string | null;
    time: string;
    patientId: number;
    patient: string | null;
    patientCode: string | null;
    reason: string;
    status: string;
    statusLabel: string;
    isWalkIn: boolean;
    rejectionReason: string | null;
    cancellationReason: string | null;
    requestedAt: string | null;
}

export interface DoctorPatientRow {
    id: number;
    code: string;
    name: string;
    initials: string;
    age: number | null;
    gender: string | null;
    condition: string | null;
    allergyCount: number;
    severeAllergyCount: number;
    allergySubstances: string[];
    lastVisit: string | null;
    nextVisit: string | null;
}

export interface DoctorScheduleBlock {
    id: number;
    dayOfWeek: number;
    dayLabel: string;
    start: string;
    end: string;
    slotDuration: number;
    breakStart: string | null;
    breakEnd: string | null;
    isActive: boolean;
}

export interface DoctorLeaveRow {
    id: number;
    start: string | null;
    end: string | null;
    reason: string;
    impacted: number;
}

export interface DoctorMedicine {
    id: number;
    name: string;
    genericName: string | null;
    strength: string | null;
    form: string | null;
    category: string | null;
    batch: string | null;
    stock: number;
    stockStatus: string;
    expiryStatus: string;
    expiryDate: string | null;
    priceFormatted: string;
    sideEffects: string[];
    contraindications?: string | null;
    requiresPrescription: boolean;
}

export interface PrescriptionSafety {
    allergies: Array<{ medicine: string; substance: string; severity: string; severityLabel: string; classMatch: boolean; notes: string | null }>;
    interactions: Array<{ id: number; a: string; b: string; severity: string; severityLabel: string; description: string; management: string | null; blocking: boolean }>;
    stock: Array<{ medicine: string; kind: string; available: number; requested: number }>;
    blocked: boolean;
    expired: boolean;
    requiresOverride: boolean;
    checkedAt: string;
}

export interface PrescriptionAiReview {
    safety: PrescriptionSafety;
    ai: { summary: string; status: string; source: string };
}

export interface DoctorPrescriptionRow {
    id: number;
    code: string;
    doctor: string | null;
    status: string;
    statusLabel: string;
    hasOverride: boolean;
    overrideJustification: string | null;
    itemNames: string[];
    createdAt: string | null;
}

export interface DoctorReviewRow {
    id: number;
    patientName: string | null;
    rating: number;
    comment: string | null;
    status: string;
    statusLabel: string;
    moderationReason: string | null;
    appointmentCode: string | null;
    createdAt: string | null;
}

export interface DoctorDashboardProps {
    dataState: DataState;
}
