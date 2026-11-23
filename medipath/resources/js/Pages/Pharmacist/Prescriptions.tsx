/** Pharmacist · Pending prescriptions — prototypes/pharmacist/02-pending-rx.html. */
import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { DataState, PageMeta } from '@/types';
import type { PharmacyQueueRow } from '@/types/pharmacy';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { DataTable, type DataColumn } from '@/Components/Data/DataTable';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { useDebouncedValue } from '@/hooks/useUi';

interface PrescriptionsProps {
    prescriptions: PharmacyQueueRow[];
    meta: PageMeta;
    filters: { q: string; stock: string; sort: string };
    counts: { pending: number; oldestHours: number | null };
    dataState: DataState;
    lastKnownAt: string;
}

function stockBadge(state: PharmacyQueueRow['stockState']): { status: string; label: string } {
    if (state === 'all_in_stock') return { status: 'healthy', label: 'All in stock' };
    if (state === 'partial') return { status: 'critical', label: 'Partial stock' };
    return { status: 'out_of_stock', label: 'Out of stock' };
}

export default function PharmacistPrescriptions({ prescriptions, meta, filters, counts, dataState, lastKnownAt }: PrescriptionsProps) {
    const [state, setState] = useState<DataState>(dataState);
    const [search, setSearch] = useState(filters.q);
    const debounced = useDebouncedValue(search, 350);

    const apply = (partial: Record<string, string> = {}, page = 1) => {
        const params: Record<string, string> = {
            q: filters.q, stock: filters.stock, sort: filters.sort, ...partial,
        };
        if (page > 1) params.page = String(page);
        Object.keys(params).forEach((key) => {
            if (!params[key] || (key === 'stock' && params[key] === 'any') || (key === 'sort' && params[key] === 'oldest')) delete params[key];
        });
        router.get('/pharmacist/prescriptions', params, { preserveState: true, preserveScroll: true, replace: true });
    };

    useEffect(() => { if (debounced !== filters.q) apply({ q: debounced }); /* eslint-disable-next-line */ }, [debounced]);

    const columns: Array<DataColumn<PharmacyQueueRow>> = [
        { key: 'code', label: 'Rx', render: (row) => <div><strong className="mono">{row.code}</strong>{row.hasOverride ? <div className="caption" style={{ color: 'var(--mp-warning-text)' }}>sealed override</div> : null}</div> },
        { key: 'patient', label: 'Patient', render: (row) => <div><strong>{row.patientName}</strong><div className="caption mono">{row.patientCode}</div></div> },
        { key: 'doctor', label: 'Doctor', render: (row) => <span>{row.doctorName}</span> },
        { key: 'items', label: 'Items', render: (row) => <span style={{ fontSize: 13 }}>{(row.itemNames ?? []).join(' · ') || `${row.items} item(s)`}</span> },
        { key: 'stock', label: 'Stock check', render: (row) => { const badge = stockBadge(row.stockState); return <StatusBadge family="stock" status={badge.status} label={badge.label} size="sm" />; } },
        { key: 'waiting', label: 'Waiting', render: (row) => <span className="tabular">{row.waitingHours}h</span> },
    ];

    return (
        <RoleLayout
            role="pharmacist"
            title="Pending prescriptions"
            subtitle={`${counts.pending} waiting${counts.oldestHours === null ? '' : ` · oldest ${counts.oldestHours}h`}`}
            activeHref="/pharmacist/prescriptions"
            topbarActions={<a className="btn btn-outline" href="/pharmacist/alerts">Stock alerts</a>}
        >
            <Head title="Pending prescriptions — MediPath" />

            <form className="filter-bar" aria-label="Pending prescription filters" onSubmit={(event) => { event.preventDefault(); apply({ q: search }); }}>
                <div className="field" style={{ margin: 0 }}>
                    <label className="label" htmlFor="ph-q">Search</label>
                    <input id="ph-q" className="input" type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Rx ID, patient ID or name" />
                </div>
                <div className="field" style={{ margin: 0 }}>
                    <label className="label" htmlFor="ph-stock">Stock status</label>
                    <select id="ph-stock" className="select" value={filters.stock} onChange={(event) => apply({ stock: event.target.value })}>
                        <option value="any">Any</option>
                        <option value="partial">Partial / missing</option>
                        <option value="all_in_stock">All in stock</option>
                        <option value="out_of_stock">Out of stock</option>
                    </select>
                </div>
                <div className="field" style={{ margin: 0 }}>
                    <label className="label" htmlFor="ph-sort">Sort</label>
                    <select id="ph-sort" className="select" value={filters.sort} onChange={(event) => apply({ sort: event.target.value })}>
                        <option value="oldest">Oldest first</option>
                        <option value="newest">Newest first</option>
                    </select>
                </div>
                <div className="f-actions f-full">
                    <span className="caption">{meta.total} result(s) · safety re-runs at the counter</span>
                    <button type="button" className="btn btn-ghost mla" onClick={() => { setSearch(''); router.get('/pharmacist/prescriptions', {}, { preserveState: true, preserveScroll: true, replace: true }); }}>Clear filters</button>
                </div>
            </form>

            <DataView
                name="Data view · Pending prescriptions"
                state={state}
                onStateChange={setState}
                showSwitch
                onRetry={() => router.reload()}
                lastKnownAt={lastKnownAt}
                emptyTitle="Queue empty"
                emptyBody="Nothing awaits dispensing — new prescriptions arrive the moment a doctor saves them."
                errorMessage="Pending queue failed to load. Do not dispense from stale data."
            >
                <DataTable
                    className="tbl-cards-md"
                    caption="Pending prescriptions"
                    columns={columns}
                    rows={prescriptions}
                    getRowKey={(row) => row.id}
                    page={meta}
                    onPageChange={(page) => apply({}, page)}
                    actions={(row) => <a className="btn btn-primary btn-sm" href={`/pharmacist/prescriptions/${row.id}`}>Review &amp; dispense</a>}
                />
            </DataView>
        </RoleLayout>
    );
}