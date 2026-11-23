/**
 * Doctor · My patients — prototypes/doctor/04-patients.html.
 */
import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { DataState, PageMeta, SharedProps } from '@/types';
import type { DoctorPatientRow } from '@/types/doctor';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { DataTable, type DataColumn } from '@/Components/Data/DataTable';
import { formatDate } from '@/lib/format';
import { useDebouncedValue } from '@/hooks/useUi';

interface PatientsProps {
    patients: DoctorPatientRow[];
    meta: PageMeta;
    filters: { q: string; last_visit: string; allergies: boolean };
    dataState: DataState;
    lastKnownAt: string;
}

export default function DoctorPatients({ patients, meta, filters, dataState, lastKnownAt }: PatientsProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(dataState);
    const [search, setSearch] = useState(filters.q);
    const debounced = useDebouncedValue(search, 350);

    const apply = (partial: Record<string, string | boolean> = {}, page = 1) => {
        const params: Record<string, string> = { q: filters.q, last_visit: filters.last_visit, allergies: filters.allergies ? '1' : '', ...Object.fromEntries(Object.entries(partial).map(([k, v]) => [k, String(v)])) };
        if (page > 1) params.page = String(page);
        Object.keys(params).forEach((k) => { if (params[k] === '' || params[k] === 'false') delete params[k]; });
        router.get('/doctor/patients', params, { preserveState: true, preserveScroll: true, replace: true });
    };

    useEffect(() => { if (debounced !== filters.q) apply({ q: debounced }); /* eslint-disable-next-line */ }, [debounced]);

    const columns: Array<DataColumn<DoctorPatientRow>> = [
        { key: 'patient', label: 'Patient', render: (row) => <div className="avatar-note"><span className="avatar avatar-initials" style={{ width: 32, height: 32, fontSize: 11 }} aria-hidden="true">{row.initials}</span><div><strong>{row.name}</strong><div className="caption">{row.condition}</div></div></div> },
        { key: 'id', label: 'ID', render: (row) => <span className="mono">{row.code}</span> },
        { key: 'age', label: 'Age / gender', render: (row) => <span className="tabular">{row.age ?? '—'} · {row.gender ?? '—'}</span> },
        { key: 'last', label: 'Last visit', render: (row) => <span className="tabular">{formatDate(row.lastVisit, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })}</span> },
        { key: 'safety', label: 'Safety flags', render: (row) => row.allergyCount === 0 ? <span className="badge badge-neutral">none recorded</span> : <div className="stack-sm" style={{ gap: 4 }}><span className={row.severeAllergyCount > 0 ? 'badge badge-danger' : 'badge badge-warning'}><span className="dot" />{row.allergyCount} allergy(s)</span><span className="caption">{row.allergySubstances.join(', ')}</span></div> },
        { key: 'next', label: 'Next', render: (row) => <span className="tabular">{row.nextVisit ?? '—'}</span> },
    ];

    return (
        <RoleLayout role="doctor" title="My patients" subtitle={`${meta.total} patient(s) you have treated`} activeHref="/doctor/patients" topbarActions={<a className="btn btn-outline" href="/doctor/walk-in">+ Walk-in registration</a>}>
            <Head title="My patients — MediPath" />
            <form className="filter-bar" aria-label="Patient filters" onSubmit={(event) => { event.preventDefault(); apply({ q: search }); }}>
                <div className="field" style={{ margin: 0 }}><label className="label" htmlFor="doc-patient-q">Search</label><input id="doc-patient-q" className="input" type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="name or patient ID" /></div>
                <div className="field" style={{ margin: 0 }}><label className="label" htmlFor="doc-patient-last">Last visit</label><select id="doc-patient-last" className="select" value={filters.last_visit} onChange={(event) => apply({ last_visit: event.target.value })}><option value="any">Any time</option><option value="30">Last 30 days</option><option value="7">Last 7 days</option></select></div>
                <label className="switch"><input type="checkbox" checked={filters.allergies} onChange={(event) => apply({ allergies: event.target.checked ? '1' : '' })} /><span>Has allergy flags</span></label>
                <div className="f-actions f-full"><span className="caption">{meta.total} result(s) · access scoped to patients you treated</span><button type="button" className="btn btn-ghost mla" onClick={() => { setSearch(''); router.get('/doctor/patients', {}, { preserveState: true, preserveScroll: true, replace: true }); }}>Clear filters</button></div>
            </form>
            <DataView name="Data view · My patients" state={state} onStateChange={setState} showSwitch onRetry={() => router.reload()} lastKnownAt={lastKnownAt}
                emptyTitle="No patients match" emptyBody="Clear a filter or register today's walk-in to create the first chart."
                errorMessage="Patient list failed to load.">
                <DataTable className="tbl-cards-md" caption="Patients you have treated" columns={columns} rows={patients} getRowKey={(row) => row.id} page={meta} onPageChange={(page) => apply({}, page)}
                    actions={(row) => <div className="mp-row"><a className="btn btn-outline btn-sm" href={`/doctor/patients/${row.id}`}>Open chart</a><a className="btn btn-ghost btn-sm" href={`/doctor/prescriptions/new?patient=${row.id}`}>Prescribe</a></div>} />
            </DataView>
        </RoleLayout>
    );
}