export type DataState = 'success' | 'skeleton' | 'empty' | 'error';

export type Tone =
    | 'neutral'
    | 'primary'
    | 'success'
    | 'warning'
    | 'danger'
    | 'danger-outline'
    | 'info'
    | 'emergency'
    | 'neutral-strong';

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    role: string;
    roleLabel: string;
    portal: string;
    initials: string;
    status: string;
    statusLabel: string;
    verificationStatus: string;
    verificationLabel: string;
    avatarUrl: string | null;
    dashboardUrl: string;
    theme: 'light' | 'dark' | 'system';
    unreadNotifications: number;
}

export interface AppSettings {
    currencyCode: string;
    currencySymbol: string;
    locale: string;
    timezone: string;
    dateFormat: string;
    clinicName: string;
    patientAutoApprove: boolean;
    cancellationCutoffHours: number;
    revenuePercentage: number;
    revenueConfigured: boolean;
    themeDefault: string;
}

export interface NotificationItem {
    id: number;
    title: string;
    message: string;
    category: string;
    actionUrl?: string | null;
    action_url?: string | null;
    readAt?: string | null;
    read_at?: string | null;
    createdAt?: string | null;
    created_at?: string | null;
}

export interface SharedProps {
    [key: string]: unknown;
    auth: { user: AuthUser | null };
    ui: {
        theme: 'light' | 'dark';
        themePreference: 'light' | 'dark' | 'system';
        reduceMotion: boolean;
        appName: string;
        initialState?: DataState;
    };
    settings: AppSettings;
    labels: Record<string, Record<string, string>>;
    tones: Record<string, Record<string, Tone>>;
    flash: {
        success?: string | null;
        error?: string | null;
        info?: string | null;
        demoResetLink?: string | null;
    };
    hotlines: EmergencyContactItem[];
    notificationsFeed: NotificationItem[];
    csrfToken: string;
    errors?: Record<string, string>;
}

export interface PageMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface Paginated<T> {
    data: T[];
    meta: PageMeta;
    links?: Record<string, string | null>;
}

export interface DoctorSummary {
    id: number;
    name: string;
    initials: string;
    photoUrl?: string | null;
    specialty: string;
    verified: boolean;
    experienceYears: number;
    clinic?: string | null;
    address?: string | null;
    rating: number;
    reviewCount: number;
    feeFormatted: string;
    nextAvailable?: string | null;
    hasSlots: boolean;
    profileUrl?: string;
    doctorCode?: string;
    licenseMasked?: string;
    licenseAuthority?: string;
    verifiedAt?: string | null;
    bio?: string | null;
    room?: string | null;
    followUpFeeFormatted?: string | null;
}

export interface Slot {
    time: string;
    kind: 'open' | 'selected' | 'booked' | 'past' | 'leave' | 'closed';
    label?: string;
}

export interface DateChip {
    date: string;
    day: string;
    num: string;
    isToday?: boolean;
    isOff?: boolean;
}

export interface AvailabilityRow {
    date: string;
    label: string;
    day_of_week: number;
    is_today: boolean;
    windows: string;
    open_slots: number;
    total_slots: number;
    status: 'available' | 'leave' | 'closed' | 'full';
    open_times: string[];
}

export interface ReviewItem {
    id: number;
    rating: number;
    comment: string | null;
    createdAt: string | null;
    patientName: string;
    initials: string;
}

export interface RatingSummaryData {
    average: number;
    total: number;
    distribution: Record<number, number>;
}

export interface DirectoryFilters {
    q: string;
    specialty: string;
    min_rating: number;
    available_today: boolean;
    weekdays: number[];
    sort: 'next_available' | 'rating' | 'experience' | 'fee';
    date: string | null;
}

export interface BookingGate {
    label: string;
    href: string | null;
    note: string;
    disabled: boolean;
}

export interface PendingApplication {
    state: 'pending' | 'rejected' | 'approved' | 'none';
    status: string;
    statusLabel: string;
    requestedRole: string | null;
    requestedRoleLabel: string | null;
    queuePosition: number | null;
    totalPending: number;
    reviewReason: string | null;
    submittedAt: string | null;
    reviewedAt: string | null;
    dashboardUrl: string;
    signOutUrl: string;
    checked_at?: string;
}

export interface DemoAccount {
    name: string;
    email: string;
    roleLabel: string;
    statusLabel: string;
}

export interface EmergencyContactItem {
    key: string;
    label: string;
    number: string;
    tel: string;
    icon?: string | null;
}

export interface StatusBadgeLabels {
    label: string;
    tone?: Tone;
}