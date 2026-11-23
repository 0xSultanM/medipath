/**
 * Admin · Reports & analytics — prototypes/admin/08-reports.html.
 * Seven server-computed sections, range chips, and server-side CSV/PDF exports
 * (CSV neutralises formula injection; PDF is built from the same dataset).
 */
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import type { DataState } from '@/types';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { DataTable, type DataColumn } from '@/Components/Data/DataTable';
import { Money } from '@/Components/ui/Money';
import { cn, formatDate } from '@/lib/format';
import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';

interface Report {
    range: number;
    from: string;
    to: string;
    monthlyRevenue: { labels: string[]; values: number[]; totalFormatted: string };
    patientGrowth: { labels: string[]; values: number[]; total: number; walkIns: number };
    prescriptions: { total: number; dispensed: number; cancelled: number; completionRate: number; refusedRate: number; medianDispenseMinutes: number | null; medianDispenseLabel: string };
    collection: { paidFormatted: string; paidCount: number; pendingFormatted: string; pendingCount: number; refundedFormatted: string; refundedCount: number; aging: { under30: string; between31And60: string; over60: string } };
    stock: { out: number; critical: number; low: number; expiring30: number; expiring90: number; items: Array<{ name: string; strength: string | null; stock: number; status: string; expiry: string }> };
    appointments: { total: number; pending: number; accepted: number; completed: number; cancelled: number; acceptanceRate: number; completionRate: number; cancellationRate: number; doctorCancelled: number };
    leaderboard: Array<{ id: number; name: string | null; specialty: string | null; visits: number; completion: number; rating: number | null; revenueFormatted: string; revenue: number }>;
}

interface ReportsProps {
    report: Report;
    dataState: DataState;
    lastKnownAt: string;
}

const RANGES = [30, 90, 365];

export default function AdminReports({ report, dataState, lastKnownAt }: ReportsProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(dataState);
    const maxRevenue = Math.max(1, ...report.monthlyRevenue.values);
    const maxGrowth = Math.max(1, ...report.patientGrowth.values);

    const leaderColumns: Array<DataColumn<Report['leaderboard'][number]>> = [
        { key: 'rank', label: '#', align: 'right', render: (row) => <span className="num">{report.leaderboard.findIndex((item) => item.id === row.id) + 1}</span> },
        { key: 'doctor', label: 'Doctor', render: (row) => <div><strong>{row.name}</strong><div className="caption">{row.specialty}</div></div> },
        { key: 'visits', label: 'Visits', align: 'right', render: (row) => <span className="num tabular">{row.visits}</span> },
        { key: 'completion', label: 'Completion', align: 'right', render: (row) => <span className="num tabular">{row.completion}%</span> },
        { key: 'rating', label: 'Rating', align: 'right', render: (row) => <span className="num tabular">{row.rating ?? '—'}{row.rating ? ' ★' : ''}</span> },
        { key: 'share', label: 'Revenue share', align: 'right', render: (row) => <Money formatted={row.revenueFormatted} size="sm" /> },
    ];

    return (
        <RoleLayout
            role="admin"
            title="Reports & analytics"
            subtitle={`${formatDate(report.from, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })} → ${formatDate(report.to, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })}`}
            activeHref="/admin/reports"
            topbarActions={
                <div className="mp-row">
                    <a className="btn btn-outline" href={`/admin/reports/export/csv?range=${report.range}`}>⬇ CSV</a>
                    <a className="btn btn-outline" href={`/admin/reports/export/pdf?range=${report.range}`}>⬇ PDF</a>
                </div>
            }
        >
            <Head title="Reports & analytics — MediPath" />

            <div className="mp-row" role="group" aria-label="Range">
                {RANGES.map((value) => (
                    <a key={value} className={cn('chip chip-select', value === report.range && 'is-on')} href={`/admin/reports?range=${value}`} aria-current={value === report.range ? 'true' : undefined}>
                        {value === report.range ? <strong aria-hidden="true">✓ </strong> : null}{value === 365 ? 'Year' : `${value}d`}
                    </a>
                ))}
                <span className="caption">exports stream server-side; CSVs neutralise formula injection</span>
            </div>

            <DataView
                name="Data view · 7 report sections"
                state={state}
                onStateChange={setState}
                showSwitch
                onRetry={() => router.reload()}
                lastKnownAt={lastKnownAt}
                emptyTitle="No data for the selected range"
                emptyBody="Widen the period or wait for appointments, prescriptions or payments."
                errorMessage="Some sections failed. Healthy tiles remain; exports include only succeeded datasets."
                skeleton={
                    <div className="grid" aria-hidden="true">
                        {[0, 1, 2, 3, 4, 5].map((item) => <div className="c-12 md-c-6" key={item}><div className="sk" style={{ height: 170, borderRadius: 12 }} /></div>)}
                    </div>
                }
            >
                <div className="grid">
                    <section className="card card-pad c-12 md-c-6" aria-labelledby="rep-1">
                        <div className="spread"><h2 className="h4" id="rep-1">1 · Monthly revenue</h2><Money formatted={report.monthlyRevenue.totalFormatted} size="sm" /></div>
                        <div className="bars mt-m" aria-hidden="true">
                            {report.monthlyRevenue.values.map((value, index) => <i key={index} style={{ height: `${Math.max(4, (value / maxRevenue) * 100)}%` }} />)}
                        </div>
                        <p className="caption">consultations + dispensing · commission configured in Settings.</p>
                    </section>

                    <section className="card card-pad c-12 md-c-6" aria-labelledby="rep-2">
                        <div className="spread"><h2 className="h4" id="rep-2">2 · Patient growth</h2><span className="caption">+{report.patientGrowth.total} in range</span></div>
                        <div className="bars mt-m" aria-hidden="true">
                            {report.patientGrowth.values.map((value, index) => <i className="alt" key={index} style={{ height: `${Math.max(4, (value / maxGrowth) * 100)}%` }} />)}
                        </div>
                        <p className="caption">{report.patientGrowth.walkIns} walk-in registrations · full split in CSV.</p>
                    </section>

                    <section className="card card-pad c-12 md-c-6" aria-labelledby="rep-3">
                        <h2 className="h4" id="rep-3">3 · Prescription completion</h2>
                        <dl className="mt-s">
                            <div className="def-row"><dt>Saved → dispensed</dt><dd><strong>{report.prescriptions.completionRate}%</strong> · {report.prescriptions.dispensed}/{report.prescriptions.total}</dd></div>
                            <div className="def-row"><dt>Cancelled / refused</dt><dd>{report.prescriptions.refusedRate}% ({report.prescriptions.cancelled})</dd></div>
                            <div className="def-row"><dt>Median time to dispense</dt><dd className="tabular">{report.prescriptions.medianDispenseLabel}</dd></div>
                        </dl>
                        <div className="progress good" style={{ marginTop: 8 }}><span style={{ width: `${report.prescriptions.completionRate}%` }} /></div>
                    </section>

                    <section className="card card-pad c-12 md-c-6" aria-labelledby="rep-4">
                        <h2 className="h4" id="rep-4">4 · Invoice collection</h2>
                        <dl className="mt-s">
                            <div className="def-row"><dt>Paid</dt><dd><Money formatted={report.collection.paidFormatted} size="sm" /> · {report.collection.paidCount}</dd></div>
                            <div className="def-row"><dt>Pending</dt><dd><Money formatted={report.collection.pendingFormatted} size="sm" /> · {report.collection.pendingCount}</dd></div>
                            <div className="def-row"><dt>Refunded</dt><dd><Money formatted={report.collection.refundedFormatted} size="sm" /> · {report.collection.refundedCount}</dd></div>
                        </dl>
                        <p className="caption">aging ≤30d <Money formatted={report.collection.aging.under30} size="sm" /> · 31–60d <Money formatted={report.collection.aging.between31And60} size="sm" /> · &gt;60d <Money formatted={report.collection.aging.over60} size="sm" /></p>
                    </section>

                    <section className="card card-pad c-12 md-c-6" aria-labelledby="rep-5">
                        <h2 className="h4" id="rep-5">5 · Stock health &amp; expiry risk</h2>
                        <div className="mp-row mt-s">
                            <span className="badge badge-danger"><span className="dot" />{report.stock.out} out</span>
                            <span className="badge badge-danger"><span className="dot" />{report.stock.critical} critical</span>
                            <span className="badge badge-warning"><span className="dot" />{report.stock.low} low</span>
                            <span className="badge badge-warning"><span className="dot" />{report.stock.expiring30} expiring ≤30d</span>
                        </div>
                        <ul className="list-quiet mt-s" style={{ fontSize: 13 }}>
                            {report.stock.items.length === 0 ? <li>All active medicines are healthy.</li> : report.stock.items.map((item) => (
                                <li key={`${item.name}-${item.strength}`}><strong>{item.name}</strong> {item.strength} — {item.stock} left · {item.expiry}</li>
                            ))}
                        </ul>
                    </section>

                    <section className="card card-pad c-12 md-c-6" aria-labelledby="rep-6">
                        <h2 className="h4" id="rep-6">6 · Appointment conversion &amp; cancellation</h2>
                        <dl className="mt-s">
                            <div className="def-row"><dt>Acceptance</dt><dd><strong>{report.appointments.acceptanceRate}%</strong></dd></div>
                            <div className="def-row"><dt>Accepted → completed</dt><dd><strong>{report.appointments.completionRate}%</strong></dd></div>
                            <div className="def-row"><dt>Cancellation rate</dt><dd><strong>{report.appointments.cancellationRate}%</strong></dd></div>
                        </dl>
                        <p className="caption">{report.appointments.total} appointments · {report.appointments.pending} pending · {report.appointments.completed} completed.</p>
                    </section>

                    <section className="card card-pad c-12" aria-labelledby="rep-7">
                        <h2 className="h4" id="rep-7">7 · Top-doctor leaderboard ({report.range}d)</h2>
                        <div className="mt-s">
                            <DataTable
                                className="tbl-cards-md"
                                caption="Doctor leaderboard by revenue in the selected range"
                                columns={leaderColumns}
                                rows={report.leaderboard}
                                getRowKey={(row) => row.id}
                                state="success"
                                emptyTitle="No doctor activity in range"
                                emptyBody="Completed visits and paid invoices populate the leaderboard."
                            />
                        </div>
                    </section>
                </div>
            </DataView>
        </RoleLayout>
    );
}