import type { DataState } from '@/types';

export interface PharmacyKpis {
    pending: number;
    oldestHours: number | null;
    lowStock: number;
    outOfStock: number;
    billedTodayFormatted: string;
    invoicesToday: number;
    dispensedToday: number;
}

export interface PharmacyQueueRow {
    id: number;
    code: string;
    patientName: string | null;
    patientCode: string | null;
    doctorName: string | null;
    items: number;
    itemNames?: string[];
    waitingHours: number;
    hasOverride: boolean;
    stockState: 'all_in_stock' | 'partial' | 'out_of_stock';
    createdAt?: string | null;
}

export interface PharmacyStockRow {
    id: number;
    name: string;
    batch?: string | null;
    stock: number;
    threshold: number;
    status: string;
    expiryStatus: string;
    expiryDate: string | null;
    valueFormatted?: string;
}

export interface PharmacySafety {
    allergies: Array<{ medicine: string; substance: string; severity: string; severityLabel: string; classMatch: boolean; notes: string | null }>;
    interactions: Array<{ id: number; a: string; b: string; severity: string; severityLabel: string; description: string; management: string | null; blocking: boolean }>;
    stock: Array<{ medicine: string; kind: string; available: number; requested: number }>;
    blocked: boolean;
    expired: boolean;
    requiresOverride: boolean;
    checkedAt: string;
}

export interface PharmacyGateItem {
    id: number;
    medicineId: number;
    name: string;
    dosage: string;
    schedule: string;
    duration: string;
    quantity: number;
    stock: number;
    batch: string | null;
    expiryDate: string | null;
    stockStatus: string;
}

export interface PharmacyMedicineRow {
    id: number;
    name: string;
    displayName: string;
    genericName: string | null;
    category: string;
    strength: string | null;
    form: string;
    manufacturer: string | null;
    unitPrice: string;
    priceFormatted: string;
    stock: number;
    threshold: number;
    stockStatus: string;
    batch: string | null;
    expiryDate: string | null;
    expiryStatus: string;
    requiresPrescription: boolean;
    sideEffects: string;
    contraindications: string | null;
    description: string | null;
}

export interface PharmacyInvoiceRow {
    id: number;
    code: string;
    patientName: string | null;
    patientCode: string | null;
    prescriptionCode: string | null;
    netFormatted: string;
    currencyCode: string;
    method: string;
    methodLabel: string;
    status: string;
    statusLabel: string;
    paidFormatted: string;
    createdAt: string | null;
    pdfUrl: string;
}

export interface PharmacyWorkflowStage {
    key: string;
    label: string;
    done: boolean;
    sub: string;
    detail: string;
    href: string | null;
    action: string | null;
    state?: 'done' | 'current' | 'upcoming' | 'blocked';
    reason?: string | null;
}

export interface PharmacyPageProps {
    dataState: DataState;
    lastKnownAt: string;
}