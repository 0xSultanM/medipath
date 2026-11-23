/**
 * Doctor · Dashboard — prototypes/doctor/01-dashboard.html.
 */
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { DataState, SharedProps } from '@/types';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { KpiCard } from '@/Components/Data/KpiCard';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { formatDate } from '@/lib/format';

interface DashboardProps {
    kpis: { todayTotal: number; todayCompleted: number; pendingRequests: number; prescriptionsWeek: number; averageRating: number | null; reviewCount: number };
    timeline: Array<{ id: number; code: string; start: string; end: string; patientId: number; patient: string | null; patientCode: string | null; reason: string; status: string; statusLabel: string; isWalkIn: boolean }>;
    pending: Array<{ id: number; code: string; date: string | null; time: string; patient: string | null; patientCode: string | null; reason: string; requestedAt: string | null }>;
    nextLeave: { start: string; end: string; reason: string } | null;
    dataState: DataState;
    lastKnownAt: string;
}

export default function DoctorDashboard({ kpis, timeline, pending, nextLeave, dataState, lastKnownAt }: DashboardProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(dataState);

    return (
        <RoleLayout
            role="doctor"
            title={`Today — ${formatDate(new Date().toISOString(), { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })}`}
            subtitle={settings.clinicName}
            activeHref="/doctor/dashboard"
            topbarActions={nextLeave ? <span className="badge badge-warning"><span className="dot" />Leave {formatDate(nextLeave.start, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })}–{formatDate(nextLeave.end, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })}</span> : undefined}
        >
            <Head title="Doctor dashboard — MediPath" />

            <DataView name="Data view · Doctor KPIs" state={state} onStateChange={setState} showSwitch onRetry={() => router.reload()} lastKnownAt={lastKnownAt}
                emptyTitle="No metrics yet" emptyBody="KPIs appear after your first approved schedule and patient."
                errorMessage="KPI aggregation failed. Requests and timeline load independently."
                skeleton={<div className="grid" aria-hidden="true">{[0, 1, 2, 3].map((i) => <div className="c-6 md-c-3" key={i}><KpiCard label="Loading metric" state="skeleton" /></div>)}</div>}>
                <div className="grid">
                    <div className="c-6 md-c-3"><KpiCard label="Appointments today" value={kpis.todayTotal} trend="up" delta={`${kpis.todayCompleted} completed`} href="/doctor/requests?tab=today" /></div>
                    <div className="c-6 md-c-3"><KpiCard label="Pending requests" value={kpis.pendingRequests} tone={kpis.pendingRequests > 0 ? 'warning' : 'success'} trend={kpis.pendingRequests > 0 ? 'down' : 'flat'} delta="awaiting your reply" href="/doctor/requests" /></div>
                    <div className="c-6 md-c-3"><KpiCard label="Prescriptions this week" value={kpis.prescriptionsWeek} trend="up" delta="issued from this workspace" href="/doctor/prescriptions/new" /></div>
                    <div className="c-6 md-c-3"><KpiCard label="Average rating" value={kpis.averageRating ?? '—'} trend="flat" delta={`${kpis.reviewCount} approved review(s)`} href="/doctor/reviews" /></div>
                </div>
            </DataView>

            <div className="grid mt-l" style={{ alignItems: 'start' }}>
                <section className="c-12 lg-c-7 card card-pad" aria-labelledby="timeline-title">
                    <div className="spread"><h2 className="h4" id="timeline-title">Today's timeline</h2><a className="caption" href="/doctor/schedule">Edit schedule</a></div>
                    {timeline.length === 0 ? <p className="muted mt-m">No appointments today.</p> : (
                        <ul className="stack-sm mt-m" style={{ fontSize: 14 }}>
                            {timeline.map((item) => (
                                <li key={item.id} className="mp-row" style={{ justifyContent: 'flex-start' }}>
                                    <span className="mono tabular" style={{ minWidth: 92, color: 'var(--mp-text-muted)' }}>{item.start}–{item.end}</span>
                                    <StatusBadge family="appointment" status={item.status} label={item.statusLabel} size="sm" />
                                    <span>{item.patient} {item.isWalkIn ? <span className="caption">walk-in</span> : null}</span>
                                    <span className="caption">{item.reason}</span>
                                    <a className="btn btn-ghost btn-sm mla" href={`/doctor/patients/${item.patientId}`}>Chart</a>
                                </li>
                            ))}
                        </ul>
                    )}
                    <div className="mp-row mt-b">
                        <a className="btn btn-primary" href="/doctor/prescriptions/new">Start prescription</a>
                        <a className="btn btn-outline" href="/doctor/walk-in">Register walk-in</a>
                    </div>
                </section>

                <section className="c-12 lg-c-5 card card-pad" aria-labelledby="pending-title">
                    <h2 className="h4" id="pending-title">Pending requests</h2>
                    {pending.length === 0 ? <p className="muted mt-s">No pending requests — you're current.</p> : (
                        <ul className="list-quiet mt-s" style={{ fontSize: 14 }}>
                            {pending.map((item) => (
                                <li key={item.id}>
                                    <div><strong>{item.patient}</strong> <span className="caption mono">{item.patientCode}</span><div className="caption">{formatDate(item.date, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })} · {item.time} · {item.reason}</div></div>
                                    <button type="button" className="btn btn-primary btn-sm mla" onClick={() => router.post(`/doctor/requests/${item.id}/accept`, {}, { preserveScroll: true })}>Accept</button>
                                </li>
                            ))}
                        </ul>
                    )}
                    <a className="btn btn-ghost btn-block mt-m" href="/doctor/requests">Open request queue</a>
                </section>
            </div>
        </RoleLayout>
    );
}