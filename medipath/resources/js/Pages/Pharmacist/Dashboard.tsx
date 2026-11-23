/** Pharmacist · Dashboard — prototypes/pharmacist/01-dashboard.html. */
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { DataState, SharedProps } from '@/types';
import type { PharmacyKpis, PharmacyQueueRow, PharmacyStockRow } from '@/types/pharmacy';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { Money } from '@/Components/ui/Money';

interface DashboardProps {
    kpis: PharmacyKpis;
    queue: PharmacyQueueRow[];
    stock: PharmacyStockRow[];
    dataState: DataState;
    lastKnownAt: string;
}

function stockBadge(state: PharmacyQueueRow['stockState']): { status: string; label: string } {
    if (state === 'all_in_stock') return { status: 'healthy', label: 'All in stock' };
    if (state === 'partial') return { status: 'critical', label: 'Partial stock' };
    return { status: 'out_of_stock', label: 'Out of stock' };
}

export default function PharmacistDashboard({ kpis, queue, stock, dataState, lastKnownAt }: DashboardProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(dataState);

    return (
        <RoleLayout
            role="pharmacist"
            title="Pharmacy dashboard"
            subtitle="Approved pharmacists only · dispensing couples stock and billing atomically"
            activeHref="/pharmacist/dashboard"
            topbarActions={<a className="btn btn-primary" href="/pharmacist/prescriptions">Open pending queue</a>}
        >
            <Head title="Pharmacy dashboard — MediPath" />

            <DataView
                name="Data view · Pharmacy KPIs"
                state={state}
                onStateChange={setState}
                showSwitch
                onRetry={() => router.reload()}
                lastKnownAt={lastKnownAt}
                emptyTitle="Quiet shift"
                emptyBody="Nothing is pending and no stock alert is open right now."
                errorMessage="KPI fetch failed; queues load independently."
            >
                <div className="grid">
                    <div className="kpi c-12 sm-c-4 is-alert">
                        <span className="k-label">Pending prescriptions</span>
                        <span className="k-value tabular">{kpis.pending}</span>
                        <span className="k-foot"><span className="k-delta down">{kpis.oldestHours === null ? 'queue clear' : `oldest ${kpis.oldestHours}h`}</span></span>
                    </div>
                    <div className="kpi c-12 sm-c-4 is-bad">
                        <span className="k-label">Low/critical stock items</span>
                        <span className="k-value tabular">{kpis.lowStock}</span>
                        <span className="k-foot"><span className="k-delta down">{kpis.outOfStock} out of stock</span></span>
                    </div>
                    <div className="kpi c-12 sm-c-4">
                        <span className="k-label">Billed today</span>
                        <span className="k-value"><Money formatted={kpis.billedTodayFormatted} size="kpi" /></span>
                        <span className="k-foot"><span className="k-delta up">{kpis.invoicesToday} invoice(s) · {kpis.dispensedToday} dispensed</span></span>
                    </div>
                </div>

                <div className="grid mt-l" style={{ alignItems: 'start' }}>
                    <section className="c-12 lg-c-7 card card-pad">
                        <div className="spread">
                            <h2 className="h4">Oldest pending</h2>
                            <a className="btn btn-ghost btn-sm" href="/pharmacist/prescriptions">Full queue →</a>
                        </div>
                        {queue.length === 0 ? (
                            <p className="caption mt-m">Queue empty — new prescriptions arrive the moment a doctor saves them.</p>
                        ) : (
                            <ul className="list-quiet mt-m" style={{ fontSize: 14 }}>
                                {queue.map((row) => {
                                    const badge = stockBadge(row.stockState);
                                    return (
                                        <li key={row.id}>
                                            <div style={{ minWidth: 0 }}>
                                                <strong className="mono">{row.code}</strong> — {row.patientName}
                                                <span className="caption mono"> {row.patientCode}</span>
                                                <div className="caption">{row.doctorName} · {row.waitingHours}h ago · {row.items} item(s){row.hasOverride ? ' · sealed override' : ''}</div>
                                            </div>
                                            <StatusBadge family="stock" status={badge.status} label={badge.label} size="sm" />
                                            <a className="btn btn-primary btn-sm" href={`/pharmacist/prescriptions/${row.id}`}>Review &amp; dispense</a>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </section>

                    <section className="c-12 lg-c-5 card card-pad">
                        <div className="spread">
                            <h2 className="h4">Urgent stock queue</h2>
                            <a className="btn btn-ghost btn-sm" href="/pharmacist/alerts">Alerts →</a>
                        </div>
                        {stock.length === 0 ? (
                            <p className="caption mt-m">No stock or expiry warnings at the latest sweep.</p>
                        ) : (
                            <ul className="list-quiet mt-m" style={{ fontSize: 14 }}>
                                {stock.map((row) => {
                                    const width = Math.min(100, Math.round((row.stock / Math.max(row.threshold * 3, 1)) * 100));
                                    const tone = row.status === 'healthy' ? 'good' : row.status === 'low' ? 'warn' : 'bad';
                                    return (
                                        <li key={row.id}>
                                            <div style={{ minWidth: 0 }}>
                                                <strong>{row.name}</strong>
                                                <div className="caption">{row.stock} / min {row.threshold}{row.expiryDate ? ` · exp ${row.expiryDate}` : ''}</div>
                                            </div>
                                            <div className={`progress ${tone} mla`} style={{ width: 70 }}><span style={{ width: `${width}%` }} /></div>
                                            <a className="btn btn-outline btn-sm" href={`/pharmacist/inventory?q=${encodeURIComponent(row.name)}`}>Restock</a>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                        <p className="caption mt-m">Stock never goes below zero; the database CHECK and row locks enforce it. Currency: {settings.currencyCode}.</p>
                    </section>
                </div>
            </DataView>
        </RoleLayout>
    );
}