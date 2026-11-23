export interface NavItem {
    label: string;
    href: string;
    icon: string;
    /** data-bound count slot; null renders no chip. */
    count?: number | null;
    exact?: boolean;
}

export interface RoleNav {
    label: string;
    items: NavItem[];
}

/**
 * Navigation structure is UI copy (not a role/specialty label). The role badge
 * itself always comes from the server (users.role) — never from this map.
 * Hrefs are filled in as each phase ships.
 */
export const ROLE_NAV: Record<string, RoleNav> = {
    admin: {
        label: 'Administration',
        items: [
            { label: 'Dashboard & Overview', href: '/admin/dashboard', icon: '📊', exact: true },
            { label: 'User Management', href: '/admin/users', icon: '👥' },
            { label: 'Approvals & Verification', href: '/admin/approvals', icon: '✅', count: 0 },
            { label: 'Doctors', href: '/admin/doctors', icon: '🩺' },
            { label: 'Patients & Portal', href: '/admin/patients', icon: '🧑' },
            { label: 'Appointments', href: '/admin/appointments', icon: '📅' },
            { label: 'Clinical & Billing Records', href: '/admin/records', icon: '🗂' },
            { label: 'Revenue & Analytics', href: '/admin/reports', icon: '📈' },
            { label: 'Activity Log', href: '/admin/activity', icon: '🧾' },
            { label: 'FAQs & Reviews', href: '/admin/faq-reviews', icon: '💬', count: 0 },
            { label: 'Emergency & AI Rules', href: '/admin/safety', icon: '🛡' },
            { label: 'System Settings', href: '/admin/settings', icon: '⚙️' },
            { label: 'Data Management', href: '/admin/data', icon: '💾' },
            { label: 'Admin Security', href: '/admin/security', icon: '🔐' },
        ],
    },
    doctor: {
        label: 'Clinical workspace',
        items: [
            { label: 'Dashboard', href: '/doctor/dashboard', icon: '🏠', exact: true },
            { label: 'Schedule & Leave', href: '/doctor/schedule', icon: '🗓' },
            { label: 'Appointment Requests', href: '/doctor/requests', icon: '📥', count: 0 },
            { label: 'Patients', href: '/doctor/patients', icon: '🧑' },
            { label: 'Prescribe (Medicines)', href: '/doctor/prescriptions/new', icon: '💊', exact: true },
            { label: 'Prescription History', href: '/doctor/prescriptions', icon: '📜', exact: true },
            { label: 'Walk-In Registration', href: '/doctor/walk-in', icon: '🚪' },
            { label: 'Notifications', href: '/doctor/notifications', icon: '🔔', count: 0 },
            { label: 'My Reviews', href: '/doctor/reviews', icon: '⭐' },
            { label: 'Settings', href: '/doctor/settings', icon: '⚙️' },
        ],
    },
    pharmacist: {
        label: 'Pharmacy',
        items: [
            { label: 'Dashboard', href: '/pharmacist/dashboard', icon: '🏠', exact: true },
            { label: 'Pending Prescriptions', href: '/pharmacist/prescriptions', icon: '📥', count: 0 },
            { label: 'Medicine Inventory', href: '/pharmacist/inventory', icon: '📦' },
            { label: 'Stock & Expiry Alerts', href: '/pharmacist/alerts', icon: '🔔', count: 0 },
            { label: 'Billing & Invoices', href: '/pharmacist/invoices', icon: '🧾' },
            { label: 'Workflow Tracker', href: '/pharmacist/workflow', icon: '🧭' },
            { label: 'Notifications', href: '/pharmacist/notifications', icon: '🔔', count: 0 },
            { label: 'Settings', href: '/pharmacist/settings', icon: '⚙️' },
        ],
    },
    patient: {
        label: 'Patient portal',
        items: [
            { label: 'Dashboard', href: '/patient/dashboard', icon: '🏠', exact: true },
            { label: 'Find Doctors', href: '/patient/doctors', icon: '🔍' },
            { label: 'My Appointments', href: '/patient/appointments', icon: '📅', count: 0 },
            { label: 'AI Symptom Checker', href: '/patient/symptom-checker', icon: '🤖' },
            { label: 'My Prescriptions', href: '/patient/prescriptions', icon: '💊', count: 0 },
            { label: 'My Bills', href: '/patient/bills', icon: '🧾' },
            { label: 'Allergies & Medicines', href: '/patient/clinical-profile', icon: '⚠️' },
            { label: 'Reviews', href: '/patient/reviews', icon: '⭐' },
            { label: 'Notifications', href: '/patient/notifications', icon: '🔔', count: 0 },
            { label: 'Settings', href: '/patient/settings', icon: '⚙️' },
        ],
    },
};

export const PUBLIC_NAV = [
    { label: 'Find Doctors', href: '/doctors' },
    { label: 'Emergency Info', href: '/emergency', emergency: true },
];