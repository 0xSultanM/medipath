/**
 * Patient · Dashboard — prototypes/patient/01-dashboard.html.
 * Next visit, server-aggregated KPIs, quick actions and the recent feed.
 */
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { DataState, SharedProps } from '@/types';
import type { PatientNextAppointment, PatientNotification } from '@/types/patient';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { KpiCard } from '@/Components/Data/KpiCard';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { Money } from '@/Components/ui/Money';
import { AdminModal } from '@/Components/Admin/AdminModal';
import { formatDate, relativeTime } from '@/lib/format';

interface DashboardProps {
    greetingName: string;
    nextAppointment: PatientNextAppointment | null;
    kpis: {
        upcomingVisits: number;
        prescriptions: number;
        pendingPrescriptions: number;
        outstandingFormatted: string;
        outstandingCount: number;
        symptomChecks: number;
    };
    notifications: PatientNotification[];
    settings: { cancellationCutoffHours: number };
    dataState: DataState;
    lastKnownAt: string;
}

export default function PatientDashboard({ greetingName, nextAppointment, kpis, notifications, settings, dataState, lastKnownAt }: DashboardProps) {
    const { ui, settings: appSettings } = usePage<SharedProps>().props;
    const [kpiState, setKpiState] = useState<DataState>(dataState);
    const [feedState, setFeedState] = useState<DataState>(notifications.length ? 'success' : 'empty');
    const [cancelOpen, setCancelOpen] = useState(false);
    const [reason, setReason] = useState('Schedule conflict');

    return (
        <RoleLayout
            role="patient"
            title={`Good ${new Date().getHours() < 12 ? 'morning' : new Date().getHours() < 17 ? 'afternoon' : 'evening'}, ${greetingName}`}
            subtitle={`${ui.appName} patient portal`}
            activeHref="/patient/dashboard"
        >
            <Head title="Patient dashboard — MediPath" />

            {nextAppointment ? (
                <section className="card card-pad" aria-labelledby="next-title">
                    <div className="spread" style={{ flexWrap: 'wrap' }}>
                        <div className="mp-row" style={{ minWidth: 0 }}>
                            <span className="avatar avatar-initials" aria-hidden="true">{nextAppointment.doctorName?.split(' ').slice(-2).map((part) => part[0]).join('')}</span>
                            <div style={{ minWidth: 0 }}>
                                <h2 id="next-title" className="h3">{nextAppointment.doctorName} <StatusBadge family="appointment" status={nextAppointment.status} label={nextAppointment.statusLabel} /></h2>
                                <p className="muted" style={{ fontSize: 14 }}>
                                    {nextAppointment.specialty} · {formatDate(nextAppointment.date, { locale: appSettings.locale, timezone: appSettings.timezone, dateFormat: appSettings.dateFormat })} {nextAppointment.time}
                                    {nextAppointment.clinic ? ` · ${nextAppointment.clinic}` : ''}{nextAppointment.room ? `, ${nextAppointment.room}` : ''} · <span className="mono">{nextAppointment.code}</span>
                                </p>
                                <p className="caption mt-s">Cancellation cutoff: {settings.cancellationCutoffHours}h before the slot (server setting).</p>
                            </div>
                        </div>
                        <div className="mp-row" style={{ flex: 'none' }}>
                            <a className="btn btn-outline" href={`/patient/book/${nextAppointment.doctorCode}?reschedule=${nextAppointment.id}`}>Reschedule</a>
                            <button type="button" className="btn btn-danger-outline" onClick={() => setCancelOpen(true)}>Cancel</button>
                        </div>
                    </div>
                </section>
            ) : (
                <section className="card card-pad">
                    <h2 className="h3">No upcoming visit</h2>
                    <p className="muted">Find a verified doctor and request a slot. Your visit will show here once sent.</p>
                    <a className="btn btn-primary mt-m" href="/patient/doctors">Find a doctor</a>
                </section>
            )}

            <DataView
                name="Data view · Patient KPIs"
                state={kpiState}
                onStateChange={setKpiState}
                showSwitch
                onRetry={() => router.reload()}
                lastKnownAt={lastKnownAt}
                emptyTitle="Your numbers appear after your first visit"
                emptyBody="Book an appointment or run a symptom check to get started."
                errorMessage="KPIs could not be loaded. Nothing was changed; retry is safe."
                skeleton={<div className="grid" aria-hidden="true">{[0, 1, 2, 3].map((i) => <div className="c-6 md-c-3" key={i}><KpiCard label="Loading metric" state="skeleton" /></div>)}</div>}
            >
                <div className="grid">
                    <div className="c-6 md-c-3"><KpiCard label="Upcoming visits" value={kpis.upcomingVisits} trend="up" delta="pending + accepted" href="/patient/appointments" /></div>
                    <div className="c-6 md-c-3"><KpiCard label="Prescriptions" value={kpis.prescriptions} trend="flat" delta={`${kpis.pendingPrescriptions} pending dispense`} href="/patient/prescriptions" /></div>
                    <div className="c-6 md-c-3"><KpiCard label="Outstanding bills" money={kpis.outstandingFormatted} tone={kpis.outstandingCount > 0 ? 'warning' : 'success'} trend={kpis.outstandingCount > 0 ? 'down' : 'flat'} delta={`${kpis.outstandingCount} unpaid invoice(s)`} href="/patient/bills" /></div>
                    <div className="c-6 md-c-3"><KpiCard label="Symptom checks" value={kpis.symptomChecks} trend="up" delta="AI assistant history" href="/patient/symptom-checker" /></div>
                </div>
            </DataView>

            <div className="grid mt-l" style={{ alignItems: 'start' }}>
                <section className="c-12 md-c-8 card card-pad" aria-labelledby="quick-title">
                    <h2 className="h4" id="quick-title">Quick actions</h2>
                    <div className="mp-row mt-m">
                        <a className="btn btn-outline" href="/patient/doctors">Book appointment</a>
                        <a className="btn btn-outline" href="/patient/symptom-checker">Check symptoms</a>
                        <a className="btn btn-outline" href="/patient/prescriptions">My prescriptions</a>
                        <a className="btn btn-outline" href="/patient/clinical-profile">Update allergies</a>
                    </div>
                    <div className="alert alert-info mt-l">
                        <span className="a-ico" aria-hidden="true">ℹ</span>
                        <span>The assistant provides general guidance, <strong>not a diagnosis</strong>. Red-flag symptoms are escalated by deterministic rules instantly.</span>
                    </div>
                    <p className="caption mt-m">Next visit fee: {nextAppointment ? <Money formatted={nextAppointment.feeFormatted} size="sm" /> : '—'}</p>
                </section>

                <section className="c-12 md-c-4">
                    <DataView
                        name="Data view · Recent activity"
                        state={feedState}
                        onStateChange={setFeedState}
                        showSwitch
                        lastKnownAt={lastKnownAt}
                        emptyTitle="No recent activity"
                        emptyBody="Notifications about visits, prescriptions and bills appear here."
                        errorMessage="Activity feed failed. Your records are unaffected."
                    >
                        <div className="card card-pad">
                            <ul className="list-quiet" style={{ fontSize: 13 }}>
                                {notifications.slice(0, 5).map((notification) => (
                                    <li key={notification.id}>
                                        <span>{notification.title}</span>
                                        <span className="caption mla">{relativeTime(notification.createdAt)}</span>
                                    </li>
                                ))}
                            </ul>
                            <a className="btn btn-ghost btn-sm mt-s" href="/patient/notifications">All notifications</a>
                        </div>
                    </DataView>
                </section>
            </div>

            <AdminModal
                open={cancelOpen}
                title="Cancel this appointment?"
                subtitle={nextAppointment ? `${nextAppointment.doctorName} · ${nextAppointment.code}` : undefined}
                icon="📅"
                danger
                onClose={() => setCancelOpen(false)}
                footer={
                    <>
                        <button type="button" className="btn btn-quiet" onClick={() => setCancelOpen(false)}>Keep appointment</button>
                        <button
                            type="button"
                            className="btn btn-danger"
                            onClick={() => {
                                if (!nextAppointment) return;
                                router.post(`/patient/appointments/${nextAppointment.id}/cancel`, { reason }, { preserveScroll: true, onSuccess: () => setCancelOpen(false) });
                            }}
                        >
                            Cancel appointment
                        </button>
                    </>
                }
            >
                <p style={{ fontSize: 14 }}>The slot returns to availability immediately. Cancellations inside the cutoff are recorded and visible to the doctor.</p>
                <div className="field">
                    <label className="label req" htmlFor="dash-cancel-reason">Reason (required)</label>
                    <select id="dash-cancel-reason" className="select" value={reason} onChange={(event) => setReason(event.target.value)}>
                        <option>Schedule conflict</option>
                        <option>Feeling better</option>
                        <option>Found another doctor</option>
                        <option>Other</option>
                    </select>
                </div>
            </AdminModal>
        </RoleLayout>
    );
}