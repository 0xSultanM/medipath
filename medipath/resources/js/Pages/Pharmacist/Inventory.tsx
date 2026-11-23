/** Pharmacist · Medicine inventory + restock — prototypes/pharmacist/05-inventory.html. */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { DataState, PageMeta, SharedProps } from '@/types';
import type { PharmacyMedicineRow } from '@/types/pharmacy';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { DataTable, type DataColumn } from '@/Components/Data/DataTable';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { AdminModal } from '@/Components/Admin/AdminModal';
import { Money } from '@/Components/ui/Money';
import { useDebouncedValue } from '@/hooks/useUi';
import { formatDate } from '@/lib/format';

interface LedgerRow {
    id: number;
    medicine: string;
    type: string;
    typeLabel: string;
    change: number;
    new: number;
    actor: string | null;
    reference: string | null;
    createdAt: string | null;
}

interface InventoryProps {
    medicines: PharmacyMedicineRow[];
    meta: PageMeta;
    filters: { q: string; category: string; low: boolean; expiry: string };
    categories: string[];
    ledger: LedgerRow[];
    dataState: DataState;
    lastKnownAt: string;
}

const emptyMedicine = {
    name: '', generic_name: '', category: '', strength: '', form: 'tablet', manufacturer: '',
    unit_price: '0', min_stock_threshold: '10', expiry_date: '', batch_number: '',
    side_effects: '', contraindications: '', description: '', requires_prescription: true, stock_quantity: '0',
};

function MedicineModal({ open, medicine, onClose }: { open: boolean; medicine: PharmacyMedicineRow | null; onClose: () => void }) {
    const form = useForm({ ...emptyMedicine, stock_quantity: '0' });

    useEffect(() => {
        if (!open) return;
        form.setData(medicine ? {
            name: medicine.name,
            generic_name: medicine.genericName ?? '',
            category: medicine.category,
            strength: medicine.strength ?? '',
            form: medicine.form,
            manufacturer: medicine.manufacturer ?? '',
            unit_price: medicine.unitPrice,
            min_stock_threshold: String(medicine.threshold),
            expiry_date: medicine.expiryDate ?? '',
            batch_number: medicine.batch ?? '',
            side_effects: medicine.sideEffects,
            contraindications: medicine.contraindications ?? '',
            description: medicine.description ?? '',
            requires_prescription: medicine.requiresPrescription,
            stock_quantity: '0',
        } : emptyMedicine);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, medicine?.id]);

    const submit = () => {
        if (medicine) {
            form.put(`/pharmacist/inventory/${medicine.id}`, { preserveScroll: true, onSuccess: () => onClose() });
        } else {
            form.post('/pharmacist/inventory', { preserveScroll: true, onSuccess: () => onClose() });
        }
    };

    return (
        <AdminModal
            open={open}
            title={medicine ? `Edit ${medicine.displayName}` : 'Add medicine'}
            subtitle={medicine ? 'Metadata only — stock changes go through restock.' : 'Opening stock is written as an append-only ledger entry.'}
            icon={medicine ? '✏️' : '➕'}
            wide
            onClose={onClose}
            footer={<><button type="button" className="btn btn-quiet" onClick={onClose}>Cancel</button><button type="button" className="btn btn-primary" disabled={form.processing} onClick={submit}>{form.processing ? 'Saving…' : medicine ? 'Save changes' : 'Add medicine'}</button></>}
        >
            <form className="grid tight" onSubmit={(event) => { event.preventDefault(); submit(); }}>
                <div className="c-12 sm-c-6 field"><label className="label req" htmlFor="inv-name">Brand name</label><input id="inv-name" className="input" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />{form.errors.name ? <span className="field-error">{form.errors.name}</span> : null}</div>
                <div className="c-12 sm-c-6 field"><label className="label" htmlFor="inv-generic">Generic name</label><input id="inv-generic" className="input" value={form.data.generic_name} onChange={(event) => form.setData('generic_name', event.target.value)} /></div>
                <div className="c-12 sm-c-6 field"><label className="label req" htmlFor="inv-category">Drug class</label><input id="inv-category" className="input" value={form.data.category} onChange={(event) => form.setData('category', event.target.value)} placeholder="feeds allergy cross-match" /></div>
                <div className="c-12 sm-c-3 field"><label className="label" htmlFor="inv-strength">Strength</label><input id="inv-strength" className="input" value={form.data.strength} onChange={(event) => form.setData('strength', event.target.value)} /></div>
                <div className="c-12 sm-c-3 field"><label className="label" htmlFor="inv-form">Form</label><select id="inv-form" className="select" value={form.data.form} onChange={(event) => form.setData('form', event.target.value)}>{['tablet', 'capsule', 'syrup', 'injection', 'inhaler', 'cream', 'drops', 'other'].map((form) => <option key={form} value={form}>{form}</option>)}</select></div>
                <div className="c-12 sm-c-6 field"><label className="label" htmlFor="inv-manufacturer">Manufacturer</label><input id="inv-manufacturer" className="input" value={form.data.manufacturer} onChange={(event) => form.setData('manufacturer', event.target.value)} /></div>
                <div className="c-12 sm-c-3 field"><label className="label req" htmlFor="inv-price">Unit price</label><input id="inv-price" className="input" type="number" min={0} step="0.01" value={form.data.unit_price} onChange={(event) => form.setData('unit_price', event.target.value)} /></div>
                <div className="c-12 sm-c-3 field"><label className="label req" htmlFor="inv-threshold">Min threshold</label><input id="inv-threshold" className="input" type="number" min={0} value={form.data.min_stock_threshold} onChange={(event) => form.setData('min_stock_threshold', event.target.value)} /></div>
                <div className="c-12 sm-c-4 field"><label className="label" htmlFor="inv-batch">Batch</label><input id="inv-batch" className="input mono" value={form.data.batch_number} onChange={(event) => form.setData('batch_number', event.target.value)} /></div>
                <div className="c-12 sm-c-4 field"><label className="label" htmlFor="inv-expiry">Expiry</label><input id="inv-expiry" className="input" type="date" value={form.data.expiry_date} onChange={(event) => form.setData('expiry_date', event.target.value)} /></div>
                {!medicine ? <div className="c-12 sm-c-4 field"><label className="label req" htmlFor="inv-stock">Opening stock</label><input id="inv-stock" className="input" type="number" min={0} value={form.data.stock_quantity} onChange={(event) => form.setData('stock_quantity', event.target.value)} /></div> : null}
                <div className="c-12 field"><label className="label" htmlFor="inv-side">Side effects (comma separated)</label><input id="inv-side" className="input" value={form.data.side_effects} onChange={(event) => form.setData('side_effects', event.target.value)} /></div>
                <div className="c-12 field"><label className="label" htmlFor="inv-contra">Contraindications</label><textarea id="inv-contra" className="textarea" rows={2} value={form.data.contraindications} onChange={(event) => form.setData('contraindications', event.target.value)} /></div>
                <div className="c-12 field"><label className="label" htmlFor="inv-desc">Description</label><textarea id="inv-desc" className="textarea" rows={2} value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} /></div>
                <label className="check c-12"><input type="checkbox" checked={form.data.requires_prescription} onChange={(event) => form.setData('requires_prescription', event.target.checked)} /><span>Prescription required</span></label>
            </form>
        </AdminModal>
    );
}

function RestockModal({ medicine, onClose }: { medicine: PharmacyMedicineRow | null; onClose: () => void }) {
    const form = useForm({ quantity: '1', batch_number: medicine?.batch ?? '', expiry_date: medicine?.expiryDate ?? '', unit_price: medicine?.unitPrice ?? '', reference: '', notes: '' });

    useEffect(() => {
        if (!medicine) return;
        form.setData({ quantity: '1', batch_number: medicine.batch ?? '', expiry_date: medicine.expiryDate ?? '', unit_price: medicine.unitPrice, reference: '', notes: '' });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [medicine?.id]);

    const submit = () => {
        if (!medicine) return;
        form.post(`/pharmacist/inventory/${medicine.id}/restock`, { preserveScroll: true, onSuccess: () => onClose() });
    };

    return (
        <AdminModal
            open={Boolean(medicine)}
            title={`Restock ${medicine?.displayName ?? ''}`}
            subtitle={`Current stock ${medicine?.stock ?? 0} · every movement is ledgered`}
            icon="📦"
            onClose={onClose}
            footer={<><button type="button" className="btn btn-quiet" onClick={onClose}>Cancel</button><button type="button" className="btn btn-primary" disabled={form.processing} onClick={submit}>{form.processing ? 'Restocking…' : 'Restock'}</button></>}
        >
            <div className="grid tight">
                <div className="c-12 sm-c-6 field"><label className="label req" htmlFor="rs-qty">Quantity received</label><input id="rs-qty" className="input" type="number" min={1} value={form.data.quantity} onChange={(event) => form.setData('quantity', event.target.value)} />{form.errors.quantity ? <span className="field-error">{form.errors.quantity}</span> : null}</div>
                <div className="c-12 sm-c-6 field"><label className="label req" htmlFor="rs-batch">Batch</label><input id="rs-batch" className="input mono" value={form.data.batch_number} onChange={(event) => form.setData('batch_number', event.target.value)} /></div>
                <div className="c-12 sm-c-6 field"><label className="label req" htmlFor="rs-exp">Expiry (future)</label><input id="rs-exp" className="input" type="date" value={form.data.expiry_date} onChange={(event) => form.setData('expiry_date', event.target.value)} />{form.errors.expiry_date ? <span className="field-error">{form.errors.expiry_date}</span> : null}</div>
                <div className="c-12 sm-c-6 field"><label className="label" htmlFor="rs-price">Unit price (optional update)</label><input id="rs-price" className="input" type="number" min={0} step="0.01" value={form.data.unit_price} onChange={(event) => form.setData('unit_price', event.target.value)} /></div>
                <div className="c-12 sm-c-6 field"><label className="label" htmlFor="rs-ref">Reference / GRN</label><input id="rs-ref" className="input mono" value={form.data.reference} onChange={(event) => form.setData('reference', event.target.value)} /></div>
                <div className="c-12 sm-c-6 field"><label className="label" htmlFor="rs-notes">Notes</label><input id="rs-notes" className="input" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} /></div>
            </div>
        </AdminModal>
    );
}

export default function PharmacistInventory({ medicines, meta, filters, categories, ledger, dataState, lastKnownAt }: InventoryProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(dataState);
    const [search, setSearch] = useState(filters.q);
    const debounced = useDebouncedValue(search, 350);
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<PharmacyMedicineRow | null>(null);
    const [restocking, setRestocking] = useState<PharmacyMedicineRow | null>(null);
    const [archiving, setArchiving] = useState<PharmacyMedicineRow | null>(null);

    const apply = (partial: Record<string, string | boolean> = {}, page = 1) => {
        const params: Record<string, string> = {
            q: filters.q, category: filters.category, expiry: filters.expiry,
            low: filters.low ? '1' : '', ...Object.fromEntries(Object.entries(partial).map(([key, value]) => [key, String(value)])),
        };
        if (page > 1) params.page = String(page);
        Object.keys(params).forEach((key) => { if (!params[key] || (key === 'expiry' && params[key] === 'any')) delete params[key]; });
        router.get('/pharmacist/inventory', params, { preserveState: true, preserveScroll: true, replace: true });
    };

    useEffect(() => { if (debounced !== filters.q) apply({ q: debounced }); /* eslint-disable-next-line */ }, [debounced]);

    const columns: Array<DataColumn<PharmacyMedicineRow>> = [
        { key: 'name', label: 'Medicine', render: (row) => <div><strong>{row.displayName}</strong><div className="caption">{row.manufacturer ?? '—'} · <span className="mono">{row.batch ?? 'no batch'}</span></div></div> },
        { key: 'class', label: 'Class / ingredient', render: (row) => <div>{row.category}<div className="caption">{row.genericName ?? '—'}</div></div> },
        { key: 'batch', label: 'Batch · expiry', render: (row) => <div className="tabular">{row.batch ?? '—'} · {row.expiryDate ?? '—'}<div className="mt-s"><StatusBadge family="expiry" status={row.expiryStatus} size="sm" /></div></div> },
        { key: 'price', label: 'Unit', align: 'right', render: (row) => <Money formatted={row.priceFormatted} size="sm" /> },
        { key: 'stock', label: 'Stock vs threshold', render: (row) => { const width = Math.min(100, Math.round((row.stock / Math.max(row.threshold * 3, 1)) * 100)); const tone = row.stockStatus === 'healthy' ? 'good' : row.stockStatus === 'low' ? 'warn' : 'bad'; return <div><div className={`progress ${tone}`} style={{ minWidth: 80 }}><span style={{ width: `${width}%` }} /></div><span className="caption tabular">{row.stock} / min {row.threshold}</span></div>; } },
        { key: 'status', label: 'Stock badge', render: (row) => <StatusBadge family="stock" status={row.stockStatus} size="sm" /> },
    ];

    return (
        <RoleLayout
            role="pharmacist"
            title="Medicine inventory"
            subtitle={`${meta.total} active medicine(s)`}
            activeHref="/pharmacist/inventory"
            topbarActions={<button type="button" className="btn btn-primary" onClick={() => { setEditing(null); setFormOpen(true); }}>+ Add medicine</button>}
        >
            <Head title="Medicine inventory — MediPath" />

            <form className="filter-bar" aria-label="Inventory filters" onSubmit={(event) => { event.preventDefault(); apply({ q: search }); }}>
                <div className="field" style={{ margin: 0 }}><label className="label" htmlFor="iv-q">Search</label><input id="iv-q" className="input" type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="name, generic, batch or class" /></div>
                <div className="field" style={{ margin: 0 }}><label className="label" htmlFor="iv-cat">Category</label><select id="iv-cat" className="select" value={filters.category} onChange={(event) => apply({ category: event.target.value })}><option value="">All</option>{categories.map((category) => <option key={category} value={category}>{category}</option>)}</select></div>
                <label className="switch"><input type="checkbox" checked={filters.low} onChange={(event) => apply({ low: event.target.checked ? '1' : '' })} /><span>Only below threshold</span></label>
                <div className="field" style={{ margin: 0 }}><label className="label" htmlFor="iv-exp">Expiry window</label><select id="iv-exp" className="select" value={filters.expiry} onChange={(event) => apply({ expiry: event.target.value })}><option value="any">Any</option><option value="90">≤ 90 days</option><option value="expired">Expired</option></select></div>
                <div className="f-actions f-full"><span className="caption">{meta.total} result(s) · currency {settings.currencyCode}</span><button type="button" className="btn btn-ghost mla" onClick={() => { setSearch(''); router.get('/pharmacist/inventory', {}, { preserveState: true, preserveScroll: true, replace: true }); }}>Clear filters</button></div>
            </form>

            <DataView name="Data view · Inventory" state={state} onStateChange={setState} showSwitch onRetry={() => router.reload()} lastKnownAt={lastKnownAt} emptyTitle="Catalogue is empty" emptyBody="Add the clinic's first medicines — doctors' live search and every safety gate depend on this table." errorMessage="Inventory failed to load.">
                <DataTable
                    className="tbl-cards-md"
                    caption="Active medicines"
                    columns={columns}
                    rows={medicines}
                    getRowKey={(row) => row.id}
                    page={meta}
                    onPageChange={(page) => apply({}, page)}
                    actions={(row) => (
                        <div className="mp-row" style={{ flexWrap: 'nowrap', gap: 4 }}>
                            <button type="button" className="btn btn-ghost btn-sm" onClick={() => { setEditing(row); setFormOpen(true); }}>Edit</button>
                            <button type="button" className={`btn ${row.stockStatus === 'out_of_stock' || row.stockStatus === 'critical' ? 'btn-primary' : 'btn-outline'} btn-sm`} onClick={() => setRestocking(row)}>Restock</button>
                            <button type="button" className="btn btn-ghost btn-sm" onClick={() => setArchiving(row)} aria-label={`Archive ${row.displayName}`}>Archive</button>
                        </div>
                    )}
                />
            </DataView>

            <section className="card card-pad mt-l">
                <div className="spread"><h2 className="h4">Recent stock ledger</h2><span className="caption">append-only · newest first</span></div>
                {ledger.length === 0 ? <p className="caption mt-s">No stock movements recorded yet.</p> : (
                    <ul className="list-quiet mt-s" style={{ fontSize: 13 }}>
                        {ledger.map((entry) => (
                            <li key={entry.id}>
                                <div><strong>{entry.medicine}</strong><div className="caption">{entry.typeLabel} · {entry.change > 0 ? '+' : ''}{entry.change} → {entry.new} · {entry.reference ?? '—'} · {entry.actor ?? 'system'}</div></div>
                                <time className="caption tabular mla">{formatDate(entry.createdAt, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat, withTime: true })}</time>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <MedicineModal open={formOpen} medicine={editing} onClose={() => setFormOpen(false)} />
            <RestockModal medicine={restocking} onClose={() => setRestocking(null)} />

            <AdminModal
                open={Boolean(archiving)}
                title={`Archive ${archiving?.displayName ?? ''}?`}
                subtitle="The medicine disappears from doctors' live search; historical prescriptions, invoices and ledger rows remain."
                icon="🗄"
                danger
                onClose={() => setArchiving(null)}
                footer={<><button type="button" className="btn btn-quiet" onClick={() => setArchiving(null)}>Cancel</button><button type="button" className="btn btn-danger" onClick={() => { if (archiving) router.post(`/pharmacist/inventory/${archiving.id}/archive`, {}, { preserveScroll: true, onSuccess: () => setArchiving(null) }); }}>Archive medicine</button></>}
            >
                <p style={{ fontSize: 14 }}>No records are deleted. The ledger keeps the full audit trail, and the catalogue can be restored by an admin if needed.</p>
            </AdminModal>
        </RoleLayout>
    );
}