/**
 * Patient · My appointments — prototypes/patient/03-appointments.html.
 * Status tabs are server-filtered; contextual actions match the status.
 */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { DataState, PageMeta, SharedProps } from '@/types';
import type { PatientAppointmentRow } from '@/types/patient';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { DataTable, type DataColumn } from '@/Components/Data/DataTable';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { AdminModal } from '@/Components/Admin/AdminModal';
import { formatDate } from '@/lib/format';
import { useDebouncedValue } from '@/hooks/useUi';

interface AppointmentsProps {
    appointments: PatientAppointmentRow[];
    meta: PageMeta;
    tab: 'all' | 'pending' | 'accepted' | 'completed' | 'cancelled';
    counts: Record<string, number>;
    filters: { q: string };
    dataState: DataState;
    lastKnownAt: string;
}

const TABS: Array<{ key: AppointmentsProps['tab']; label: string }> = [
    { key: 'all', label: 'All' },
    { key: 'pending', label: 'Pending' },
    { key: 'accepted', label: 'Accepted' },
    { key: 'completed', label: 'Completed' },
    { key: 'cancelled', label: 'Cancelled' },
];

export default function PatientAppointments({ appointments, meta, tab, counts, filters, dataState, lastKnownAt }: AppointmentsProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(dataState);
    const [search, setSearch] = useState(filters.q);
    const [cancelTarget, setCancelTarget] = useState<PatientAppointmentRow | null>(null);
    const debounced = useDebouncedValue(search, 350);

    const apply = (nextTab = tab, page = 1, q = filters.q) => {
        const params: Record<string, string> = { tab: nextTab };
        if (q) params.q = q;
        if (page > 1) params.page = String(page);
        router.get('/patient/appointments', params, { preserveState: true, preserveScroll: true, replace: true });
    };

    useEffect(() => { if (debounced !== filters.q) apply(tab, 1, debounced); /* eslint-disable-next-line */ }, [debounced]);

    const columns: Array<DataColumn<PatientAppointmentRow>> = [
        {
            key: 'when',
            label: 'Date',
            render: (row) => (
                <div className="tabular">
                    <strong>{formatDate(row.date, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })}</strong>
                    <div className="caption">· {row.time}</div>
                </div>
            ),
        },
        {
            key: 'doctor',
            label: 'Doctor',
            render: (row) => (
                <div>
                    <strong>{row.doctorName}</strong>
                    <div className="caption">{row.specialty}{row.room ? ` · ${row.room}` : ''}</div>
                </div>
            ),
        },
        { key: 'status', label: 'Status', render: (row) => <StatusBadge family="appointment" status={row.status} label={row.statusLabel} /> },
        { key: 'fee', label: 'Fee', align: 'right', render: (row) => <span className="tabular">{row.feeFormatted}</span> },
    ];

    return (
        <RoleLayout
            role="patient"
            title="My appointments"
            subtitle={`${meta.total} in this view`}
            activeHref="/patient/appointments"
            topbarActions={<a className="btn btn-primary" href="/patient/doctors">New booking</a>}
        >
            <Head title="My appointments — MediPath" />

            <DataView
                name="Data view · Appointment history"
                state={state}
                onStateChange={setState}
                showSwitch
                onRetry={() => router.reload()}
                lastKnownAt={lastKnownAt}
                emptyTitle="No appointments yet"
                emptyBody="When you book, your visits and outcomes stay here — searchable by doctor, status and month."
                emptyAction={<a className="btn btn-primary" href="/patient/doctors">Find a doctor</a>}
                errorMessage="Couldn't load your appointments. Filters are kept; nothing was changed."
            >
                <div className="tabs" role="tablist" aria-label="Appointment status">
                    {TABS.map((item) => (
                        <button key={item.key} type="button" role="tab" aria-selected={tab === item.key} onClick={() => apply(item.key)}>
                            {item.label} <span className="cnt tabular">{counts[item.key] ?? 0}</span>
                        </button>
                    ))}
                </div>

                <form className="filter-bar mt-m" aria-label="Appointment search" onSubmit={(event) => { event.preventDefault(); apply(tab, 1, search); }}>
                    <div className="field" style={{ margin: 0 }}>
                        <label className="label" htmlFor="patient-appt-q">Search</label>
                        <input id="patient-appt-q" className="input" type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="doctor, specialty, APT code" />
                    </div>
                    <div className="f-actions f-full">
                        <span className="caption">Server-side filters · {meta.total} result(s)</span>
                        <button type="button" className="btn btn-ghost mla" onClick={() => { setSearch(''); router.get('/patient/appointments', { tab }, { preserveState: true, preserveScroll: true, replace: true }); }}>Clear</button>
                    </div>
                </form>

                <div className="mt-m">
                    <DataTable
                        className="tbl-cards-md"
                        caption="Your appointment history"
                        columns={columns}
                        rows={appointments}
                        getRowKey={(row) => row.id}
                        page={meta}
                        onPageChange={(page) => apply(tab, page)}
                        actions={(row) => (
                            <div className="mp-row" style={{ flexWrap: 'wrap' }}>
                                {row.canReschedule && row.doctorCode ? <a className="btn btn-ghost btn-sm" href={`/patient/book/${row.doctorCode}?reschedule=${row.id}`}>Reschedule</a> : null}
                                {row.canCancel ? <button type="button" className="btn btn-ghost btn-sm" style={{ color: 'var(--mp-danger-text)' }} onClick={() => setCancelTarget(row)}>Cancel…</button> : null}
                                {row.status === 'completed' ? <a className="btn btn-ghost btn-sm" href="/patient/prescriptions">View prescription</a> : null}
                                {row.canReview ? <a className="btn btn-outline btn-sm" href={`/patient/reviews?appointment=${row.id}`}>Leave review</a> : null}
                                {row.reviewStatus ? <span className="caption">review {row.reviewStatus}</span> : null}
                                {row.status === 'cancelled' && row.cancellationReason ? <span className="caption">{row.cancellationReason}</span> : null}
                                {['cancelled', 'rejected'].includes(row.status) && row.doctorCode ? <a className="btn btn-ghost btn-sm" href={`/patient/book/${row.doctorCode}`}>Rebook</a> : null}
                            </div>
                        )}
                    />
                </div>
                <p className="caption mt-m">Actions follow the status enum; cancellation inside the cutoff requires the clinic desk.</p>
            </DataView>

            <CancelModal appointment={cancelTarget} onClose={() => setCancelTarget(null)} />
        </RoleLayout>
    );
}

function CancelModal({ appointment, onClose }: { appointment: PatientAppointmentRow | null; onClose: () => void }) {
    const form = useForm({ reason: 'Schedule conflict' });

    useEffect(() => { if (appointment) { form.reset(); form.clearErrors(); } /* eslint-disable-next-line */ }, [appointment]);

    return (
        <AdminModal
            open={Boolean(appointment)}
            title={`Cancel ${appointment?.code ?? 'appointment'}?`}
            subtitle={appointment ? `${appointment.doctorName} · ${appointment.date} ${appointment.time}` : undefined}
            icon="📅"
            danger
            onClose={onClose}
            footer={
                <>
                    <button type="button" className="btn btn-quiet" onClick={onClose}>Keep appointment</button>
                    <button type="submit" form="patient-cancel-form" className="btn btn-danger" disabled={form.processing}>
                        {form.processing ? 'Cancelling…' : 'Cancel appointment'}
                    </button>
                </>
            }
        >
            <form
                id="patient-cancel-form"
                onSubmit={(event) => {
                    event.preventDefault();
                    if (!appointment) return;
                    form.post(`/patient/appointments/${appointment.id}/cancel`, { preserveScroll: true, onSuccess: onClose });
                }}
            >
                <p style={{ fontSize: 14 }}>The slot returns to availability immediately. The reason is visible to the doctor and recorded in the audit log.</p>
                <div className="field">
                    <label className="label req" htmlFor="patient-cancel-reason">Reason (required)</label>
                    <select id="patient-cancel-reason" className="select" value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)}>
                        <option>Schedule conflict</option>
                        <option>Feeling better</option>
                        <option>Found another doctor</option>
                        <option>Other</option>
                    </select>
                    {(form.errors as Record<string, string | undefined>).reason ? <span className="field-error">{(form.errors as Record<string, string | undefined>).reason}</span> : null}
                    {(form.errors as Record<string, string | undefined>).appointment ? <span className="field-error">{(form.errors as Record<string, string | undefined>).appointment}</span> : null}
                </div>
            </form>
        </AdminModal>
    );
}