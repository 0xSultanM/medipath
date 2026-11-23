/**
 * Admin · Global appointments — prototypes/admin/06-appointments.html.
 * Oversight only: filters are server-side; cancellation is explicit, reasoned
 * and writes actor + activity + notifications for both parties.
 */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { DataState, PageMeta, SharedProps } from '@/types';
import type { AdminAppointmentRow } from '@/types/admin';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { DataTable, type DataColumn } from '@/Components/Data/DataTable';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { AdminModal } from '@/Components/Admin/AdminModal';
import { formatDate } from '@/lib/format';
import { useDebouncedValue } from '@/hooks/useUi';

interface AppointmentsProps {
    appointments: AdminAppointmentRow[];
    meta: PageMeta;
    filters: { q: string; doctor: number; status: string; date: string };
    doctors: Array<{ id: number; name: string | null }>;
    statuses: Array<{ value: string; label: string }>;
    dataState: DataState;
    lastKnownAt: string;
}

export default function AdminAppointments({ appointments, meta, filters, doctors, statuses, dataState, lastKnownAt }: AppointmentsProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(dataState);
    const [search, setSearch] = useState(filters.q);
    const [cancelTarget, setCancelTarget] = useState<AdminAppointmentRow | null>(null);
    const debounced = useDebouncedValue(search, 350);

    const apply = (partial: Partial<Record<string, string | number>> = {}, page = 1) => {
        const params: Record<string, string> = {
            q: filters.q,
            doctor: filters.doctor ? String(filters.doctor) : '',
            status: filters.status,
            date: filters.date,
            ...Object.fromEntries(Object.entries(partial).map(([key, value]) => [key, String(value)])),
        };
        if (page > 1) params.page = String(page);
        Object.keys(params).forEach((key) => { if (params[key] === '' || params[key] === '0') delete params[key]; });
        router.get('/admin/appointments', params, { preserveState: true, preserveScroll: true, replace: true });
    };

    useEffect(() => { if (debounced !== filters.q) apply({ q: debounced }); /* eslint-disable-next-line */ }, [debounced]);

    const columns: Array<DataColumn<AdminAppointmentRow>> = [
        { key: 'code', label: 'APT', render: (row) => <span className="mono">{row.code}</span> },
        {
            key: 'when',
            label: 'Date / slot',
            render: (row) => (
                <span className="tabular">
                    {formatDate(row.date, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })}
                    {' · '}{row.startTime}
                </span>
            ),
        },
        {
            key: 'patient',
            label: 'Patient',
            render: (row) => (
                <div>
                    <strong>{row.patientName}</strong>
                    <div className="caption mono">{row.isWalkIn ? 'walk-in · ' : ''}{row.patientCode}</div>
                </div>
            ),
        },
        { key: 'doctor', label: 'Doctor', render: (row) => row.doctorName ?? '—' },
        {
            key: 'status',
            label: 'Status',
            render: (row) => (
                <div className="stack-sm" style={{ gap: 4 }}>
                    <StatusBadge family="appointment" status={row.status} label={row.statusLabel} />
                    {row.cancellationReason ? <span className="caption">{row.cancellationReason}</span> : null}
                </div>
            ),
        },
    ];

    return (
        <RoleLayout
            role="admin"
            title="Global appointments"
            subtitle="oversight only — acting on behalf always records actor + reason"
            activeHref="/admin/appointments"
        >
            <Head title="Global appointments — MediPath" />

            <form className="filter-bar" aria-label="Appointment filters" onSubmit={(event) => { event.preventDefault(); apply({ q: search }); }}>
                <div className="field" style={{ margin: 0 }}>
                    <label className="label" htmlFor="apt-q">Search</label>
                    <input id="apt-q" className="input" type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="patient ID, name, doctor, APT code" />
                </div>
                <div className="field" style={{ margin: 0 }}>
                    <label className="label" htmlFor="apt-doctor">Doctor</label>
                    <select id="apt-doctor" className="select" value={filters.doctor || ''} onChange={(event) => apply({ doctor: event.target.value })}>
                        <option value="">Any doctor</option>
                        {doctors.map((doctor) => <option key={doctor.id} value={doctor.id}>{doctor.name}</option>)}
                    </select>
                </div>
                <div className="field" style={{ margin: 0 }}>
                    <label className="label" htmlFor="apt-status">Status</label>
                    <select id="apt-status" className="select" value={filters.status} onChange={(event) => apply({ status: event.target.value })}>
                        <option value="">Any status</option>
                        {statuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}
                    </select>
                </div>
                <div className="field" style={{ margin: 0 }}>
                    <label className="label" htmlFor="apt-date">Date</label>
                    <input id="apt-date" className="input" type="date" value={filters.date} onChange={(event) => apply({ date: event.target.value })} />
                </div>
                <div className="f-actions f-full">
                    <span className="caption">{meta.total} appointment{meta.total === 1 ? '' : 's'} · newest first</span>
                    <button type="button" className="btn btn-ghost mla" onClick={() => { setSearch(''); router.get('/admin/appointments', {}, { preserveState: true, preserveScroll: true, replace: true }); }}>
                        Clear filters
                    </button>
                </div>
            </form>

            <DataView
                name="Data view · Appointments"
                state={state}
                onStateChange={setState}
                showSwitch
                onRetry={() => router.reload()}
                lastKnownAt={lastKnownAt}
                emptyTitle="No appointments in this filter set"
                emptyBody="Clear one or more filters; the oversight query is bounded and indexed."
                errorMessage="Oversight query failed. Nothing was modified; retry is safe."
            >
                <DataTable
                    className="tbl-cards-md"
                    caption="All appointments matching the current filters"
                    columns={columns}
                    rows={appointments}
                    getRowKey={(row) => row.id}
                    page={meta}
                    onPageChange={(page) => apply({}, page)}
                    actions={(row) => (
                        <div className="mp-row" style={{ flexWrap: 'wrap' }}>
                            <a className="btn btn-ghost btn-sm" href="/admin/records?tab=prescriptions">Records</a>
                            <a className="btn btn-ghost btn-sm" href={`/admin/activity?q=${encodeURIComponent(row.code)}`}>Audit trail</a>
                            {row.canCancel ? (
                                <button type="button" className="btn btn-ghost btn-sm" style={{ color: 'var(--mp-danger-text)' }} onClick={() => setCancelTarget(row)}>
                                    Cancel on behalf…
                                </button>
                            ) : null}
                        </div>
                    )}
                />
            </DataView>

            <CancelAppointmentModal appointment={cancelTarget} onClose={() => setCancelTarget(null)} />
        </RoleLayout>
    );
}

function CancelAppointmentModal({ appointment, onClose }: { appointment: AdminAppointmentRow | null; onClose: () => void }) {
    const form = useForm({ reason: '' });

    useEffect(() => { if (appointment) { form.reset(); form.clearErrors(); } /* eslint-disable-next-line */ }, [appointment]);

    return (
        <AdminModal
            open={Boolean(appointment)}
            title={`Cancel ${appointment?.code ?? 'appointment'} on behalf?`}
            subtitle="Actor + reason are recorded; both parties are notified and the slot is released."
            icon="🚫"
            danger
            onClose={onClose}
            footer={
                <>
                    <button type="button" className="btn btn-quiet" onClick={onClose}>Back</button>
                    <button type="submit" form="cancel-appointment-form" className="btn btn-danger" disabled={form.processing}>
                        {form.processing ? 'Cancelling…' : 'Cancel appointment'}
                    </button>
                </>
            }
        >
            <form
                id="cancel-appointment-form"
                onSubmit={(event) => {
                    event.preventDefault();
                    if (!appointment) return;
                    form.post(`/admin/appointments/${appointment.id}/cancel`, { preserveScroll: true, onSuccess: onClose });
                }}
            >
                <div className="alert alert-warning">
                    <span className="a-ico" aria-hidden="true">⚖</span>
                    <span>This is a clinical action: the cancellation reason is visible to the patient and doctor and lands in the immutable activity log.</span>
                </div>
                <div className="field">
                    <label className="label req" htmlFor="cancel-reason">Reason (required)</label>
                    <textarea id="cancel-reason" className="textarea" rows={3} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} placeholder="clinic closure, doctor emergency…" />
                    {form.errors.reason ? <span className="field-error" role="alert">{form.errors.reason}</span> : null}
                </div>
            </form>
        </AdminModal>
    );
}