/**
 * Doctor · Prescription history (PLAN Sprint 7.3).
 * Server-scoped to the signed-in doctor; searchable by code/patient and
 * filterable by status. PDFs are the sealed record.
 */
import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { DataState, PageMeta, SharedProps } from '@/types';
import type { DoctorPrescriptionRow } from '@/types/doctor';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { DataTable, type DataColumn } from '@/Components/Data/DataTable';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { formatDate } from '@/lib/format';
import { useDebouncedValue } from '@/hooks/useUi';

interface PrescriptionRow extends DoctorPrescriptionRow {
    patientId: number;
    patientName: string | null;
    patientCode: string | null;
    pdfUrl: string;
}

interface PrescriptionsProps {
    prescriptions: PrescriptionRow[];
    meta: PageMeta;
    filters: { q: string; status: string };
    statuses: Array<{ value: string; label: string }>;
    dataState: DataState;
    lastKnownAt: string;
}

export default function DoctorPrescriptions({ prescriptions, meta, filters, statuses, dataState, lastKnownAt }: PrescriptionsProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(dataState);
    const [search, setSearch] = useState(filters.q);
    const debounced = useDebouncedValue(search, 350);

    const apply = (partial: Record<string, string> = {}, page = 1) => {
        const params: Record<string, string> = { q: filters.q, status: filters.status, ...partial };
        if (page > 1) params.page = String(page);
        Object.keys(params).forEach((key) => { if (!params[key]) delete params[key]; });
        router.get('/doctor/prescriptions', params, { preserveState: true, preserveScroll: true, replace: true });
    };

    useEffect(() => { if (debounced !== filters.q) apply({ q: debounced }); /* eslint-disable-next-line */ }, [debounced]);

    const columns: Array<DataColumn<PrescriptionRow>> = [
        { key: 'code', label: 'Prescription', render: (row) => <div><strong className="mono">{row.code}</strong><div className="caption">{formatDate(row.createdAt, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat, withTime: true })}</div></div> },
        { key: 'patient', label: 'Patient', render: (row) => <div><strong>{row.patientName ?? '—'}</strong><div className="caption mono">{row.patientCode}</div></div> },
        { key: 'items', label: 'Items', render: (row) => <span style={{ fontSize: 13 }}>{row.itemNames.join(' · ')}</span> },
        { key: 'status', label: 'Status', render: (row) => <div className="stack-sm" style={{ gap: 4 }}><StatusBadge family="prescription" status={row.status} label={row.statusLabel} size="sm" />{row.hasOverride ? <span className="badge badge-warning"><span className="dot" />override</span> : null}</div> },
    ];

    return (
        <RoleLayout
            role="doctor"
            title="Prescriptions"
            subtitle={`${meta.total} prescription(s) you wrote`}
            activeHref="/doctor/prescriptions"
            topbarActions={<a className="btn btn-primary" href="/doctor/prescriptions/new">+ New prescription</a>}
        >
            <Head title="Prescriptions — MediPath" />

            <form className="filter-bar" aria-label="Prescription filters" onSubmit={(event) => { event.preventDefault(); apply({ q: search }); }}>
                <div className="field" style={{ margin: 0 }}>
                    <label className="label" htmlFor="rx-history-q">Search</label>
                    <input id="rx-history-q" className="input" type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="prescription code, patient name or ID" />
                </div>
                <div className="field" style={{ margin: 0 }}>
                    <label className="label" htmlFor="rx-history-status">Status</label>
                    <select id="rx-history-status" className="select" value={filters.status} onChange={(event) => apply({ status: event.target.value })}>
                        <option value="">Any status</option>
                        {statuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}
                    </select>
                </div>
                <div className="f-actions f-full">
                    <span className="caption">{meta.total} result(s) · only prescriptions you wrote</span>
                    <button type="button" className="btn btn-ghost mla" onClick={() => { setSearch(''); router.get('/doctor/prescriptions', {}, { preserveState: true, preserveScroll: true, replace: true }); }}>Clear filters</button>
                </div>
            </form>

            <DataView
                name="Data view · Prescription history"
                state={state}
                onStateChange={setState}
                showSwitch
                onRetry={() => router.reload()}
                lastKnownAt={lastKnownAt}
                emptyTitle="No prescriptions match"
                emptyBody="Write a prescription from a patient chart or clear the filters."
                errorMessage="Prescription history failed to load. Safety records remain stored."
            >
                <DataTable
                    className="tbl-cards-md"
                    caption="Prescriptions you wrote"
                    columns={columns}
                    rows={prescriptions}
                    getRowKey={(row) => row.id}
                    page={meta}
                    onPageChange={(page) => apply({}, page)}
                    actions={(row) => (
                        <div className="mp-row">
                            <a className="btn btn-outline btn-sm" href={row.pdfUrl}>⬇ PDF</a>
                            <a className="btn btn-ghost btn-sm" href={`/doctor/patients/${row.patientId}?tab=prescriptions`}>Chart</a>
                        </div>
                    )}
                />
            </DataView>
        </RoleLayout>
    );
}