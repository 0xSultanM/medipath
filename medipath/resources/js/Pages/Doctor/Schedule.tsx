/**
 * Doctor · Schedule & leave — prototypes/doctor/02-schedule-leave.html.
 */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import type { DataState, SharedProps } from '@/types';
import type { DoctorLeaveRow, DoctorScheduleBlock } from '@/types/doctor';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { AdminModal } from '@/Components/Admin/AdminModal';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { formatDate } from '@/lib/format';

interface ScheduleProps {
    blocks: DoctorScheduleBlock[];
    leaves: DoctorLeaveRow[];
    weekdays: Array<{ value: number; label: string }>;
    dataState: DataState;
    lastKnownAt: string;
}

export default function DoctorSchedule({ blocks, leaves, weekdays, dataState, lastKnownAt }: ScheduleProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(dataState);
    const [blockOpen, setBlockOpen] = useState(false);
    const [leaveOpen, setLeaveOpen] = useState(false);

    const grouped = useMemo(() => weekdays.map((day) => ({
        ...day,
        blocks: blocks.filter((block) => block.dayOfWeek === day.value),
    })), [blocks, weekdays]);

    return (
        <RoleLayout
            role="doctor"
            title="Weekly schedule"
            subtitle={`${blocks.filter((b) => b.isActive).length} active block(s) · ${leaves.length} upcoming leave(s)`}
            activeHref="/doctor/schedule"
            topbarActions={<><button type="button" className="btn btn-outline" onClick={() => setLeaveOpen(true)}>+ Add leave</button><button type="button" className="btn btn-primary" onClick={() => setBlockOpen(true)}>+ Add block</button></>}
        >
            <Head title="Schedule & leave — MediPath" />

            <DataView name="Data view · Weekly availability" state={state} onStateChange={setState} showSwitch onRetry={() => router.reload()} lastKnownAt={lastKnownAt}
                emptyTitle="No hours published" emptyBody="Patients cannot book you until at least one weekday block exists."
                errorMessage="Schedule failed to load."
                skeleton={<div className="grid" aria-hidden="true">{[0, 1, 2, 3].map((i) => <div className="c-12 sm-c-6 lg-c-4" key={i}><div className="sk" style={{ height: 150, borderRadius: 12 }} /></div>)}</div>}>
                <div className="grid" style={{ alignItems: 'start' }}>
                    {grouped.map((day) => (
                        <div className="c-12 sm-c-6 lg-c-4 card card-pad" key={day.value}>
                            <div className="spread"><h2 className="h4">{day.label}</h2><span className="caption">{day.blocks.filter((b) => b.isActive).length} block(s)</span></div>
                            <div className="stack-sm mt-s">
                                {day.blocks.length === 0 ? <p className="caption">Clinic closed / no blocks.</p> : day.blocks.map((block) => (
                                    <div className="chip spread" style={{ minHeight: 0, padding: '8px 12px', opacity: block.isActive ? 1 : 0.55 }} key={block.id}>
                                        <span className="tabular">{block.start}–{block.end}</span>
                                        <span className="caption">{block.slotDuration}-min</span>
                                        <button type="button" className="btn btn-ghost btn-sm" onClick={() => router.post(`/doctor/schedule/blocks/${block.id}/toggle`, {}, { preserveScroll: true })}>{block.isActive ? 'Disable' : 'Enable'}</button>
                                    </div>
                                ))}
                                {day.blocks.some((b) => b.breakStart) ? <div className="caption">Break {day.blocks.find((b) => b.breakStart)?.breakStart}–{day.blocks.find((b) => b.breakStart)?.breakEnd}</div> : null}
                            </div>
                            <button type="button" className="btn btn-ghost btn-sm mt-s" onClick={() => setBlockOpen(true)}>+ Add block</button>
                        </div>
                    ))}
                </div>
            </DataView>

            <section className="card card-pad mt-l" aria-labelledby="leave-title">
                <div className="spread"><h2 className="h4" id="leave-title">Leave</h2><span className="caption">Approved leave hides future slots; overlapping active appointments are cancelled and patients notified.</span></div>
                {leaves.length === 0 ? <p className="muted mt-s">No upcoming approved leave.</p> : (
                    <div className="tbl-wrap mt-m"><table className="tbl"><caption className="sr-only">Upcoming leave</caption><thead><tr><th>Period</th><th>Reason</th><th>Impact</th><th><span className="sr-only">Actions</span></th></tr></thead><tbody>
                        {leaves.map((leave) => (
                            <tr key={leave.id}>
                                <td data-label="Period" className="tabular">{formatDate(leave.start, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })}–{formatDate(leave.end, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })}</td>
                                <td data-label="Reason">{leave.reason}</td>
                                <td data-label="Impact"><span className={leave.impacted > 0 ? 'badge badge-warning' : 'badge badge-neutral'}><span className="dot" />{leave.impacted} booked</span></td>
                                <td data-label="Actions"><button type="button" className="btn btn-ghost btn-sm" style={{ color: 'var(--mp-danger-text)' }} onClick={() => router.post(`/doctor/schedule/leaves/${leave.id}/cancel`, {}, { preserveScroll: true })}>Cancel leave</button></td>
                            </tr>
                        ))}
                    </tbody></table></div>
                )}
            </section>

            <BlockModal open={blockOpen} weekdays={weekdays} onClose={() => setBlockOpen(false)} />
            <LeaveModal open={leaveOpen} onClose={() => setLeaveOpen(false)} />
        </RoleLayout>
    );
}

function BlockModal({ open, weekdays, onClose }: { open: boolean; weekdays: Array<{ value: number; label: string }>; onClose: () => void }) {
    const form = useForm({ day_of_week: '1', start_time: '09:00', end_time: '13:00', slot_duration: '30', break_start: '', break_end: '' });
    useEffect(() => { if (open) { form.reset(); form.clearErrors(); } /* eslint-disable-next-line */ }, [open]);
    return (
        <AdminModal open={open} title="Add availability block" subtitle="No overlapping active block per weekday." icon="🗓" onClose={onClose}
            footer={<><button type="button" className="btn btn-quiet" onClick={onClose}>Cancel</button><button type="submit" form="block-form" className="btn btn-primary" disabled={form.processing}>{form.processing ? 'Saving…' : 'Add block'}</button></>}>
            <form id="block-form" className="stack-sm" onSubmit={(event) => { event.preventDefault(); form.post('/doctor/schedule/blocks', { preserveScroll: true, onSuccess: onClose }); }}>
                <div className="grid tight">
                    <div className="c-12 field"><label className="label req" htmlFor="block-day">Weekday</label><select id="block-day" className="select" value={form.data.day_of_week} onChange={(event) => form.setData('day_of_week', event.target.value)}>{weekdays.map((day) => <option key={day.value} value={day.value}>{day.label}</option>)}</select></div>
                    <div className="c-6 field"><label className="label req" htmlFor="block-start">Start</label><input id="block-start" className="input" type="time" value={form.data.start_time} onChange={(event) => form.setData('start_time', event.target.value)} /></div>
                    <div className="c-6 field"><label className="label req" htmlFor="block-end">End</label><input id="block-end" className="input" type="time" value={form.data.end_time} onChange={(event) => form.setData('end_time', event.target.value)} /></div>
                    <div className="c-6 field"><label className="label" htmlFor="block-break-start">Break start</label><input id="block-break-start" className="input" type="time" value={form.data.break_start} onChange={(event) => form.setData('break_start', event.target.value)} /></div>
                    <div className="c-6 field"><label className="label" htmlFor="block-break-end">Break end</label><input id="block-break-end" className="input" type="time" value={form.data.break_end} onChange={(event) => form.setData('break_end', event.target.value)} /></div>
                    <div className="c-12 field"><label className="label" htmlFor="block-duration">Slot duration</label><select id="block-duration" className="select" value={form.data.slot_duration} onChange={(event) => form.setData('slot_duration', event.target.value)}>{[10, 15, 20, 30, 45, 60].map((minutes) => <option key={minutes} value={minutes}>{minutes} minutes</option>)}</select></div>
                </div>
                {Object.values(form.errors).filter(Boolean).map((error) => <span className="field-error" key={error} role="alert">{error}</span>)}
            </form>
        </AdminModal>
    );
}

function LeaveModal({ open, onClose }: { open: boolean; onClose: () => void }) {
    const form = useForm({ start_date: '', end_date: '', reason: '' });
    useEffect(() => { if (open) { form.reset(); form.clearErrors(); } /* eslint-disable-next-line */ }, [open]);
    return (
        <AdminModal open={open} title="Add approved leave" subtitle="Overlapping active appointments will be cancelled and patients notified." icon="🗓" danger onClose={onClose}
            footer={<><button type="button" className="btn btn-quiet" onClick={onClose}>Cancel</button><button type="submit" form="leave-form" className="btn btn-danger" disabled={form.processing}>{form.processing ? 'Saving…' : 'Save leave'}</button></>}>
            <form id="leave-form" className="stack-sm" onSubmit={(event) => { event.preventDefault(); form.post('/doctor/schedule/leaves', { preserveScroll: true, onSuccess: onClose }); }}>
                <div className="grid tight">
                    <div className="c-6 field"><label className="label req" htmlFor="leave-start">Start</label><input id="leave-start" className="input" type="date" value={form.data.start_date} onChange={(event) => form.setData('start_date', event.target.value)} /></div>
                    <div className="c-6 field"><label className="label req" htmlFor="leave-end">End</label><input id="leave-end" className="input" type="date" value={form.data.end_date} onChange={(event) => form.setData('end_date', event.target.value)} /></div>
                </div>
                <div className="field"><label className="label req" htmlFor="leave-reason">Reason (internal)</label><input id="leave-reason" className="input" value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} /></div>
                <div className="alert alert-warning"><span className="a-ico" aria-hidden="true">⚠</span><span>Patients with active appointments in this window receive a cancellation notice and must rebook.</span></div>
                {Object.values(form.errors).filter(Boolean).map((error) => <span className="field-error" key={error} role="alert">{error}</span>)}
            </form>
        </AdminModal>
    );
}