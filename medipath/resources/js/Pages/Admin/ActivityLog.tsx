/**
 * Admin · Activity log — prototypes/admin/09-activity.html.
 * Immutable, append-only audit viewer. Filters are server-side; detail JSON is
 * shown read-only and there are no edit/delete code paths for a log row.
 */
import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { DataState, PageMeta, SharedProps } from '@/types';
import type { AdminActivityEntry } from '@/types/admin';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { DataTable, type DataColumn } from '@/Components/Data/DataTable';
import { AdminModal } from '@/Components/Admin/AdminModal';
import { cn, formatDate } from '@/lib/format';
import { useDebouncedValue } from '@/hooks/useUi';

interface ActivityProps {
    entries: AdminActivityEntry[];
    meta: PageMeta;
    filters: { q: string; actor_role: string; severity: string; from: string; to: string };
    roles: Record<string, string>;
    severities: Record<string, string>;
    dataState: DataState;
    lastKnownAt: string;
}

const SEVERITY_CLASS = {
    critical: 'badge-emergency',
    warning: 'badge-warning',
    notice: 'badge-neutral',
} as const;

export default function AdminActivityLog({ entries, meta, filters, roles, severities, dataState, lastKnownAt }: ActivityProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(dataState);
    const [search, setSearch] = useState(filters.q);
    const [detail, setDetail] = useState<AdminActivityEntry | null>(null);
    const debounced = useDebouncedValue(search, 350);

    const apply = (partial: Partial<Record<string, string>> = {}, page = 1) => {
        const params: Record<string, string> = {
            q: filters.q,
            actor_role: filters.actor_role,
            severity: filters.severity,
            from: filters.from,
            to: filters.to,
            ...partial,
        };
        if (page > 1) params.page = String(page);
        Object.keys(params).forEach((key) => { if (params[key] === '') delete params[key]; });
        router.get('/admin/activity', params, { preserveState: true, preserveScroll: true, replace: true });
    };

    useEffect(() => { if (debounced !== filters.q) apply({ q: debounced }); /* eslint-disable-next-line */ }, [debounced]);

    const columns: Array<DataColumn<AdminActivityEntry>> = [
        {
            key: 'time',
            label: 'Time',
            render: (row) => <span className="tabular">{formatDate(row.createdAt, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat, withTime: true })}</span>,
        },
        {
            key: 'actor',
            label: 'Actor',
            render: (row) => (
                <div>
                    <strong>{row.actorName}</strong>
                    <div className="caption">{row.actorRoleLabel ?? 'System'}</div>
                </div>
            ),
        },
        { key: 'action', label: 'Action', render: (row) => <span className="mono">{row.action}</span> },
        {
            key: 'target',
            label: 'Target',
            render: (row) => row.targetType ? <span className="mono">{row.targetType.split('\\').pop()}#{row.targetId}</span> : <span className="muted">—</span>,
        },
        {
            key: 'severity',
            label: 'Severity',
            render: (row) => <span className={cn('badge', SEVERITY_CLASS[row.severity])}><span className="dot" />{row.severity}</span>,
        },
        {
            key: 'detail',
            label: 'Detail',
            render: (row) => (
                <button type="button" className="btn btn-ghost btn-sm" onClick={() => setDetail(row)} disabled={!row.detail}>
                    {row.detail ? 'View JSON' : '—'}
                </button>
            ),
        },
    ];

    return (
        <RoleLayout
            role="admin"
            title="Activity log"
            subtitle="append-only · no edit or delete code paths exist"
            activeHref="/admin/activity"
        >
            <Head title="Activity log — MediPath" />

            <form className="filter-bar" aria-label="Activity filters" onSubmit={(event) => { event.preventDefault(); apply({ q: search }); }}>
                <div className="field" style={{ margin: 0 }}>
                    <label className="label" htmlFor="act-q">Search detail JSON</label>
                    <input id="act-q" className="input mono" value={search} onChange={(event) => setSearch(event.target.value)} placeholder='e.g., "reason" or "credential"' />
                </div>
                <div className="field" style={{ margin: 0 }}>
                    <label className="label" htmlFor="act-role">Actor role</label>
                    <select id="act-role" className="select" value={filters.actor_role} onChange={(event) => apply({ actor_role: event.target.value })}>
                        <option value="">Any</option>
                        {Object.entries(roles).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        <option value="system">System</option>
                    </select>
                </div>
                <div className="field" style={{ margin: 0 }}>
                    <label className="label" htmlFor="act-sev">Severity</label>
                    <select id="act-sev" className="select" value={filters.severity} onChange={(event) => apply({ severity: event.target.value })}>
                        <option value="">Any</option>
                        {Object.entries(severities).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </select>
                </div>
                <div className="field" style={{ margin: 0 }}>
                    <label className="label" htmlFor="act-from">From</label>
                    <input id="act-from" className="input" type="date" value={filters.from} onChange={(event) => apply({ from: event.target.value })} />
                </div>
                <div className="field" style={{ margin: 0 }}>
                    <label className="label" htmlFor="act-to">To</label>
                    <input id="act-to" className="input" type="date" value={filters.to} onChange={(event) => apply({ to: event.target.value })} />
                </div>
                <div className="f-actions f-full">
                    <span className="caption">{meta.total} entries · newest first</span>
                    <button type="button" className="btn btn-ghost mla" onClick={() => { setSearch(''); router.get('/admin/activity', {}, { preserveState: true, preserveScroll: true, replace: true }); }}>
                        Clear facets
                    </button>
                </div>
            </form>

            <DataView
                name="Data view · Log entries"
                state={state}
                onStateChange={setState}
                showSwitch
                onRetry={() => router.reload()}
                lastKnownAt={lastKnownAt}
                emptyTitle="No entries match these facets"
                emptyBody="Widen the date range or clear the actor/severity filter. The log is never rewritten."
                errorMessage="Log query failed. Timeouts are bounded by design; retry is safe."
            >
                <DataTable
                    className="tbl-cards-md"
                    caption="Immutable activity log entries"
                    columns={columns}
                    rows={entries}
                    getRowKey={(row) => row.id}
                    page={meta}
                    onPageChange={(page) => apply({}, page)}
                />
                <p className="caption mt-m">Critical badges are reserved for emergency escalation events, keeping the scoped-red rule intact.</p>
            </DataView>

            <AdminModal
                open={Boolean(detail)}
                title={`Audit entry #${detail?.id ?? ''}`}
                subtitle={detail?.action}
                icon="🧾"
                wide
                onClose={() => setDetail(null)}
                footer={<button type="button" className="btn btn-quiet" onClick={() => setDetail(null)}>Close</button>}
            >
                <dl className="meta-grid">
                    <div><dt>Actor</dt><dd>{detail?.actorName} · {detail?.actorRoleLabel}</dd></div>
                    <div><dt>Target</dt><dd className="mono">{detail?.targetType ?? '—'} #{detail?.targetId ?? '—'}</dd></div>
                    <div><dt>IP</dt><dd className="mono">{detail?.ipAddress ?? '—'}</dd></div>
                    <div><dt>Time</dt><dd>{formatDate(detail?.createdAt, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat, withTime: true })}</dd></div>
                </dl>
                <pre className="mono mt-m" style={{ fontSize: 12, whiteSpace: 'pre-wrap', background: 'var(--mp-surface-muted)', padding: 12, borderRadius: 10 }}>
                    {detail?.detail ? JSON.stringify(detail.detail, null, 2) : 'No detail payload.'}
                </pre>
            </AdminModal>
        </RoleLayout>
    );
}