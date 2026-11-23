/**
 * Admin · Master dashboard — prototypes/admin/01-dashboard.html.
 * Server aggregates KPI, system-health, attention-queue and 12-week trend data;
 * every region honours the four-state contract (G3) and money is pre-formatted
 * by CurrencyFormatter (G1). ?range=7|30|90|365 is server-side and URL-synced.
 */
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { DataState, SharedProps } from '@/types';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { KpiCard } from '@/Components/Data/KpiCard';
import { cn } from '@/lib/format';

interface DashboardProps {
    range: number;
    dataState: DataState;
    lastKnownAt: string;
    kpis: {
        totalUsers: number;
        awaitingAction: number;
        activePatients: number;
        verifiedDoctors: number;
        prescriptionsIssued: number;
        activeMedicines: number;
        appointments: number;
        revenueFormatted: string;
        revenueCompact: string;
        commissionCompact: string;
        commissionFormatted: string;
        revenuePercent: number;
        revenueConfigured: boolean;
        completionRate: number;
    };
    system: {
        db: boolean;
        queueSize: number;
        storagePercent: number | null;
        onDutyDoctors: number;
        activePatients: number;
        pendingPrescriptions: number;
        pendingApprovals: number;
        pendingVerifications: number;
        pendingReviews: number;
        lowStock: number;
        outOfStock: number;
        expiring: number;
        aiEnabled: boolean;
        aiModel: string;
    };
    attention: Array<{
        key: string;
        label: string;
        count: number;
        tone: 'warning' | 'danger' | 'neutral';
        href: string;
    }>;
    trend: Array<{ label: string; booked: number; completed: number }>;
}

const RANGES = [
    { value: 7, label: '7d' },
    { value: 30, label: '30d' },
    { value: 90, label: '90d' },
    { value: 365, label: '12mo' },
];

export default function AdminDashboard({
    range,
    dataState,
    lastKnownAt,
    kpis,
    system,
    attention,
    trend,
}: DashboardProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(dataState);

    const retry = () => router.reload();

    const bookedSeries = trend.map((week) => week.booked);
    const maxTrend = Math.max(1, ...trend.flatMap((week) => [week.booked, week.completed]));

    const storageTone = system.storagePercent === null
        ? 'info'
        : system.storagePercent >= 85
            ? 'warning'
            : 'success';

    return (
        <RoleLayout
            role="admin"
            title="Master dashboard"
            subtitle={`${settings.clinicName} · last ${range} days`}
            activeHref="/admin/dashboard"
            topbarActions={
                <div className="mp-row" role="group" aria-label="Date range">
                    {RANGES.map((option) => (
                        <a
                            key={option.value}
                            className={cn('chip chip-select', option.value === range && 'is-on')}
                            href={`/admin/dashboard?range=${option.value}`}
                            aria-current={option.value === range ? 'true' : undefined}
                        >
                            {option.value === range ? <strong aria-hidden="true">✓ </strong> : null}
                            {option.label}
                        </a>
                    ))}
                </div>
            }
        >
            <Head title="Admin dashboard — MediPath" />

            <DataView
                name="Data view · Platform KPIs"
                state={state}
                onStateChange={setState}
                showSwitch
                onRetry={retry}
                lastKnownAt={lastKnownAt}
                emptyTitle="No activity in the selected range"
                emptyBody="Expand the range or wait for the first booking, prescription or payment."
                errorMessage="Aggregation timed out. Queries are bounded; try a smaller range or retry."
                skeleton={
                    <div className="grid" aria-hidden="true">
                        {[0, 1, 2, 3, 4, 5].map((item) => (
                            <div className={cn(item < 4 ? 'c-6 md-c-3' : 'c-12 md-c-6')} key={item}>
                                <KpiCard label="Loading metric" state="skeleton" />
                            </div>
                        ))}
                    </div>
                }
            >
                <div className="grid">
                    <div className="c-6 md-c-3">
                        <KpiCard
                            label="Users"
                            value={kpis.totalUsers}
                            trend="up"
                            delta={`${kpis.activePatients} active patients`}
                            href="/admin/users"
                        />
                    </div>
                    <div className="c-6 md-c-3">
                        <KpiCard
                            label="Doctors awaiting action"
                            value={kpis.awaitingAction}
                            tone={kpis.awaitingAction > 0 ? 'warning' : 'success'}
                            trend={kpis.awaitingAction > 0 ? 'down' : 'flat'}
                            delta={`${system.pendingVerifications} verifications · ${system.pendingApprovals} approvals`}
                            href="/admin/approvals"
                        />
                    </div>
                    <div className="c-6 md-c-3">
                        <KpiCard
                            label={`Appointments (${range}d)`}
                            value={kpis.appointments}
                            trend="up"
                            delta={`${system.onDutyDoctors} doctors on duty today`}
                            sparkline={bookedSeries}
                            href="/admin/records?tab=appointments"
                        />
                    </div>
                    <div className="c-6 md-c-3">
                        <KpiCard
                            label="Revenue collected"
                            money={kpis.revenueFormatted}
                            trend="up"
                            delta={`${kpis.prescriptionsIssued} prescriptions issued`}
                        />
                    </div>
                    <div className="c-12 md-c-6">
                        <KpiCard
                            label={`Commission revenue (revenue % = ${kpis.revenuePercent.toFixed(1)})`}
                            money={kpis.commissionFormatted}
                            trend="flat"
                            delta={kpis.revenueConfigured ? 'configurable in Revenue settings' : 'configure revenue % in settings'}
                            href="/admin/reports"
                        />
                    </div>
                    <div className="c-12 md-c-6">
                        <KpiCard
                            label="Prescription completion rate"
                            value={`${kpis.completionRate}%`}
                            tone={kpis.completionRate >= 80 ? 'success' : 'warning'}
                            trend="up"
                            delta={`${kpis.activeMedicines} active medicines`}
                        />
                    </div>
                </div>
            </DataView>

            <section className="card card-pad mt-l" aria-labelledby="system-title">
                <div className="spread">
                    <h2 className="h4" id="system-title">System overview</h2>
                    <span className="caption">live health + drill-down links · checks run server-side</span>
                </div>
                <div className="grid mt-m">
                    <SystemCard
                        tone={system.queueSize === 0 ? 'success' : 'warning'}
                        title={`${system.queueSize === 0 ? '✅' : '🟡'} App & queue`}
                        body={`worker ${system.queueSize === 0 ? '✓ idle' : `⚠ ${system.queueSize} job(s) pending`} · notifications queue`}
                        href="/admin/data"
                        action="Queue detail"
                    />
                    <SystemCard
                        tone={system.db ? 'success' : 'danger'}
                        title={`${system.db ? '✅' : '🛑'} Database`}
                        body={system.db ? 'connection healthy · bounded aggregate queries' : 'database check failed — decisions remain in DB queue'}
                        href="/admin/records"
                        action="Clinical records"
                    />
                    <SystemCard
                        tone={storageTone}
                        title={`${storageTone === 'success' ? '✅' : '🟡'} Storage`}
                        body={system.storagePercent === null
                            ? 'usage unavailable on this host'
                            : `private disk ${system.storagePercent}% used — plan cleanup before 85%`}
                        href="/admin/data"
                        action="Data management"
                    />
                    <SystemCard
                        tone="info"
                        title={`${system.aiEnabled ? '🔵' : '⚪'} AI provider`}
                        body={system.aiEnabled
                            ? `model ${system.aiModel} · deterministic safety paths always available`
                            : 'disabled · chatbot uses deterministic red-flag rules + FAQ only'}
                        href="/admin/safety"
                        action="Safety rules"
                    />
                </div>
            </section>

            <div className="grid mt-l" style={{ alignItems: 'start' }}>
                <section className="c-12 md-c-6 card card-pad" aria-labelledby="attention-title">
                    <h2 className="h4" id="attention-title">Attention queue</h2>
                    <ul className="list-quiet mt-s" style={{ fontSize: 14 }}>
                        {attention.map((item) => (
                            <li key={item.key}>
                                <span className={cn('badge', item.tone === 'danger' ? 'badge-danger' : item.tone === 'warning' ? 'badge-warning' : 'badge-neutral')}>
                                    <span className="dot" aria-hidden="true" />
                                    <span className="tabular">{item.count}</span>
                                </span>
                                <span>{item.label}</span>
                                <a className="mla" href={item.href}>open →</a>
                            </li>
                        ))}
                    </ul>
                    <p className="caption mt-m">
                        Also live: {system.pendingPrescriptions} prescriptions waiting to dispense · {system.lowStock} low stock ·{' '}
                        {system.outOfStock} out of stock · {system.expiring} expiring within 90 days.
                    </p>
                </section>

                <section className="c-12 md-c-6 card card-pad" aria-labelledby="trend-title">
                    <h2 className="h4" id="trend-title">Trend — appointments vs completed (12 weeks)</h2>
                    <div className="bars mt-m" aria-hidden="true">
                        {trend.map((week) => (
                            <i key={`b-${week.label}`} style={{ height: `${Math.max(4, (week.booked / maxTrend) * 100)}%` }} title={`${week.label}: ${week.booked} booked`} />
                        ))}
                    </div>
                    <div className="bars mt-s" aria-hidden="true" style={{ height: 36 }}>
                        {trend.map((week) => (
                            <i className="alt" key={`c-${week.label}`} style={{ height: `${Math.max(4, (week.completed / maxTrend) * 100)}%` }} title={`${week.label}: ${week.completed} completed`} />
                        ))}
                    </div>
                    <p className="caption">legend: <span className="seat" style={{ background: 'var(--mp-chart-1)' }} /> booked · <span className="seat" style={{ background: 'var(--mp-chart-2)' }} /> completed</p>
                    <table className="sr-only">
                        <caption>Weekly appointment trend</caption>
                        <thead><tr><th>Week</th><th>Booked</th><th>Completed</th></tr></thead>
                        <tbody>
                            {trend.map((week) => (
                                <tr key={`t-${week.label}`}><td>{week.label}</td><td>{week.booked}</td><td>{week.completed}</td></tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            </div>
        </RoleLayout>
    );
}

function SystemCard({
    tone,
    title,
    body,
    href,
    action,
}: {
    tone: 'success' | 'warning' | 'danger' | 'info';
    title: string;
    body: string;
    href: string;
    action: string;
}) {
    const style = {
        success: { background: 'var(--mp-success-weak-bg)', borderColor: 'var(--mp-success-weak-border)' },
        warning: { background: 'var(--mp-warning-weak-bg)', borderColor: 'var(--mp-warning-weak-border)' },
        danger: { background: 'var(--mp-danger-weak-bg)', borderColor: 'var(--mp-danger-weak-border)' },
        info: { background: 'var(--mp-info-weak-bg)', borderColor: 'var(--mp-info-weak-border)' },
    }[tone];

    return (
        <div className="c-12 sm-c-6 md-c-3 card card-pad" style={style}>
            <strong>{title}</strong>
            <p className="caption">{body}</p>
            <a className="btn btn-ghost mt-s" style={{ color: 'var(--mp-text-body)' }} href={href}>{action}</a>
        </div>
    );
}