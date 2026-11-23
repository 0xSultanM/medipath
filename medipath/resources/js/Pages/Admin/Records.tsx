/**
 * Admin · Clinical & billing records — prototypes/admin/07-records.html.
 * Read-only oversight across prescriptions, invoices and live inventory, with
 * the shared server-rendered PDFs. Historic money uses currency snapshots.
 */
import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { DataState, PageMeta } from '@/types';
import type { AdminInventoryRow, AdminInvoiceRow, AdminPrescriptionRow } from '@/types/admin';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { DataTable, type DataColumn } from '@/Components/Data/DataTable';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { Money } from '@/Components/ui/Money';
import { useDebouncedValue } from '@/hooks/useUi';

interface RecordsProps {
    tab: 'prescriptions' | 'invoices' | 'inventory';
    items: unknown[];
    meta: PageMeta;
    filters: { q: string; status: string };
    counts: { prescriptions: number; invoices: number; inventory: number };
    statuses: Array<{ value: string; label: string }>;
    dataState: DataState;
    lastKnownAt: string;
}

export default function AdminRecords({ tab, items, meta, filters, counts, statuses, dataState, lastKnownAt }: RecordsProps) {
    const [state, setState] = useState<DataState>(dataState);
    const [search, setSearch] = useState(filters.q);
    const debounced = useDebouncedValue(search, 350);

    const apply = (nextTab: string = tab, partial: Record<string, string> = {}, page = 1) => {
        const params: Record<string, string> = { tab: nextTab, q: filters.q, status: filters.status, ...partial };
        if (page > 1) params.page = String(page);
        Object.keys(params).forEach((key) => { if (params[key] === '') delete params[key]; });
        router.get('/admin/records', params, { preserveState: true, preserveScroll: true, replace: true });
    };

    useEffect(() => { if (debounced !== filters.q) apply(tab, { q: debounced }); /* eslint-disable-next-line */ }, [debounced]);

    const rxColumns: Array<DataColumn<AdminPrescriptionRow>> = [
        { key: 'rx', label: 'Rx', render: (row) => <span className="mono">{row.code}</span> },
        { key: 'patient', label: 'Patient', render: (row) => <div><strong>{row.patientName}</strong><div className="caption mono">{row.patientCode}</div></div> },
        { key: 'doctor', label: 'Doctor', render: (row) => row.doctorName ?? '—' },
        { key: 'items', label: 'Items', align: 'right', render: (row) => <span className="num tabular">{row.items}</span> },
        {
            key: 'safety',
            label: 'Safety',
            render: (row) => row.safety === 'override'
                ? <span className="badge badge-warning"><span className="dot" />{row.safetyLabel}</span>
                : <span className="badge badge-success"><span className="dot" />clean</span>,
        },
        { key: 'status', label: 'Status', render: (row) => <StatusBadge family="prescription" status={row.status} label={row.statusLabel} /> },
        { key: 'pdf', label: 'PDF', render: (row) => <a className="btn btn-outline btn-sm" href={row.pdfUrl}>⬇ PDF</a> },
    ];

    const invoiceColumns: Array<DataColumn<AdminInvoiceRow>> = [
        { key: 'invoice', label: 'Invoice', render: (row) => <span className="mono">{row.code}</span> },
        { key: 'patient', label: 'Patient', render: (row) => <div><strong>{row.patientName}</strong><div className="caption mono">{row.patientCode}</div></div> },
        { key: 'net', label: 'Net', align: 'right', render: (row) => <Money formatted={row.netFormatted} size="sm" /> },
        { key: 'status', label: 'Status', render: (row) => <StatusBadge family="payment" status={row.status} label={row.statusLabel} /> },
        { key: 'pdf', label: 'PDF', render: (row) => <a className="btn btn-outline btn-sm" href={row.pdfUrl}>⬇ PDF</a> },
    ];

    const inventoryColumns: Array<DataColumn<AdminInventoryRow>> = [
        { key: 'medicine', label: 'Medicine', render: (row) => <div><strong>{row.name}</strong><div className="caption">{row.genericName} {row.strength}</div></div> },
        { key: 'batch', label: 'Batch', render: (row) => <span className="mono">{row.batch}</span> },
        { key: 'stock', label: 'Stock', align: 'right', render: (row) => <span className="num tabular">{row.stock}</span> },
        { key: 'stockStatus', label: 'Stock status', render: (row) => <StatusBadge family="stock" status={row.stockStatus} /> },
        { key: 'expiry', label: 'Expiry', render: (row) => <StatusBadge family="expiry" status={row.expiryStatus} /> },
        { key: 'price', label: 'Unit price', align: 'right', render: (row) => <Money formatted={row.priceFormatted} size="sm" /> },
    ];

    return (
        <RoleLayout
            role="admin"
            title="Clinical & billing records"
            subtitle="read-only oversight · shared PDF templates power every role's download"
            activeHref="/admin/records"
        >
            <Head title="Clinical & billing records — MediPath" />

            <DataView
                name="Data view · Records"
                state={state}
                onStateChange={setState}
                showSwitch
                onRetry={() => router.reload()}
                lastKnownAt={lastKnownAt}
                emptyTitle="No records for the selected filters"
                emptyBody="Clear the search or status filter. Clinical records are never deleted by this screen."
                errorMessage="Records failed to load. Nothing was modified; retry is safe."
            >
                <div className="tabs" role="tablist" aria-label="Record types">
                    <button type="button" role="tab" aria-selected={tab === 'prescriptions'} onClick={() => apply('prescriptions')}>
                        Clinical (prescriptions) <span className="cnt tabular">{counts.prescriptions}</span>
                    </button>
                    <button type="button" role="tab" aria-selected={tab === 'invoices'} onClick={() => apply('invoices')}>
                        Billing (invoices) <span className="cnt tabular">{counts.invoices}</span>
                    </button>
                    <button type="button" role="tab" aria-selected={tab === 'inventory'} onClick={() => apply('inventory')}>
                        Inventory <span className="cnt tabular">{counts.inventory}</span>
                    </button>
                </div>

                <form className="filter-bar mt-m" aria-label="Record filters" onSubmit={(event) => { event.preventDefault(); apply(tab, { q: search }); }}>
                    <div className="field" style={{ margin: 0 }}>
                        <label className="label" htmlFor="record-q">Search</label>
                        <input id="record-q" className="input" type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder={tab === 'inventory' ? 'medicine, generic, batch' : 'code, patient'} />
                    </div>
                    {tab !== 'inventory' ? (
                        <div className="field" style={{ margin: 0 }}>
                            <label className="label" htmlFor="record-status">Status</label>
                            <select id="record-status" className="select" value={filters.status} onChange={(event) => apply(tab, { status: event.target.value })}>
                                <option value="">Any status</option>
                                {statuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}
                            </select>
                        </div>
                    ) : null}
                    <div className="f-actions f-full">
                        <span className="caption">{meta.total} record{meta.total === 1 ? '' : 's'} · read-only</span>
                        <button type="button" className="btn btn-ghost mla" onClick={() => { setSearch(''); router.get('/admin/records', { tab }, { preserveState: true, preserveScroll: true, replace: true }); }}>
                            Clear filters
                        </button>
                    </div>
                </form>

                <div className="mt-m">
                    {tab === 'prescriptions' ? (
                        <DataTable
                            className="tbl-cards-md"
                            caption="Prescription records"
                            columns={rxColumns}
                            rows={items as AdminPrescriptionRow[]}
                            getRowKey={(row) => row.id}
                            page={meta}
                            onPageChange={(page) => apply(tab, {}, page)}
                        />
                    ) : tab === 'invoices' ? (
                        <DataTable
                            className="tbl-cards-md"
                            caption="Invoice records"
                            columns={invoiceColumns}
                            rows={items as AdminInvoiceRow[]}
                            getRowKey={(row) => row.id}
                            page={meta}
                            onPageChange={(page) => apply(tab, {}, page)}
                        />
                    ) : (
                        <DataTable
                            className="tbl-cards-md"
                            caption="Live medicine inventory"
                            columns={inventoryColumns}
                            rows={items as AdminInventoryRow[]}
                            getRowKey={(row) => row.id}
                            page={meta}
                            onPageChange={(page) => apply(tab, {}, page)}
                        />
                    )}
                </div>
            </DataView>
        </RoleLayout>
    );
}