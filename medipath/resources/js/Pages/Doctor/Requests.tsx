/**
 * Doctor · Appointment requests — prototypes/doctor/03-requests.html.
 */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { DataState, PageMeta, SharedProps } from '@/types';
import type { DoctorRequestRow } from '@/types/doctor';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { DataTable, type DataColumn } from '@/Components/Data/DataTable';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { AdminModal } from '@/Components/Admin/AdminModal';
import { formatDate } from '@/lib/format';

interface RequestsProps {
    tab: 'pending' | 'today' | 'upcoming' | 'completed' | 'cancelled';
    requests: DoctorRequestRow[];
    meta: PageMeta;
    counts: Record<string, number>;
    dataState: DataState;
    lastKnownAt: string;
}

const TABS: Array<{ key: RequestsProps['tab']; label: string }> = [
    { key: 'pending', label: 'Pending' }, { key: 'today', label: 'Today' }, { key: 'upcoming', label: 'Upcoming' },
    { key: 'completed', label: 'Completed' }, { key: 'cancelled', label: 'Cancelled' },
];

export default function DoctorRequests({ tab, requests, meta, counts, dataState, lastKnownAt }: RequestsProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(dataState);
    const [rejectTarget, setRejectTarget] = useState<DoctorRequestRow | null>(null);
    const [cancelTarget, setCancelTarget] = useState<DoctorRequestRow | null>(null);

    const apply = (nextTab = tab, page = 1) => router.get('/doctor/requests', { ...(nextTab !== 'pending' ? { tab: nextTab } : {}), ...(page > 1 ? { page } : {}) }, { preserveState: true, preserveScroll: true, replace: true });

    const columns: Array<DataColumn<DoctorRequestRow>> = [
        { key: 'when', label: 'Slot', render: (row) => <span className="tabular"><strong>{formatDate(row.date, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })}</strong><div className="caption">{row.time}</div></span> },
        { key: 'patient', label: 'Patient', render: (row) => <div><strong>{row.patient}</strong><div className="caption mono">{row.isWalkIn ? 'walk-in · ' : ''}{row.patientCode}</div></div> },
        { key: 'reason', label: 'Reason', render: (row) => <div>{row.reason}{row.requestedAt ? <div className="caption">requested {row.requestedAt}</div> : null}</div> },
        { key: 'status', label: 'Status', render: (row) => <div className="stack-sm" style={{ gap: 4 }}><StatusBadge family="appointment" status={row.status} label={row.statusLabel} />{row.rejectionReason ? <span className="caption">{row.rejectionReason}</span> : null}{row.cancellationReason ? <span className="caption">{row.cancellationReason}</span> : null}</div> },
    ];

    return (
        <RoleLayout role="doctor" title="Appointment requests" subtitle={`${meta.total} in this view`} activeHref="/doctor/requests">
            <Head title="Appointment requests — MediPath" />
            <DataView name="Data view · Requests queue" state={state} onStateChange={setState} showSwitch onRetry={() => router.reload()} lastKnownAt={lastKnownAt}
                emptyTitle={tab === 'pending' ? 'Queue is clear' : 'No appointments in this view'} emptyBody="New booking requests land here and as a notification."
                errorMessage="Queue couldn't load. No decisions were lost; the database queue is the source of truth.">
                <div className="tabs" role="tablist" aria-label="Request status">
                    {TABS.map((item) => <button key={item.key} type="button" role="tab" aria-selected={tab === item.key} onClick={() => apply(item.key)}>{item.label} <span className="cnt tabular">{counts[item.key] ?? 0}</span></button>)}
                </div>
                <div className="mt-m">
                    <DataTable className="tbl-cards-md" caption="Appointment requests" columns={columns} rows={requests} getRowKey={(row) => row.id} page={meta} onPageChange={(page) => apply(tab, page)}
                        actions={(row) => (
                            <div className="mp-row" style={{ flexWrap: 'wrap' }}>
                                <a className="btn btn-ghost btn-sm" href={`/doctor/patients/${row.patientId}`}>Chart</a>
                                {row.status === 'pending' ? <>
                                    <button type="button" className="btn btn-primary btn-sm" onClick={() => router.post(`/doctor/requests/${row.id}/accept`, {}, { preserveScroll: true })}>Accept</button>
                                    <button type="button" className="btn btn-danger-outline btn-sm" onClick={() => setRejectTarget(row)}>Reject…</button>
                                </> : null}
                                {['accepted', 'pending'].includes(row.status) ? <>
                                    <button type="button" className="btn btn-outline btn-sm" onClick={() => router.post(`/doctor/requests/${row.id}/complete`, {}, { preserveScroll: true })}>Mark completed</button>
                                    <button type="button" className="btn btn-ghost btn-sm" style={{ color: 'var(--mp-danger-text)' }} onClick={() => setCancelTarget(row)}>Cancel w/ notice…</button>
                                </> : null}
                                {row.status === 'completed' ? <a className="btn btn-ghost btn-sm" href={`/doctor/prescriptions/new?patient=${row.patientId}`}>Rx</a> : null}
                            </div>
                        )} />
                </div>
            </DataView>
            <ReasonModal open={Boolean(rejectTarget)} title={`Reject ${rejectTarget?.code ?? ''}`} endpoint={rejectTarget ? `/doctor/requests/${rejectTarget.id}/reject` : ''} action="Reject request" tone="danger" onClose={() => setRejectTarget(null)} />
            <ReasonModal open={Boolean(cancelTarget)} title={`Cancel ${cancelTarget?.code ?? ''}`} endpoint={cancelTarget ? `/doctor/requests/${cancelTarget.id}/cancel` : ''} action="Cancel appointment" tone="danger" onClose={() => setCancelTarget(null)} />
        </RoleLayout>
    );
}

function ReasonModal({ open, title, endpoint, action, tone, onClose }: { open: boolean; title: string; endpoint: string; action: string; tone: 'danger'; onClose: () => void }) {
    const form = useForm({ reason: '' });
    useEffect(() => { if (open) { form.reset(); form.clearErrors(); } /* eslint-disable-next-line */ }, [open]);
    return (
        <AdminModal open={open} title={title} subtitle="The reason is shown to the patient and written to the activity log." icon="✕" danger={tone === 'danger'} onClose={onClose}
            footer={<><button type="button" className="btn btn-quiet" onClick={onClose}>Back</button><button type="submit" form="request-reason-form" className="btn btn-danger" disabled={form.processing}>{form.processing ? 'Saving…' : action}</button></>}>
            <form id="request-reason-form" onSubmit={(event) => { event.preventDefault(); form.post(endpoint, { preserveScroll: true, onSuccess: onClose }); }}>
                <div className="field"><label className="label req" htmlFor="request-reason">Reason</label><textarea id="request-reason" className="textarea" rows={3} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} />{form.errors.reason ? <span className="field-error">{form.errors.reason}</span> : null}</div>
            </form>
        </AdminModal>
    );
}