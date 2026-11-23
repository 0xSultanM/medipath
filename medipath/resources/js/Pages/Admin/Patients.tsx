/**
 * Admin · Patients & portal — prototypes/admin/05-patients.html.
 * Staff-mode patient CRUD (typed soft archive + dependency counts) and the
 * read-only as-patient portal tab. Portal reads are audited server-side.
 */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { DataState, PageMeta, SharedProps } from '@/types';
import type { AdminPatientRow, PortalData } from '@/types/admin';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { DataTable, type DataColumn } from '@/Components/Data/DataTable';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { AdminModal } from '@/Components/Admin/AdminModal';
import { PatientPortalPreview } from '@/Components/Admin/PatientPortalPreview';
import { cn, formatDate } from '@/lib/format';
import { useDebouncedValue } from '@/hooks/useUi';

interface PatientsProps {
    patients: AdminPatientRow[];
    meta: PageMeta;
    filters: { q: string; scope: string };
    tab: 'directory' | 'portal';
    portalId: number;
    portal: PortalData | null;
    genders: string[];
    dataState: DataState;
    lastKnownAt: string;
}

const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'];

export default function AdminPatients({
    patients,
    meta,
    filters,
    tab,
    portalId,
    portal,
    genders,
    dataState,
    lastKnownAt,
}: PatientsProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(dataState);
    const [search, setSearch] = useState(filters.q);
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<AdminPatientRow | null>(null);
    const [archiveTarget, setArchiveTarget] = useState<AdminPatientRow | null>(null);
    const debounced = useDebouncedValue(search, 350);

    const apply = (partial: Partial<Record<string, string | number>> = {}, page = 1) => {
        const params: Record<string, string> = {
            q: filters.q,
            scope: filters.scope === 'active' ? '' : filters.scope,
            ...Object.fromEntries(Object.entries(partial).map(([key, value]) => [key, String(value)])),
        };
        if (page > 1) params.page = String(page);
        Object.keys(params).forEach((key) => {
            if (params[key] === '' || params[key] === '0' || (key === 'scope' && params[key] === 'active')) delete params[key];
        });
        router.get('/admin/patients', params, { preserveState: true, preserveScroll: true, replace: true });
    };

    useEffect(() => {
        if (debounced !== filters.q) apply({ q: debounced });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [debounced]);

    const openPortal = (patient: AdminPatientRow) => {
        router.get('/admin/patients', { tab: 'portal', portal: patient.id }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const columns: Array<DataColumn<AdminPatientRow>> = [
        {
            key: 'patient',
            label: 'Patient',
            render: (patient) => (
                <div>
                    <strong>{patient.name}</strong>
                    {patient.age ? <span className="caption"> · {patient.age}</span> : null}
                    {patient.gender ? <span className="caption">{patient.gender.charAt(0)}</span> : null}
                    {patient.archivedAt ? <div className="caption">archived {formatDate(patient.archivedAt, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })}</div> : null}
                </div>
            ),
        },
        { key: 'id', label: 'ID', render: (patient) => <span className="mono">{patient.code}</span> },
        { key: 'phone', label: 'Phone', render: (patient) => <span className="mono tabular">{patient.phone ?? '—'}</span> },
        {
            key: 'account',
            label: 'Linked account',
            render: (patient) => patient.accountStatus ? (
                <div className="stack-sm" style={{ gap: 4 }}>
                    <StatusBadge family="account" status={patient.accountStatus} label={patient.accountStatusLabel ?? undefined} />
                    <span className="caption">{patient.accountEmail}</span>
                </div>
            ) : (
                <span className="badge badge-neutral">{patient.isWalkIn ? `no account · ${patient.walkInCode ?? 'walk-in'}` : 'no account'}</span>
            ),
        },
        {
            key: 'workflow',
            label: 'Workflow',
            render: (patient) => (
                <div className="stack-sm" style={{ gap: 4 }}>
                    <span className={cn('badge', patient.isWalkIn ? 'badge-success' : 'badge-primary')}>
                        {patient.isWalkIn ? 'walk-in' : 'registered'}
                    </span>
                    {patient.condition ? <span className="caption">{patient.condition}</span> : null}
                </div>
            ),
        },
    ];

    return (
        <RoleLayout
            role="admin"
            title="Patients"
            subtitle={`${meta.total} patient record${meta.total === 1 ? '' : 's'}`}
            activeHref="/admin/patients"
            topbarActions={
                <button type="button" className="btn btn-primary" onClick={() => { setEditing(null); setFormOpen(true); }}>
                    + Register patient (staff mode)
                </button>
            }
        >
            <Head title="Patients & portal — MediPath" />

            <DataView
                name="Data view · Patient records / portal-as-patient"
                state={state}
                onStateChange={setState}
                showSwitch
                onRetry={() => router.reload()}
                lastKnownAt={lastKnownAt}
                emptyTitle="No patients yet"
                emptyBody="Walk-in registrations and staff-mode registrations create the first charts."
                errorMessage="Patient directory failed to load. No patient record was modified; retry is safe."
            >
                <div className="tabs" role="tablist" aria-label="Patient views">
                    <button
                        type="button"
                        role="tab"
                        aria-selected={tab === 'directory'}
                        onClick={() => router.get('/admin/patients', {}, { preserveState: true, preserveScroll: true, replace: true })}
                    >
                        Directory (CRUD) <span className="cnt tabular">{meta.total}</span>
                    </button>
                    <button
                        type="button"
                        role="tab"
                        aria-selected={tab === 'portal'}
                        onClick={() => router.get('/admin/patients', { tab: 'portal', ...(portalId ? { portal: portalId } : {}) }, { preserveState: true, preserveScroll: true, replace: true })}
                    >
                        Patient portal view (as-patient)
                    </button>
                </div>

                {tab === 'directory' ? (
                    <div className="mt-m">
                        <form className="filter-bar" aria-label="Patient filters" onSubmit={(event) => { event.preventDefault(); apply({ q: search }); }}>
                            <div className="field" style={{ margin: 0 }}>
                                <label className="label" htmlFor="patient-q">Search</label>
                                <input id="patient-q" className="input" type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="name, phone, patient ID" />
                            </div>
                            <div className="field" style={{ margin: 0 }}>
                                <label className="label" htmlFor="patient-scope">Scope</label>
                                <select id="patient-scope" className="select" value={filters.scope} onChange={(event) => apply({ scope: event.target.value === 'active' ? '' : event.target.value })}>
                                    <option value="active">Active records</option>
                                    <option value="archived">Archived only</option>
                                    <option value="all">All records</option>
                                </select>
                            </div>
                            <div className="f-actions f-full">
                                <span className="caption">{meta.total} match{meta.total === 1 ? '' : 'es'} · archive is a soft delete</span>
                                <button type="button" className="btn btn-ghost mla" onClick={() => { setSearch(''); router.get('/admin/patients', {}, { preserveState: true, preserveScroll: true, replace: true }); }}>
                                    Clear filters
                                </button>
                            </div>
                        </form>

                        <DataTable
                            caption="Patient records matching the current filters"
                            className="tbl-cards-md"
                            columns={columns}
                            rows={patients}
                            getRowKey={(patient) => patient.id}
                            state="success"
                            page={meta}
                            onPageChange={(page) => apply({}, page)}
                            actions={(patient) => (
                                <div className="mp-row" style={{ flexWrap: 'wrap' }}>
                                    <button type="button" className="btn btn-ghost btn-sm" onClick={() => openPortal(patient)}>
                                        Portal view
                                    </button>
                                    <button type="button" className="btn btn-ghost btn-sm" onClick={() => { setEditing(patient); setFormOpen(true); }}>
                                        Edit
                                    </button>
                                    {patient.archivedAt ? (
                                        <button type="button" className="btn btn-ghost btn-sm" onClick={() => router.post(`/admin/patients/${patient.id}/restore`, {}, { preserveScroll: true })}>
                                            Restore
                                        </button>
                                    ) : (
                                        <button type="button" className="btn btn-ghost btn-sm" style={{ color: 'var(--mp-danger-text)' }} onClick={() => setArchiveTarget(patient)}>
                                            Archive…
                                        </button>
                                    )}
                                </div>
                            )}
                        />
                    </div>
                ) : portal ? (
                    <div className="mt-m">
                        <PatientPortalPreview portal={portal} />
                        <div className="mp-row mt-m">
                            <a className="btn btn-outline" href={`/admin/patients?tab=portal&portal=${portal.patient.id}`}>Refresh preview</a>
                            <button type="button" className="btn btn-quiet mla" onClick={() => router.get('/admin/patients', {}, { preserveState: true, preserveScroll: true, replace: true })}>
                                Back to directory
                            </button>
                        </div>
                    </div>
                ) : (
                    <div className="empty mt-m">
                        <span className="glyph" aria-hidden="true">🧑</span>
                        <p className="empty-title">Choose a patient to preview</p>
                        <p className="empty-body">Open any row's “Portal view” from the Directory tab. The preview is read-only and every open is audited.</p>
                        <button type="button" className="btn btn-primary" onClick={() => router.get('/admin/patients', {}, { preserveState: true, preserveScroll: true, replace: true })}>
                            Go to Directory
                        </button>
                    </div>
                )}
            </DataView>

            <PatientFormModal open={formOpen} patient={editing} genders={genders} onClose={() => setFormOpen(false)} />
            <ArchivePatientModal patient={archiveTarget} onClose={() => setArchiveTarget(null)} />
        </RoleLayout>
    );
}

function PatientFormModal({
    open,
    patient,
    genders,
    onClose,
}: {
    open: boolean;
    patient: AdminPatientRow | null;
    genders: string[];
    onClose: () => void;
}) {
    const form = useForm({
        name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
        age: '',
        gender: '',
        blood_group: '',
        condition: '',
        address: '',
    });

    useEffect(() => {
        if (!open) return;
        if (patient) {
            form.setData({
                name: patient.name,
                email: patient.accountEmail ?? '',
                phone: patient.phone ?? '',
                password: '',
                password_confirmation: '',
                age: patient.age ? String(patient.age) : '',
                gender: patient.gender ?? '',
                blood_group: patient.bloodGroup ?? '',
                condition: patient.condition ?? '',
                address: patient.address ?? '',
            });
        } else {
            form.reset();
        }
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, patient]);

    const close = () => {
        form.clearErrors();
        onClose();
    };

    return (
        <AdminModal
            open={open}
            title={patient ? `Edit ${patient.name}` : 'Register patient (staff mode)'}
            subtitle={patient ? 'Updates the chart and linked account contact fields.' : 'Creates a patient account and chart with a temporary password.'}
            icon="🧑"
            wide
            onClose={close}
            footer={
                <>
                    <button type="button" className="btn btn-quiet" onClick={close}>Cancel</button>
                    <button type="submit" form="patient-form" className="btn btn-primary" disabled={form.processing}>
                        {form.processing ? 'Saving…' : patient ? 'Save patient' : 'Register patient'}
                    </button>
                </>
            }
        >
            <form
                id="patient-form"
                className="stack-sm"
                onSubmit={(event) => {
                    event.preventDefault();
                    const options = { preserveScroll: true, onSuccess: close };
                    if (patient) form.put(`/admin/patients/${patient.id}`, options);
                    else form.post('/admin/patients', options);
                }}
            >
                <div className="grid">
                    <div className="c-12 sm-c-6 field">
                        <label className="label req" htmlFor="pf-name">Full name</label>
                        <input id="pf-name" className="input" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                        {form.errors.name ? <span className="field-error" role="alert">{form.errors.name}</span> : null}
                    </div>
                    <div className="c-12 sm-c-6 field">
                        <label className="label req" htmlFor="pf-email">Email</label>
                        <input id="pf-email" className="input" type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} />
                        {form.errors.email ? <span className="field-error" role="alert">{form.errors.email}</span> : null}
                    </div>
                    <div className="c-12 sm-c-6 field">
                        <label className="label" htmlFor="pf-phone">Phone</label>
                        <input id="pf-phone" className="input mono" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} />
                    </div>
                    <div className="c-6 field">
                        <label className="label" htmlFor="pf-age">Age</label>
                        <input id="pf-age" className="input" type="number" min={0} max={120} value={form.data.age} onChange={(event) => form.setData('age', event.target.value)} />
                    </div>
                    <div className="c-6 field">
                        <label className="label" htmlFor="pf-gender">Gender</label>
                        <select id="pf-gender" className="select" value={form.data.gender} onChange={(event) => form.setData('gender', event.target.value)}>
                            <option value="">Not recorded</option>
                            {genders.map((gender) => <option key={gender} value={gender}>{gender}</option>)}
                        </select>
                    </div>
                    <div className="c-6 field">
                        <label className="label" htmlFor="pf-blood">Blood group</label>
                        <select id="pf-blood" className="select" value={form.data.blood_group} onChange={(event) => form.setData('blood_group', event.target.value)}>
                            <option value="">Not recorded</option>
                            {BLOOD_GROUPS.map((group) => <option key={group} value={group}>{group}</option>)}
                        </select>
                    </div>
                    <div className="c-6 field">
                        <label className="label" htmlFor="pf-condition">Primary condition</label>
                        <input id="pf-condition" className="input" value={form.data.condition} onChange={(event) => form.setData('condition', event.target.value)} />
                    </div>
                    <div className="c-12 field">
                        <label className="label" htmlFor="pf-address">Address</label>
                        <input id="pf-address" className="input" value={form.data.address} onChange={(event) => form.setData('address', event.target.value)} />
                    </div>
                    {!patient ? (
                        <>
                            <div className="c-12 sm-c-6 field">
                                <label className="label req" htmlFor="pf-password">Temporary password</label>
                                <input id="pf-password" className="input" type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} />
                                {form.errors.password ? <span className="field-error" role="alert">{form.errors.password}</span> : null}
                            </div>
                            <div className="c-12 sm-c-6 field">
                                <label className="label req" htmlFor="pf-password2">Confirm password</label>
                                <input id="pf-password2" className="input" type="password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} />
                            </div>
                        </>
                    ) : null}
                </div>
            </form>
        </AdminModal>
    );
}

function ArchivePatientModal({ patient, onClose }: { patient: AdminPatientRow | null; onClose: () => void }) {
    const [typed, setTyped] = useState('');
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (patient) {
            setTyped('');
            setProcessing(false);
        }
    }, [patient]);

    const matches = Boolean(patient) && typed === patient?.name;

    return (
        <AdminModal
            open={Boolean(patient)}
            title={`Archive ${patient?.name ?? 'patient'}?`}
            subtitle="Soft delete only — clinical and billing records stay readable forever."
            icon="🗄️"
            danger
            onClose={onClose}
            footer={
                <>
                    <button type="button" className="btn btn-quiet" onClick={onClose}>Cancel</button>
                    <button
                        type="button"
                        className="btn btn-danger"
                        disabled={!matches || processing}
                        onClick={() => {
                            if (!patient || !matches) return;
                            setProcessing(true);
                            router.post(`/admin/patients/${patient.id}/archive`, { confirm_name: typed }, {
                                preserveScroll: true,
                                onSuccess: onClose,
                                onFinish: () => setProcessing(false),
                            });
                        }}
                    >
                        {processing ? 'Archiving…' : 'Archive patient'}
                    </button>
                </>
            }
        >
            <div className="dep-counts" role="group" aria-label="Linked records">
                {patient ? Object.entries(patient.dependencies).map(([key, count]) => (
                    <span className="dep" key={key}>🔗 {count} {key}</span>
                )) : null}
            </div>
            <p style={{ fontSize: 14, marginTop: 8 }}>
                The patient disappears from active lists and cannot sign in. Existing prescriptions, invoices and
                appointments remain intact.
            </p>
            <div className="field">
                <label className="label req" htmlFor="archive-patient-name">Type the patient's name to confirm</label>
                <input
                    id="archive-patient-name"
                    className={cn('input mono', typed !== '' && !matches && 'is-invalid')}
                    value={typed}
                    onChange={(event) => setTyped(event.target.value)}
                    autoComplete="off"
                    placeholder={patient?.name ?? ''}
                />
            </div>
        </AdminModal>
    );
}