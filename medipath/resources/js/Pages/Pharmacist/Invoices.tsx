/** Pharmacist · Invoices — prototypes/pharmacist/07-invoices.html. */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { DataState, PageMeta, SharedProps } from '@/types';
import type { PharmacyInvoiceRow } from '@/types/pharmacy';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { DataTable, type DataColumn } from '@/Components/Data/DataTable';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { AdminModal } from '@/Components/Admin/AdminModal';
import { Money } from '@/Components/ui/Money';
import { formatDate } from '@/lib/format';
import { useDebouncedValue } from '@/hooks/useUi';

interface InvoicesProps {
    invoices: PharmacyInvoiceRow[];
    meta: PageMeta;
    filters: { q: string; status: string; method: string };
    statuses: Array<{ value: string; label: string }>;
    methods: Array<{ value: string; label: string }>;
    summary: { pending: number; paid: number; refunded: number; todayFormatted: string; todayCount: number };
    dataState: DataState;
    lastKnownAt: string;
}

function StatusModal({ invoice, statuses, onClose }: { invoice: PharmacyInvoiceRow | null; statuses: Array<{ value: string; label: string }>; onClose: () => void }) {
    const form = useForm({ payment_status: 'paid', refund_reason: '' });

    useEffect(() => {
        if (invoice) form.setData({ payment_status: 'paid', refund_reason: '' });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [invoice?.id]);

    const submit = () => {
        if (!invoice) return;
        form.post(`/pharmacist/invoices/${invoice.id}/status`, { preserveScroll: true, onSuccess: () => onClose() });
    };

    return (
        <AdminModal
            open={Boolean(invoice)}
            title={`Payment status — ${invoice?.code ?? ''}`}
            subtitle={invoice ? `${invoice.patientName} · net ${invoice.netFormatted} · currently ${invoice.statusLabel.toLowerCase()}` : ''}
            icon="💳"
            onClose={onClose}
            footer={<><button type="button" className="btn btn-quiet" onClick={onClose}>Cancel</button><button type="button" className="btn btn-primary" disabled={form.processing} onClick={submit}>{form.processing ? 'Updating…' : 'Update status'}</button></>}
        >
            <div className="stack-sm">
                {['paid', 'refunded'].map((value) => (
                    <label className="check" key={value}>
                        <input type="radio" name="payment_status" checked={form.data.payment_status === value} onChange={() => form.setData('payment_status', value)} />
                        <span>{statuses.find((status) => status.value === value)?.label ?? value}{value === 'paid' ? ' (marks paid_at)' : ''}</span>
                    </label>
                ))}
                {form.data.payment_status === 'refunded' ? (
                    <div className="field">
                        <label className="label req" htmlFor="inv-refund">Refund reason (required, ≥10 characters)</label>
                        <textarea id="inv-refund" className="textarea" rows={3} value={form.data.refund_reason} onChange={(event) => form.setData('refund_reason', event.target.value)} placeholder="e.g., duplicate charge confirmed with admin" />
                        {form.errors.refund_reason ? <span className="field-error">{form.errors.refund_reason}</span> : null}
                    </div>
                ) : null}
                <p className="caption">Refunds are visible to admin oversight; stock reversal remains a separate explicit ledger action.</p>
            </div>
        </AdminModal>
    );
}

export default function PharmacistInvoices({ invoices, meta, filters, statuses, methods, summary, dataState, lastKnownAt }: InvoicesProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(dataState);
    const [search, setSearch] = useState(filters.q);
    const debounced = useDebouncedValue(search, 350);
    const [statusInvoice, setStatusInvoice] = useState<PharmacyInvoiceRow | null>(null);

    const apply = (partial: Record<string, string> = {}, page = 1) => {
        const params: Record<string, string> = { q: filters.q, status: filters.status, method: filters.method, ...partial };
        if (page > 1) params.page = String(page);
        Object.keys(params).forEach((key) => { if (!params[key]) delete params[key]; });
        router.get('/pharmacist/invoices', params, { preserveState: true, preserveScroll: true, replace: true });
    };

    useEffect(() => { if (debounced !== filters.q) apply({ q: debounced }); /* eslint-disable-next-line */ }, [debounced]);

    const columns: Array<DataColumn<PharmacyInvoiceRow>> = [
        { key: 'code', label: 'Invoice', render: (row) => <span className="mono">{row.code}</span> },
        { key: 'patient', label: 'Patient', render: (row) => <div><strong>{row.patientName}</strong><div className="caption mono">{row.patientCode}</div></div> },
        { key: 'rx', label: 'Rx', render: (row) => <span className="mono">{row.prescriptionCode ?? '—'}</span> },
        { key: 'date', label: 'Date', render: (row) => <span className="tabular">{formatDate(row.createdAt, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })}</span> },
        { key: 'net', label: 'Net total', align: 'right', render: (row) => <Money formatted={row.netFormatted} size="sm" /> },
        { key: 'method', label: 'Method', render: (row) => row.methodLabel },
        { key: 'status', label: 'Status', render: (row) => <StatusBadge family="payment" status={row.status} label={row.statusLabel} size="sm" /> },
    ];

    return (
        <RoleLayout role="pharmacist" title="Invoices" subtitle={`${meta.total} invoice(s) · today ${summary.todayFormatted}`} activeHref="/pharmacist/invoices">
            <Head title="Invoices — MediPath" />

            <div className="grid tight">
                <div className="kpi c-6 sm-c-3"><span className="k-label">Pending</span><span className="k-value tabular">{summary.pending}</span></div>
                <div className="kpi c-6 sm-c-3"><span className="k-label">Paid</span><span className="k-value tabular">{summary.paid}</span></div>
                <div className="kpi c-6 sm-c-3"><span className="k-label">Refunded</span><span className="k-value tabular">{summary.refunded}</span></div>
                <div className="kpi c-6 sm-c-3"><span className="k-label">Billed today</span><span className="k-value"><Money formatted={summary.todayFormatted} size="kpi" /></span><span className="k-foot"><span className="k-delta up">{summary.todayCount} invoice(s)</span></span></div>
            </div>

            <form className="filter-bar mt-l" aria-label="Invoice filters" onSubmit={(event) => { event.preventDefault(); apply({ q: search }); }}>
                <div className="field" style={{ margin: 0 }}><label className="label" htmlFor="inv-q">Search</label><input id="inv-q" className="input" type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="invoice, patient or Rx" /></div>
                <div className="field" style={{ margin: 0 }}><label className="label" htmlFor="inv-status">Status</label><select id="inv-status" className="select" value={filters.status} onChange={(event) => apply({ status: event.target.value })}><option value="">Any status</option>{statuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}</select></div>
                <div className="field" style={{ margin: 0 }}><label className="label" htmlFor="inv-method">Method</label><select id="inv-method" className="select" value={filters.method} onChange={(event) => apply({ method: event.target.value })}><option value="">Any method</option>{methods.map((method) => <option key={method.value} value={method.value}>{method.label}</option>)}</select></div>
                <div className="f-actions f-full"><span className="caption">{meta.total} result(s) · currency snapshot per invoice</span><button type="button" className="btn btn-ghost mla" onClick={() => { setSearch(''); router.get('/pharmacist/invoices', {}, { preserveState: true, preserveScroll: true, replace: true }); }}>Clear filters</button></div>
            </form>

            <DataView name="Data view · Invoices" state={state} onStateChange={setState} showSwitch onRetry={() => router.reload()} lastKnownAt={lastKnownAt} emptyTitle="No invoices match" emptyBody="Every completed dispense generates one here automatically." errorMessage="Invoice list failed to load.">
                <DataTable
                    className="tbl-cards-md"
                    caption="Pharmacy invoices"
                    columns={columns}
                    rows={invoices}
                    getRowKey={(row) => row.id}
                    page={meta}
                    onPageChange={(page) => apply({}, page)}
                    actions={(row) => (
                        <div className="mp-row">
                            <button type="button" className="btn btn-ghost btn-sm" onClick={() => setStatusInvoice(row)}>Status…</button>
                            <a className="btn btn-outline btn-sm" href={row.pdfUrl}>⬇ PDF</a>
                        </div>
                    )}
                />
            </DataView>

            <StatusModal invoice={statusInvoice} statuses={statuses} onClose={() => setStatusInvoice(null)} />
        </RoleLayout>
    );
}