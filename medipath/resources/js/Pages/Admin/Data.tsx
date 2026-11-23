/**
 * Admin · Data management — prototypes/admin/13-data.html.
 * Streamed exports with CSV formula-injection neutralisation, metadata backup
 * snapshots, two-step CSV import, and password+typed-confirm danger actions.
 */
import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { AdminBackupSnapshot, AdminImportPreview } from '@/types/admin';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { AdminModal } from '@/Components/Admin/AdminModal';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { formatDate } from '@/lib/format';
import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';

interface DataProps {
    exports: Array<{ type: string; label: string }>;
    snapshots: AdminBackupSnapshot[];
    importPreview: AdminImportPreview | null;
    canReseed: boolean;
    counts: { patients: number; doctors: number; invoices: number; medicines: number };
    lastKnownAt: string;
}

type DangerAction = 'purge_sessions' | 'delete_failed_backups' | 'reseed_demo';

export default function AdminData({ exports, snapshots, importPreview, canReseed, counts }: DataProps) {
    const { settings } = usePage<SharedProps>().props;
    const [backupOpen, setBackupOpen] = useState(false);
    const [danger, setDanger] = useState<DangerAction | null>(null);
    const importForm = useForm<{ file: File | null }>({ file: null });
    const [fileName, setFileName] = useState('');

    return (
        <RoleLayout
            role="admin"
            title="Data management"
            subtitle={`${counts.patients} patients · ${counts.doctors} doctors · ${counts.invoices} invoices · ${counts.medicines} medicines`}
            activeHref="/admin/data"
            topbarActions={<button type="button" className="btn btn-primary" onClick={() => setBackupOpen(true)}>Create snapshot now</button>}
        >
            <Head title="Data management — MediPath" />

            <div className="grid">
                <section className="c-12 xl-c-6 card card-pad" aria-labelledby="exports-title">
                    <h2 className="h4" id="exports-title">CSV exports <span className="caption">streamed · formula-injection neutralised</span></h2>
                    <ul className="list-quiet mt-s" style={{ fontSize: 14 }}>
                        {exports.map((item) => (
                            <li key={item.type}>
                                <span>{item.label}</span>
                                <a className="btn btn-outline btn-sm mla" href={`/admin/data/export/${item.type}`}>⬇ CSV</a>
                            </li>
                        ))}
                    </ul>
                    <p className="caption">Leading =, +, −, @ and tab/CR are escaped; filename carries a timestamp.</p>
                </section>

                <section className="c-12 xl-c-6 card card-pad" aria-labelledby="backups-title">
                    <h2 className="h4" id="backups-title">Backup snapshots</h2>
                    <ul className="list-quiet mt-s" style={{ fontSize: 14 }}>
                        {snapshots.length === 0 ? <li>No snapshots yet. Create the first metadata snapshot.</li> : snapshots.map((snapshot) => (
                            <li key={snapshot.id}>
                                <StatusBadge family="account" status={snapshot.status === 'verified' ? 'approved' : snapshot.status === 'failed' ? 'suspended' : 'pending'} label={snapshot.status} />
                                <span className="mono">{snapshot.label}</span>
                                <span className="caption">{snapshot.size} · {formatDate(snapshot.createdAt, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat, withTime: true })}</span>
                                <div className="mp-row mla">
                                    <button type="button" className="btn btn-ghost btn-sm" onClick={() => router.post(`/admin/data/backups/${snapshot.id}/verify`, {}, { preserveScroll: true })}>Restore drill</button>
                                    {snapshot.status === 'failed' ? <button type="button" className="btn btn-ghost btn-sm" style={{ color: 'var(--mp-danger-text)' }} onClick={() => router.post(`/admin/data/backups/${snapshot.id}/delete`, {}, { preserveScroll: true })}>Delete failed</button> : null}
                                </div>
                            </li>
                        ))}
                    </ul>
                    <p className="caption">Snapshots are metadata JSON files on the private backups disk; the restore drill verifies file presence/integrity.</p>
                </section>

                <section className="c-12 xl-c-8 card card-pad" aria-labelledby="import-title">
                    <h2 className="h4" id="import-title">CSV import — always two-step (dry run → apply)</h2>
                    <form
                        className="stack-sm mt-s"
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (!importForm.data.file) return;
                            importForm.post('/admin/data/import/dry-run', { forceFormData: true, preserveScroll: true });
                        }}
                    >
                        <div className="field">
                            <label className="label" htmlFor="import-file">Medicines CSV</label>
                            <input id="import-file" className="input" type="file" accept=".csv,text/csv" onChange={(event) => { importForm.setData('file', event.target.files?.[0] ?? null); setFileName(event.target.files?.[0]?.name ?? ''); }} style={{ padding: 10 }} />
                            <span className="hint">≤ 5 MB · UTF-8 · header row required. Required fields: name, generic_name, category, strength, form, unit_price, stock_quantity, min_stock_threshold, expiry_date, batch_number.</span>
                            {fileName ? <span className="caption">Selected: {fileName}</span> : null}
                            {importForm.errors.file ? <span className="field-error" role="alert">{importForm.errors.file}</span> : null}
                        </div>
                        <div className="mp-row">
                            <button type="submit" className="btn btn-primary" disabled={importForm.processing || !importForm.data.file}>{importForm.processing ? 'Validating…' : 'Run dry-import'}</button>
                            {importPreview ? <span className="caption">Dry run: {importPreview.valid} valid · {importPreview.invalid} invalid · {importPreview.updates} will update.</span> : null}
                        </div>
                    </form>

                    {importPreview ? (
                        <div className="mt-m">
                            <div className="alert alert-warning">
                                <span className="a-ico" aria-hidden="true">🧪</span>
                                <span><strong>{importPreview.valid} rows valid · {importPreview.invalid} invalid · {importPreview.updates} will update.</strong> Nothing has been written yet.</span>
                            </div>
                            {importPreview.errors.length > 0 ? (
                                <div className="tbl-wrap mt-m">
                                    <table className="tbl">
                                        <caption className="sr-only">Invalid import rows</caption>
                                        <thead><tr><th>Row</th><th>Status</th><th>Reason</th></tr></thead>
                                        <tbody>
                                            {importPreview.errors.map((error) => (
                                                <tr key={error.row}><td data-label="Row" className="num">{error.row}</td><td data-label="Status"><span className="badge badge-danger"><span className="dot" />invalid</span></td><td data-label="Why">{error.reason}</td></tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : null}
                            <div className="mp-row mt-m">
                                <button type="button" className="btn btn-primary" onClick={() => router.post('/admin/data/import/apply', { token: importPreview.token, confirm: true }, { preserveScroll: true })}>Apply {importPreview.valid} rows</button>
                                <button type="button" className="btn btn-quiet" onClick={() => router.get('/admin/data', {}, { preserveScroll: true, replace: true })}>Discard preview</button>
                                <span className="caption">Apply runs in one transaction + audit entry.</span>
                            </div>
                        </div>
                    ) : null}
                </section>

                <section className="c-12 xl-c-4 card card-pad" style={{ borderTop: '4px solid var(--mp-danger)' }} aria-labelledby="danger-title">
                    <h2 className="h4" id="danger-title" style={{ color: 'var(--mp-danger-text)' }}>Danger zone</h2>
                    <p className="caption">Irreversible operations need password re-entry + typed confirmation; nothing here touches clinical records (soft-archive only elsewhere).</p>
                    <ul className="stack-sm mt-m" style={{ fontSize: 14 }}>
                        <li><button type="button" className="btn btn-danger-outline btn-block" onClick={() => setDanger('purge_sessions')}>Purge archived sessions</button></li>
                        <li><button type="button" className="btn btn-danger-outline btn-block" onClick={() => setDanger('delete_failed_backups')}>Delete failed backup files…</button></li>
                        <li><button type="button" className="btn btn-danger-outline btn-block" disabled={!canReseed} onClick={() => setDanger('reseed_demo')}>Reset demo data {canReseed ? '' : '(local only)'}</button></li>
                    </ul>
                </section>
            </div>

            <BackupModal open={backupOpen} onClose={() => setBackupOpen(false)} />
            <DangerModal action={danger} onClose={() => setDanger(null)} />
        </RoleLayout>
    );
}

function BackupModal({ open, onClose }: { open: boolean; onClose: () => void }) {
    const form = useForm({ label: '' });
    useEffect(() => { if (open) { form.reset(); form.clearErrors(); } /* eslint-disable-next-line */ }, [open]);
    return (
        <AdminModal open={open} title="Create backup snapshot" subtitle="Metadata snapshot on the private backups disk." icon="💾" onClose={onClose}
            footer={<><button type="button" className="btn btn-quiet" onClick={onClose}>Cancel</button><button type="submit" form="backup-form" className="btn btn-primary" disabled={form.processing}>{form.processing ? 'Creating…' : 'Create snapshot'}</button></>}>
            <form id="backup-form" onSubmit={(event) => { event.preventDefault(); form.post('/admin/data/backups', { preserveScroll: true, onSuccess: onClose }); }}>
                <div className="field"><label className="label" htmlFor="backup-label">Label</label><input id="backup-label" className="input" value={form.data.label} onChange={(event) => form.setData('label', event.target.value)} placeholder="Before November release" /></div>
            </form>
        </AdminModal>
    );
}

function DangerModal({ action, onClose }: { action: DangerAction | null; onClose: () => void }) {
    const form = useForm({ action: 'purge_sessions', password: '', confirmation: '' });

    useEffect(() => { if (action) { form.setData({ action, password: '', confirmation: '' }); form.clearErrors(); } /* eslint-disable-next-line */ }, [action]);

    const copy = action === 'purge_sessions'
        ? { title: 'Purge archived sessions?', phrase: 'PURGE', note: 'Deletes DB-backed session rows for archived accounts. Redis sessions are invalidated by version checks.' }
        : action === 'delete_failed_backups'
            ? { title: 'Delete failed backup files?', phrase: 'DELETE', note: 'Removes only snapshots marked failed, plus their private-disk files.' }
            : { title: 'Reset demo data?', phrase: 'RESET', note: 'Re-runs the idempotent demo seeders in local/testing only. Clinical history is preserved.' };

    return (
        <AdminModal open={Boolean(action)} title={copy.title} subtitle="Password re-entry and typed confirmation are both required." icon="☢️" danger onClose={onClose}
            footer={<><button type="button" className="btn btn-quiet" onClick={onClose}>Cancel</button><button type="submit" form="danger-form" className="btn btn-danger" disabled={form.processing}>{form.processing ? 'Running…' : 'Delete permanently'}</button></>}>
            <form id="danger-form" className="stack-sm" onSubmit={(event) => { event.preventDefault(); form.post('/admin/data/danger', { preserveScroll: true, onSuccess: onClose }); }}>
                <div className="alert alert-danger"><span className="a-ico" aria-hidden="true">⚠</span><span>{copy.note}</span></div>
                <div className="field"><label className="label req" htmlFor="danger-password">Re-enter your admin password</label><input id="danger-password" className="input" type="password" autoComplete="current-password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} />{form.errors.password ? <span className="field-error">{form.errors.password}</span> : null}</div>
                <div className="field"><label className="label req" htmlFor="danger-confirm">Type {copy.phrase} to confirm</label><input id="danger-confirm" className="input mono" autoComplete="off" value={form.data.confirmation} onChange={(event) => form.setData('confirmation', event.target.value)} />{form.errors.confirmation ? <span className="field-error">{form.errors.confirmation}</span> : null}</div>
            </form>
        </AdminModal>
    );
}