/**
 * Admin · Doctors — prototypes/admin/04-doctors.html.
 * Server-side search/filter/pagination, create/edit, verification drill-down,
 * and typed-confirm soft archive with dependency counts.
 */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { DataState, PageMeta, SharedProps } from '@/types';
import type { AdminDoctorRow } from '@/types/admin';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { DataTable, type DataColumn } from '@/Components/Data/DataTable';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { Money } from '@/Components/ui/Money';
import { AdminModal } from '@/Components/Admin/AdminModal';
import { cn, formatDate } from '@/lib/format';
import { useDebouncedValue } from '@/hooks/useUi';

interface DoctorsProps {
    doctors: AdminDoctorRow[];
    meta: PageMeta;
    filters: { q: string; specialty: number; verification: string; scope: string };
    specialties: Array<{ id: number; name: string }>;
    statuses: Array<{ value: string; label: string }>;
    dataState: DataState;
    lastKnownAt: string;
}

export default function AdminDoctors({
    doctors,
    meta,
    filters,
    specialties,
    statuses,
    dataState,
    lastKnownAt,
}: DoctorsProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(dataState);
    const [search, setSearch] = useState(filters.q);
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<AdminDoctorRow | null>(null);
    const [archiveTarget, setArchiveTarget] = useState<AdminDoctorRow | null>(null);
    const debounced = useDebouncedValue(search, 350);

    const apply = (partial: Partial<Record<string, string | number>> = {}, page = 1) => {
        const params: Record<string, string> = {
            q: filters.q,
            specialty: filters.specialty ? String(filters.specialty) : '',
            verification: filters.verification,
            scope: filters.scope === 'active' ? '' : filters.scope,
            ...Object.fromEntries(Object.entries(partial).map(([key, value]) => [key, String(value)])),
        };
        if (page > 1) params.page = String(page);
        Object.keys(params).forEach((key) => {
            if (params[key] === '' || params[key] === '0' || (key === 'scope' && params[key] === 'active')) delete params[key];
        });
        router.get('/admin/doctors', params, { preserveState: true, preserveScroll: true, replace: true });
    };

    useEffect(() => {
        if (debounced !== filters.q) apply({ q: debounced });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [debounced]);

    const columns: Array<DataColumn<AdminDoctorRow>> = [
        {
            key: 'doctor',
            label: 'Doctor',
            render: (doctor) => (
                <div>
                    <strong>{doctor.name}</strong>
                    {doctor.archivedAt ? (
                        <span className="caption"> archived {formatDate(doctor.archivedAt, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })}</span>
                    ) : null}
                    <div className="caption mono">{doctor.code} · {doctor.email}</div>
                </div>
            ),
        },
        { key: 'specialty', label: 'Specialty', render: (doctor) => doctor.specialty ?? '—' },
        { key: 'license', label: 'License', render: (doctor) => <span className="mono">{doctor.licenseNumber}</span> },
        {
            key: 'fee',
            label: 'Fee',
            align: 'right',
            render: (doctor) => doctor.archivedAt ? <span className="muted">—</span> : <Money formatted={doctor.feeFormatted} size="sm" />,
        },
        {
            key: 'verification',
            label: 'Verification',
            render: (doctor) => (
                <StatusBadge family="verification" status={doctor.verificationStatus} label={doctor.verificationLabel ?? undefined} />
            ),
        },
        { key: 'bookings', label: 'Bookings', align: 'right', render: (doctor) => <span className="num tabular">{doctor.bookings}</span> },
    ];

    return (
        <RoleLayout
            role="admin"
            title="Doctors"
            subtitle={`${meta.total} record${meta.total === 1 ? '' : 's'}`}
            activeHref="/admin/doctors"
            topbarActions={
                <button type="button" className="btn btn-primary" onClick={() => { setEditing(null); setFormOpen(true); }}>
                    + Add doctor
                </button>
            }
        >
            <Head title="Doctors — MediPath" />

            <form className="filter-bar" aria-label="Doctor filters" onSubmit={(event) => { event.preventDefault(); apply({ q: search }); }}>
                <div className="field" style={{ margin: 0 }}>
                    <label className="label" htmlFor="doc-q">Search</label>
                    <input id="doc-q" className="input" type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="name, email, license" />
                </div>
                <div className="field" style={{ margin: 0 }}>
                    <label className="label" htmlFor="doc-specialty">Specialty</label>
                    <select id="doc-specialty" className="select" value={filters.specialty || ''} onChange={(event) => apply({ specialty: event.target.value })}>
                        <option value="">All specialties</option>
                        {specialties.map((specialty) => <option key={specialty.id} value={specialty.id}>{specialty.name}</option>)}
                    </select>
                </div>
                <div className="field" style={{ margin: 0 }}>
                    <label className="label" htmlFor="doc-verification">Verification</label>
                    <select id="doc-verification" className="select" value={filters.verification} onChange={(event) => apply({ verification: event.target.value })}>
                        <option value="">Any state</option>
                        {statuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}
                    </select>
                </div>
                <div className="field" style={{ margin: 0 }}>
                    <label className="label" htmlFor="doc-scope">Scope</label>
                    <select id="doc-scope" className="select" value={filters.scope} onChange={(event) => apply({ scope: event.target.value === 'active' ? '' : event.target.value })}>
                        <option value="active">Active records</option>
                        <option value="archived">Archived only</option>
                        <option value="all">All records</option>
                    </select>
                </div>
                <div className="f-actions f-full">
                    <span className="caption">{meta.total} match{meta.total === 1 ? '' : 'es'} · soft-archived records preserved</span>
                    <button type="button" className="btn btn-ghost mla" onClick={() => { setSearch(''); router.get('/admin/doctors', {}, { preserveState: true, preserveScroll: true, replace: true }); }}>
                        Clear filters
                    </button>
                </div>
            </form>

            <DataView
                name="Data view · Doctor records"
                state={state}
                onStateChange={setState}
                showSwitch
                onRetry={() => router.reload()}
                lastKnownAt={lastKnownAt}
                emptyTitle="No doctor records"
                emptyBody="Adjust the filters, or add the first doctor profile."
                emptyAction={<button type="button" className="btn btn-primary" onClick={() => { setEditing(null); setFormOpen(true); }}>+ Add doctor</button>}
                errorMessage="Records failed to load. No record was modified; retry is safe."
            >
                <DataTable
                    caption="Doctor records matching the current filters"
                    className="tbl-cards-md"
                    columns={columns}
                    rows={doctors}
                    getRowKey={(doctor) => doctor.id}
                    state="success"
                    page={meta}
                    onPageChange={(page) => apply({}, page)}
                    actions={(doctor) => (
                        <div className="mp-row" style={{ flexWrap: 'wrap' }}>
                            <button type="button" className="btn btn-ghost btn-sm" onClick={() => { setEditing(doctor); setFormOpen(true); }}>
                                Edit
                            </button>
                            {doctor.archivedAt ? (
                                <button type="button" className="btn btn-ghost btn-sm" onClick={() => router.post(`/admin/doctors/${doctor.id}/restore`, {}, { preserveScroll: true })}>
                                    Restore
                                </button>
                            ) : (
                                <>
                                    {doctor.verificationStatus !== 'verified' ? (
                                        <a className="btn btn-outline btn-sm" href="/admin/approvals?tab=verification">Verify →</a>
                                    ) : null}
                                    <button type="button" className="btn btn-ghost btn-sm" style={{ color: 'var(--mp-danger-text)' }} onClick={() => setArchiveTarget(doctor)}>
                                        Archive…
                                    </button>
                                </>
                            )}
                        </div>
                    )}
                />
            </DataView>

            <p className="caption mt-m">
                Archiving is a <strong>soft</strong> delete: records link forever, while the directory and booking hide instantly. Hard-delete paths do not exist.
            </p>

            <DoctorFormModal open={formOpen} doctor={editing} specialties={specialties} onClose={() => setFormOpen(false)} />
            <ArchiveDoctorModal doctor={archiveTarget} onClose={() => setArchiveTarget(null)} />
        </RoleLayout>
    );
}

function DoctorFormModal({
    open,
    doctor,
    specialties,
    onClose,
}: {
    open: boolean;
    doctor: AdminDoctorRow | null;
    specialties: Array<{ id: number; name: string }>;
    onClose: () => void;
}) {
    const form = useForm({
        name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
        specialty_id: '',
        license_number: '',
        license_authority: '',
        experience_years: '0',
        fee: '0',
        bio: '',
        clinic_name: '',
        clinic_address: '',
        room: '',
        is_featured: false,
    });

    useEffect(() => {
        if (!open) return;
        if (doctor) {
            form.setData({
                name: doctor.name ?? '',
                email: doctor.email ?? '',
                phone: doctor.phone ?? '',
                password: '',
                password_confirmation: '',
                specialty_id: String(doctor.specialtyId),
                license_number: doctor.licenseNumber,
                license_authority: doctor.licenseAuthority,
                experience_years: String(doctor.experienceYears),
                fee: String(doctor.fee),
                bio: doctor.bio ?? '',
                clinic_name: doctor.clinicName ?? '',
                clinic_address: doctor.clinicAddress ?? '',
                room: doctor.room ?? '',
                is_featured: doctor.isFeatured,
            });
        } else {
            form.reset();
        }
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, doctor]);

    const close = () => {
        form.clearErrors();
        onClose();
    };

    const submit = () => {
        const options = { preserveScroll: true, onSuccess: close };
        if (doctor) form.put(`/admin/doctors/${doctor.id}`, options);
        else form.post('/admin/doctors', options);
    };

    return (
        <AdminModal
            open={open}
            title={doctor ? `Edit ${doctor.name}` : 'Add doctor'}
            subtitle={doctor ? 'Contact, license and public-profile fields.' : 'Creates a doctor account; license verification stays pending.'}
            icon="🩺"
            wide
            onClose={close}
            footer={
                <>
                    <button type="button" className="btn btn-quiet" onClick={close}>Cancel</button>
                    <button type="submit" form="doctor-form" className="btn btn-primary" disabled={form.processing}>
                        {form.processing ? 'Saving…' : doctor ? 'Save doctor' : 'Create doctor'}
                    </button>
                </>
            }
        >
            <form id="doctor-form" className="stack-sm" onSubmit={(event) => { event.preventDefault(); submit(); }}>
                <div className="grid">
                    <div className="c-12 sm-c-6 field">
                        <label className="label req" htmlFor="df-name">Full name</label>
                        <input id="df-name" className="input" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                        {form.errors.name ? <span className="field-error" role="alert">{form.errors.name}</span> : null}
                    </div>
                    <div className="c-12 sm-c-6 field">
                        <label className="label req" htmlFor="df-email">Email</label>
                        <input id="df-email" className="input" type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} />
                        {form.errors.email ? <span className="field-error" role="alert">{form.errors.email}</span> : null}
                    </div>
                    <div className="c-12 sm-c-6 field">
                        <label className="label" htmlFor="df-phone">Phone</label>
                        <input id="df-phone" className="input" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} />
                    </div>
                    <div className="c-12 sm-c-6 field">
                        <label className="label req" htmlFor="df-specialty">Specialty</label>
                        <select id="df-specialty" className="select" value={form.data.specialty_id} onChange={(event) => form.setData('specialty_id', event.target.value)}>
                            <option value="">Choose specialty</option>
                            {specialties.map((specialty) => <option key={specialty.id} value={specialty.id}>{specialty.name}</option>)}
                        </select>
                        {form.errors.specialty_id ? <span className="field-error" role="alert">{form.errors.specialty_id}</span> : null}
                    </div>
                    {!doctor ? (
                        <>
                            <div className="c-12 sm-c-6 field">
                                <label className="label req" htmlFor="df-password">Temporary password</label>
                                <input id="df-password" className="input" type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} />
                                {form.errors.password ? <span className="field-error" role="alert">{form.errors.password}</span> : null}
                            </div>
                            <div className="c-12 sm-c-6 field">
                                <label className="label req" htmlFor="df-password2">Confirm password</label>
                                <input id="df-password2" className="input" type="password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} />
                            </div>
                        </>
                    ) : null}
                    <div className="c-12 sm-c-6 field">
                        <label className="label req" htmlFor="df-license">License number</label>
                        <input id="df-license" className="input mono" value={form.data.license_number} onChange={(event) => form.setData('license_number', event.target.value)} />
                        {form.errors.license_number ? <span className="field-error" role="alert">{form.errors.license_number}</span> : null}
                    </div>
                    <div className="c-12 sm-c-6 field">
                        <label className="label req" htmlFor="df-authority">License authority</label>
                        <input id="df-authority" className="input" value={form.data.license_authority} onChange={(event) => form.setData('license_authority', event.target.value)} />
                        {form.errors.license_authority ? <span className="field-error" role="alert">{form.errors.license_authority}</span> : null}
                    </div>
                    <div className="c-6 field">
                        <label className="label" htmlFor="df-exp">Experience (years)</label>
                        <input id="df-exp" className="input" type="number" min={0} max={70} value={form.data.experience_years} onChange={(event) => form.setData('experience_years', event.target.value)} />
                    </div>
                    <div className="c-6 field">
                        <label className="label" htmlFor="df-fee">Consultation fee</label>
                        <input id="df-fee" className="input" type="number" min={0} step="0.01" value={form.data.fee} onChange={(event) => form.setData('fee', event.target.value)} />
                    </div>
                    <div className="c-12 sm-c-6 field">
                        <label className="label" htmlFor="df-clinic">Clinic name</label>
                        <input id="df-clinic" className="input" value={form.data.clinic_name} onChange={(event) => form.setData('clinic_name', event.target.value)} />
                    </div>
                    <div className="c-12 sm-c-6 field">
                        <label className="label" htmlFor="df-room">Room</label>
                        <input id="df-room" className="input" value={form.data.room} onChange={(event) => form.setData('room', event.target.value)} />
                    </div>
                    <div className="c-12 field">
                        <label className="label" htmlFor="df-address">Clinic address</label>
                        <input id="df-address" className="input" value={form.data.clinic_address} onChange={(event) => form.setData('clinic_address', event.target.value)} />
                    </div>
                    <div className="c-12 field">
                        <label className="label" htmlFor="df-bio">Bio</label>
                        <textarea id="df-bio" className="textarea" rows={3} value={form.data.bio} onChange={(event) => form.setData('bio', event.target.value)} />
                    </div>
                </div>
                <label className="check">
                    <input type="checkbox" checked={form.data.is_featured} onChange={(event) => form.setData('is_featured', event.target.checked)} />
                    <span>Feature on the public directory</span>
                </label>
            </form>
        </AdminModal>
    );
}

function ArchiveDoctorModal({ doctor, onClose }: { doctor: AdminDoctorRow | null; onClose: () => void }) {
    const [typed, setTyped] = useState('');
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (doctor) {
            setTyped('');
            setProcessing(false);
        }
    }, [doctor]);

    const matches = Boolean(doctor) && typed === doctor?.name;

    return (
        <AdminModal
            open={Boolean(doctor)}
            title={`Archive ${doctor?.name ?? 'doctor'}?`}
            subtitle="They disappear from the directory, booking and future prescriptions."
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
                            if (!doctor || !matches) return;
                            setProcessing(true);
                            router.post(`/admin/doctors/${doctor.id}/archive`, { confirm_name: typed }, {
                                preserveScroll: true,
                                onSuccess: onClose,
                                onFinish: () => setProcessing(false),
                            });
                        }}
                    >
                        {processing ? 'Archiving…' : 'Archive doctor'}
                    </button>
                </>
            }
        >
            <div className="dep-counts" role="group" aria-label="Linked records">
                {doctor ? Object.entries(doctor.dependencies).map(([key, count]) => (
                    <span className="dep" key={key}>🔗 {count} {key}</span>
                )) : null}
            </div>
            <p style={{ fontSize: 14, marginTop: 8 }}>
                Existing records remain readable to patients and pharmacy forever. This can only be reversed with
                “Restore”, which is also logged.
            </p>
            <div className="field">
                <label className="label req" htmlFor="archive-doctor-name">Type the doctor's name to confirm</label>
                <input
                    id="archive-doctor-name"
                    className={cn('input mono', typed !== '' && !matches && 'is-invalid')}
                    value={typed}
                    onChange={(event) => setTyped(event.target.value)}
                    autoComplete="off"
                    placeholder={doctor?.name ?? ''}
                />
            </div>
        </AdminModal>
    );
}