/** Pharmacist · Alerts console — prototypes/pharmacist/06-alerts.html. */
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { DataState, SharedProps } from '@/types';
import type { PharmacyStockRow } from '@/types/pharmacy';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { Money } from '@/Components/ui/Money';
import { formatDate } from '@/lib/format';

interface AlertsProps {
    tiers: { stock: PharmacyStockRow[]; expired: PharmacyStockRow[]; critical: PharmacyStockRow[]; upcoming: PharmacyStockRow[] };
    counts: { stock: number; expired: number; critical: number; upcoming: number };
    dataState: DataState;
    lastKnownAt: string;
}

type Tier = 'stock' | 'expired' | 'critical' | 'upcoming';

export default function PharmacistAlerts({ tiers, counts, dataState, lastKnownAt }: AlertsProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(dataState);
    const [tab, setTab] = useState<Tier>('stock');
    const rows = tiers[tab];

    const restockHref = (row: PharmacyStockRow) => `/pharmacist/inventory?q=${encodeURIComponent(row.name)}`;

    return (
        <RoleLayout role="pharmacist" title="Alerts console" subtitle="Stock and expiry tiers · expiry is recalculated daily from batch dates" activeHref="/pharmacist/alerts" topbarActions={<a className="btn btn-outline" href="/pharmacist/inventory">Open inventory</a>}>
            <Head title="Alerts console — MediPath" />

            <DataView
                name="Data view · Alert tiers"
                state={state}
                onStateChange={setState}
                showSwitch
                onRetry={() => router.reload()}
                lastKnownAt={lastKnownAt}
                emptyTitle="All clear"
                emptyBody="No stock or expiry alerts at the latest sweep."
                errorMessage="Last sweep failed. Timestamps show the last known results — never stale data presented as fresh."
            >
                <div className="tabs" role="tablist" aria-label="Alert tier">
                    {(['stock', 'expired', 'critical', 'upcoming'] as Tier[]).map((tier) => (
                        <button key={tier} type="button" role="tab" aria-selected={tab === tier} onClick={() => setTab(tier)}>
                            {tier === 'stock' ? 'Stock' : tier === 'expired' ? 'Expired' : tier === 'critical' ? 'Critical ≤30d' : 'Upcoming 31–90d'}
                            <span className="cnt tabular">{counts[tier]}</span>
                        </button>
                    ))}
                </div>

                {rows.length === 0 ? (
                    <div className="empty mt-m" role="status">
                        <span className="glyph" aria-hidden="true">🎉</span>
                        <p>{tab === 'stock' ? 'No stock is below its threshold.' : 'No batches in this expiry window.'}</p>
                    </div>
                ) : tab === 'expired' ? (
                    <div className="tbl-wrap mt-m">
                        <table className="tbl">
                            <caption className="sr-only">Expired batches</caption>
                            <thead><tr><th>Medicine · batch</th><th>Expired</th><th>Qty</th><th>Stock value</th><th>Action</th></tr></thead>
                            <tbody>
                                {rows.map((row) => (
                                    <tr key={row.id}>
                                        <td data-label="Medicine"><strong>{row.name}</strong> <span className="mono caption">{row.batch ?? '—'}</span></td>
                                        <td data-label="Expired" className="tabular" style={{ color: 'var(--mp-danger-text)' }}>{formatDate(row.expiryDate, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })}</td>
                                        <td data-label="Qty" className="num">{row.stock}</td>
                                        <td data-label="Value" className="num"><Money formatted={row.valueFormatted ?? '—'} size="sm" /></td>
                                        <td data-label="Action"><a className="btn btn-outline btn-sm" href={restockHref(row)}>Replace batch</a></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <p className="caption" style={{ padding: '12px 16px' }}>Expired batches are excluded from the doctor's live stock search. Replacing the batch writes a restock ledger entry — never a silent deletion.</p>
                    </div>
                ) : (
                    <ul className="list-quiet mt-m" style={{ fontSize: 14 }}>
                        {rows.map((row) => (
                            <li key={row.id}>
                                <div style={{ minWidth: 0 }}>
                                    <strong>{row.name}</strong> <StatusBadge family={tab === 'stock' ? 'stock' : 'expiry'} status={tab === 'stock' ? row.status : row.expiryStatus} size="sm" />
                                    <div className="caption">{tab === 'stock' ? `${row.stock} available · threshold ${row.threshold}` : `${row.batch ? `batch ${row.batch} · ` : ''}exp ${formatDate(row.expiryDate, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })} · qty ${row.stock}`}</div>
                                </div>
                                <a className={`btn ${tab === 'stock' && (row.status === 'critical' || row.status === 'out_of_stock') ? 'btn-primary' : 'btn-outline'} btn-sm mla`} href={restockHref(row)}>Restock</a>
                            </li>
                        ))}
                    </ul>
                )}
            </DataView>
        </RoleLayout>
    );
}