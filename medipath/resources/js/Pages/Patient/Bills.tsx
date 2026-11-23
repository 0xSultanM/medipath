/**
 * Patient · My bills — prototypes/patient/06-bills.html.
 * Every query is scoped to the patient id; search covers invoice code and
 * dispensed items, and PDFs use the shared invoice template.
 */
import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { DataState, PageMeta, SharedProps } from '@/types';
import type { PatientInvoice } from '@/types/patient';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { DataTable, type DataColumn } from '@/Components/Data/DataTable';
import { KpiCard } from '@/Components/Data/KpiCard';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { Money } from '@/Components/ui/Money';
import { formatDate } from '@/lib/format';
import { useDebouncedValue } from '@/hooks/useUi';

interface BillsProps {
    invoices: PatientInvoice[];
    meta: PageMeta;
    filters: { q: string; status: string };
    kpis: { outstandingFormatted: string; outstandingCount: number; paidFormatted: string; paidCount: number; refundedFormatted: string; refundedCount: number };
    statuses: Array<{ value: string; label: string }>;
    dataState: DataState;
    lastKnownAt: string;
}

export default function PatientBills({ invoices, meta, filters, kpis, statuses, dataState, lastKnownAt }: BillsProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(dataState);
    const [search, setSearch] = useState(filters.q);
    const debounced = useDebouncedValue(search, 350);

    const apply = (partial: Record<string, string> = {}, page = 1) => {
        const params: Record<string, string> = { q: filters.q, status: filters.status, ...partial };
        if (page > 1) params.page = String(page);
        Object.keys(params).forEach((key) => { if (params[key] === '') delete params[key]; });
        router.get('/patient/bills', params, { preserveState: true, preserveScroll: true, replace: true });
    };

    useEffect(() => { if (debounced !== filters.q) apply({ q: debounced }); /* eslint-disable-next-line */ }, [debounced]);

    const columns: Array<DataColumn<PatientInvoice>> = [
        { key: 'invoice', label: 'Invoice', render: (row) => <span className="mono">{row.code}</span> },
        { key: 'date', label: 'Date', render: (row) => <span className="tabular">{formatDate(row.createdAt, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })}</span> },
        { key: 'items', label: 'Items', render: (row) => <span className="caption">{row.items.join(', ') || '—'}</span> },
        { key: 'net', label: 'Net total', align: 'right', render: (row) => <Money formatted={row.netFormatted} size="sm" /> },
        { key: 'method', label: 'Method', render: (row) => row.method ?? '—' },
        { key: 'status', label: 'Status', render: (row) => <StatusBadge family="payment" status={row.status} label={row.statusLabel} /> },
    ];

    return (
        <RoleLayout role="patient" title="My bills" subtitle="scoped to your patient id — never to a name" activeHref="/patient/bills">
            <Head title="My bills — MediPath" />

            <div className="grid">
                <div className="c-12 sm-c-4"><KpiCard label="Outstanding" money={kpis.outstandingFormatted} tone={kpis.outstandingCount > 0 ? 'warning' : 'success'} trend="down" delta={`${kpis.outstandingCount} unpaid invoice(s)`} /></div>
                <div className="c-12 sm-c-4"><KpiCard label="Paid" money={kpis.paidFormatted} tone="success" trend="up" delta={`${kpis.paidCount} invoices`} /></div>
                <div className="c-12 sm-c-4"><KpiCard label="Refunded" money={kpis.refundedFormatted} trend="flat" delta={`${kpis.refundedCount} refund(s)`} /></div>
            </div>

            <form className="filter-bar mt-l" aria-label="Find an invoice" onSubmit={(event) => { event.preventDefault(); apply({ q: search }); }}>
                <div className="field" style={{ margin: 0 }}>
                    <label className="label" htmlFor="bill-q">Search by invoice ID or medicine</label>
                    <input id="bill-q" className="input mono" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="e.g., INV-2026-0139" />
                </div>
                <div className="field" style={{ margin: 0 }}>
                    <label className="label" htmlFor="bill-status">Payment status</label>
                    <select id="bill-status" className="select" value={filters.status} onChange={(event) => apply({ status: event.target.value })}>
                        <option value="">Any</option>
                        {statuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}
                    </select>
                </div>
                <div className="f-actions f-full"><span className="caption">Results are scoped server-side to your patient ID.</span><button type="button" className="btn btn-ghost mla" onClick={() => { setSearch(''); router.get('/patient/bills', {}, { preserveState: true, preserveScroll: true, replace: true }); }}>Clear</button></div>
            </form>

            <DataView
                name="Data view · Invoices"
                state={state}
                onStateChange={setState}
                showSwitch
                onRetry={() => router.reload()}
                lastKnownAt={lastKnownAt}
                emptyTitle="No invoices found"
                emptyBody="Invoices appear the moment a pharmacist dispenses and bills a prescription."
                errorMessage="Billing list failed to load. Your search term is kept."
            >
                <DataTable
                    className="tbl-cards-md"
                    caption="Your invoices"
                    columns={columns}
                    rows={invoices}
                    getRowKey={(row) => row.id}
                    page={meta}
                    onPageChange={(page) => apply({}, page)}
                    actions={(row) => <a className="btn btn-outline btn-sm" href={row.pdfUrl}>⬇ PDF</a>}
                />
            </DataView>
            <p className="caption mt-m">Amounts use the configured clinic currency; PDFs are generated server-side from the shared template.</p>
        </RoleLayout>
    );
}