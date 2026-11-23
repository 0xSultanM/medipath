import type { DataState, PageMeta } from '@/types';

export interface AdminUserRow {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    initials: string;
    role: string;
    roleLabel: string;
    status: string;
    statusLabel: string;
    verificationLabel: string | null;
    lastLoginAt: string | null;
    createdAt: string | null;
    patientCode: string | null;
    doctorCode: string | null;
    pharmacistCode: string | null;
    licenseNumber: string | null;
    specialty: string | null;
    isSelf: boolean;
    isFinalAdmin: boolean;
}

export interface AdminApprovalRow {
    id: number;
    applicantName: string | null;
    initials: string | null;
    email: string | null;
    phone: string | null;
    requestedRole: string;
    requestedRoleLabel: string;
    specialty: string | null;
    licenseNumber: string | null;
    licenseAuthority: string | null;
    experienceYears: number | null;
    submittedAt: string | null;
    credentialAvailable: boolean;
    credentialUrl: string | null;
}

export interface AdminDoctorRow {
    id: number;
    name: string | null;
    initials: string | null;
    email: string | null;
    phone: string | null;
    code: string;
    specialtyId: number;
    specialty: string | null;
    licenseNumber: string;
    licenseAuthority: string;
    experienceYears: number;
    fee: number;
    feeFormatted: string;
    bio: string | null;
    clinicName: string | null;
    clinicAddress: string | null;
    room: string | null;
    isFeatured: boolean;
    verificationStatus: string;
    verificationLabel: string | null;
    bookings: number;
    archivedAt: string | null;
    dependencies: Record<string, number>;
}

export interface AdminVerificationRow {
    id: number;
    name: string | null;
    initials: string | null;
    email: string | null;
    specialty: string | null;
    licenseNumber: string;
    licenseAuthority: string;
    verificationStatus: string;
    verificationLabel: string | null;
    reviewReason: string | null;
    verifiedAt: string | null;
    archivedAt: string | null;
    credentialAvailable: boolean;
    credentialUrl: string | null;
    canVerify: boolean;
}

export interface AdminPatientRow {
    id: number;
    code: string;
    name: string;
    initials: string;
    age: number | null;
    gender: string | null;
    phone: string | null;
    bloodGroup: string | null;
    condition: string | null;
    address: string | null;
    isWalkIn: boolean;
    walkInCode: string | null;
    accountStatus: string | null;
    accountStatusLabel: string | null;
    accountEmail: string | null;
    archivedAt: string | null;
    dependencies: Record<string, number>;
}

export interface PortalPrescription {
    id: number;
    code: string;
    status: string;
    statusLabel: string;
    createdAt: string | null;
}

export interface PortalInvoice {
    id: number;
    code: string;
    netTotal: string;
    paymentStatus: string;
    paymentStatusLabel: string;
    createdAt: string | null;
}

export interface PortalAllergy {
    id: number;
    substance: string;
    severity: string;
    severityLabel: string;
}

export interface PortalMedication {
    id: number;
    name: string;
    dosage: string;
    frequency: string;
}

export interface PortalData {
    patient: {
        id: number;
        code: string;
        name: string;
        age: number | null;
        gender: string | null;
        phone: string | null;
        condition: string | null;
        accountStatus: string | null;
        accountStatusLabel: string | null;
    };
    prescriptions: PortalPrescription[];
    invoices: PortalInvoice[];
    allergies: PortalAllergy[];
    medications: PortalMedication[];
}

export interface AdminFilters {
    q: string;
    role?: string;
    status?: string;
    idle?: boolean;
    specialty?: number;
    verification?: string;
    scope?: string;
}

export interface AdminPageProps {
    meta: PageMeta;
    dataState: DataState;
    lastKnownAt: string;
}
export interface AdminAppointmentRow {
    id: number;
    code: string;
    date: string | null;
    startTime: string;
    patientName: string | null;
    patientCode: string | null;
    isWalkIn: boolean;
    doctorName: string | null;
    status: string;
    statusLabel: string;
    visitReason: string;
    cancellationReason: string | null;
    canCancel: boolean;
    createdAt: string | null;
}

export interface AdminPrescriptionRow {
    id: number;
    code: string;
    patientCode: string | null;
    patientName: string | null;
    doctorName: string | null;
    items: number;
    safety: 'clean' | 'override';
    safetyLabel: string;
    status: string;
    statusLabel: string;
    createdAt: string | null;
    pdfUrl: string;
}

export interface AdminInvoiceRow {
    id: number;
    code: string;
    patientCode: string | null;
    patientName: string | null;
    netFormatted: string;
    currencyCode: string;
    status: string;
    statusLabel: string;
    method: string | null;
    createdAt: string | null;
    pdfUrl: string;
}

export interface AdminInventoryRow {
    id: number;
    name: string;
    genericName: string | null;
    strength: string | null;
    batch: string | null;
    stock: number;
    threshold: number;
    stockStatus: string;
    expiryStatus: string;
    expiryDate: string | null;
    priceFormatted: string;
}

export interface AdminActivityEntry {
    id: number;
    createdAt: string | null;
    actorName: string;
    actorRole: string | null;
    actorRoleLabel: string | null;
    action: string;
    targetType: string | null;
    targetId: number | null;
    severity: 'notice' | 'warning' | 'critical';
    detail: Record<string, unknown> | null;
    ipAddress: string | null;
}

export interface AdminFaqRow {
    id: number;
    question: string;
    answer: string;
    category: string;
    keywords: string | null;
    isPublished: boolean;
    sortOrder: number;
    updatedAt: string | null;
}

export interface AdminReviewRow {
    id: number;
    patientName: string | null;
    doctorName: string | null;
    appointmentCode: string | null;
    appointmentDate: string | null;
    rating: number;
    comment: string | null;
    status: string;
    statusLabel: string;
    moderationReason: string | null;
    moderatedAt: string | null;
    createdAt: string | null;
}

export interface AdminEmergencyContact {
    id: number;
    key: string;
    label: string;
    number: string;
    icon: string | null;
    description: string | null;
    sortOrder: number;
    isActive: boolean;
}

export interface AdminEmergencyRule {
    id: number;
    category: string;
    title: string;
    keywords: string[];
    keywordsText: string;
    severity: string;
    actionDirective: string;
    hotlineKey: string | null;
    hotlineLabel: string | null;
    sortOrder: number;
    isActive: boolean;
}

export interface AdminSymptomMapping {
    id: number;
    symptomCategory: string;
    keywords: string[];
    keywordsText: string;
    specialtyId: number;
    specialty: string | null;
    priority: number;
    isActive: boolean;
}

export interface AdminBackupSnapshot {
    id: number;
    label: string;
    status: string;
    size: string;
    sizeBytes: number;
    path: string;
    createdBy: string | null;
    createdAt: string | null;
}

export interface AdminImportPreview {
    token: string;
    valid: number;
    invalid: number;
    updates: number;
    errors: Array<{ row: number; reason: string }>;
    fileName: string;
}
