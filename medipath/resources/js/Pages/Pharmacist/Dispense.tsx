/** Pharmacist · Dispense & bill — prototypes/pharmacist/04-dispense-bill.html. */
import { Head, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { DataState } from '@/types';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { Money } from '@/Components/ui/Money';
import { postJson } from '@/lib/http';
import { useDebouncedValue } from '@/hooks/useUi';
import type { PharmacySafety } from '@/types/pharmacy';

interface DispenseLine {
    id: number;
    name: string;
    batch: string | null;
    expiryDate: string | null;
    stock: number;
    prescribed: number;
    unitFormatted: string;
    unitPrice: number;
    stockStatus: string;
}

interface Receipt {
    code: string;
    netFormatted: string;
    method: string;
    status: string;
    statusLabel: string;
    items: Array<{ description: string; quantity: number; lineFormatted: string }>;
    pdfUrl: string;
}

interface Quote {
    subtotalFormatted: string;
    discountFormatted: string;
    netFormatted: string;
    currencyCode: string;
    lines: Array<{ id: number; lineFormatted: string; available: number; insufficient: boolean; after: number }>;
}

interface DispenseProps {
    prescription: { id: number; code: string; statusLabel?: string };
    patient: { name: string | null; code: string | null; initials: string | null };
    lines: DispenseLine[];
    receipt: Receipt | null;
    consultationFee?: string;
    currencyCode?: string;
    paymentMethods: Array<{ value: string; label: string }>;
    safety?: PharmacySafety;
    dataState: DataState;
    lastKnownAt: string;
}

export default function PharmacistDispense({ prescription, patient, lines, receipt, consultationFee = '0', paymentMethods, safety, dataState, lastKnownAt }: DispenseProps) {
    const [quote, setQuote] = useState<Quote | null>(null);
    const [quoteError, setQuoteError] = useState<string | null>(null);
    const [state, setState] = useState<DataState>(dataState);

    const form = useForm({
        lines: lines.map((line) => ({ prescription_item_id: line.id, quantity: line.prescribed })),
        consultation_fee: consultationFee,
        discount_kind: 'none',
        discount_value: '',
        payment_method: 'cash',
        mark_paid: false,
        notes: '',
        safety_ack: false,
    });

    const signature = useDebouncedValue(JSON.stringify({
        lines: form.data.lines,
        consultation_fee: form.data.consultation_fee,
        discount_kind: form.data.discount_kind,
        discount_value: form.data.discount_value,
    }), 300);

    useEffect(() => {
        if (receipt) return;
        let cancelled = false;
        try {
            const payload = JSON.parse(signature) as Record<string, unknown>;
            postJson<Quote>(`/pharmacist/prescriptions/${prescription.id}/quote`, payload)
                .then((data) => { if (!cancelled) { setQuote(data); setQuoteError(null); } })
                .catch((error) => { if (!cancelled) setQuoteError((error as Error).message ?? 'Totals unavailable.'); });
        } catch {
            /* ignore malformed debounce frame */
        }
        return () => { cancelled = true; };
    }, [signature, prescription.id, receipt]);

    const insufficient = Boolean(quote?.lines.some((line) => line.insufficient));
    const canSave = !receipt && Boolean(quote) && !insufficient && form.data.safety_ack && !form.processing;

    if (receipt) {
        return (
            <RoleLayout
                role="pharmacist"
                title={`Dispense & bill — ${prescription.code}`}
                subtitle="Receipt · stock deducted and invoice issued atomically"
                activeHref="/pharmacist/prescriptions"
                topbarActions={<a className="btn btn-outline" href="/pharmacist/workflow">Track workflow</a>}
            >
                <Head title={`Receipt ${receipt.code} — MediPath`} />
                <div className="grid" style={{ alignItems: 'start' }}>
                    <section className="c-12 lg-c-7 card card-pad" role="status" aria-live="polite">
                        <span className="badge badge-success"><span className="dot" />Dispensed &amp; billed</span>
                        <h2 className="h2 mt-m mono">{receipt.code}</h2>
                        <dl className="mt-m">
                            <div className="def-row"><dt>Patient</dt><dd>{patient.name} · <span className="mono">{patient.code}</span></dd></div>
                            <div className="def-row"><dt>Net total</dt><dd><Money formatted={receipt.netFormatted} size="lg" /></dd></div>
                            <div className="def-row"><dt>Method</dt><dd>{receipt.method}</dd></div>
                            <div className="def-row"><dt>Status</dt><dd><StatusBadge family="payment" status={receipt.status} label={receipt.statusLabel} size="sm" /></dd></div>
                        </dl>
                        <div className="tbl-wrap mt-l">
                            <table className="tbl">
                                <caption className="sr-only">Invoice items</caption>
                                <thead><tr><th>Item</th><th>Qty</th><th style={{ textAlign: 'right' }}>Line</th></tr></thead>
                                <tbody>
                                    {receipt.items.map((item, index) => (
                                        <tr key={index}>
                                            <td data-label="Item">{item.description}</td>
                                            <td data-label="Qty" className="num">{item.quantity}</td>
                                            <td data-label="Line" className="num" style={{ textAlign: 'right' }}><Money formatted={item.lineFormatted} size="sm" /></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <div className="mp-row mt-l">
                            <a className="btn btn-outline" href={receipt.pdfUrl}>⬇ Invoice PDF</a>
                            <a className="btn btn-ghost" href="/pharmacist/workflow">Track workflow</a>
                            <a className="btn btn-ghost" href="/pharmacist/prescriptions">Back to queue</a>
                        </div>
                        <p className="caption mt-m">Stock ledger and invoice share one transaction; the patient and doctor were notified.</p>
                    </section>
                    <aside className="c-12 lg-c-5 card card-pad">
                        <h2 className="h4">What just happened</h2>
                        <ol className="mt-m" style={{ paddingLeft: 18, fontSize: 14, lineHeight: 2 }}>
                            <li>Prescription row locked and re-checked.</li>
                            <li>Deterministic allergy, interaction, stock and expiry checks re-run.</li>
                            <li>Every medicine row locked; stock deducted; ledger row written.</li>
                            <li>Invoice issued with a currency snapshot.</li>
                            <li>Prescription closed as dispensed; notifications sent.</li>
                        </ol>
                        <p className="caption">Any failure would have rolled all five steps back.</p>
                    </aside>
                </div>
            </RoleLayout>
        );
    }

    return (
        <RoleLayout
            role="pharmacist"
            title={`Dispense & bill — ${prescription.code}`}
            subtitle={`${patient.name} · ${patient.code}`}
            activeHref="/pharmacist/prescriptions"
            topbarActions={<a className="btn btn-outline" href={`/pharmacist/prescriptions/${prescription.id}`}>Back to gate</a>}
        >
            <Head title={`Dispense & bill — ${prescription.code}`} />

            <div className="grid" style={{ alignItems: 'start' }}>
                <section className="c-12 lg-c-7 card card-pad">
                    <h2 className="h4">Itemised dispense</h2>
                    <div className="tbl-wrap mt-m" style={{ border: 0 }}>
                        <table className="tbl">
                            <caption className="sr-only">Dispense quantities</caption>
                            <thead><tr><th>Medicine · batch</th><th>Stock</th><th>Qty</th><th>Unit</th><th style={{ textAlign: 'right' }}>Line</th></tr></thead>
                            <tbody>
                                {lines.map((line) => {
                                    const row = quote?.lines.find((entry) => entry.id === line.id);
                                    const value = form.data.lines.find((entry) => entry.prescription_item_id === line.id)?.quantity ?? line.prescribed;
                                    return (
                                        <tr key={line.id}>
                                            <td data-label="Medicine">
                                                <strong>{line.name}</strong>
                                                <div className="caption mono">{line.batch ? `batch ${line.batch}` : 'no batch'} · exp {line.expiryDate ?? '—'}</div>
                                            </td>
                                            <td data-label="Stock"><StatusBadge family="stock" status={line.stockStatus} label={`${line.stock} available`} size="sm" /></td>
                                            <td data-label="Qty">
                                                <input className="input num" aria-label={`Quantity for ${line.name}`} type="number" readOnly value={line.prescribed} style={{ minHeight: 36, width: 80 }} />
                                                <div className="caption">full course · after: {row ? row.after : line.stock - value}</div>
                                            </td>
                                            <td data-label="Unit" className="num"><Money formatted={line.unitFormatted} size="sm" /></td>
                                            <td data-label="Line" className="num" style={{ textAlign: 'right' }}>{row ? <Money formatted={row.lineFormatted} size="sm" /> : '—'}</td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    <div className="grid tight mt-l">
                        <div className="field c-12 sm-c-6">
                            <label className="label" htmlFor="ph-consult">Consultation fee</label>
                            <input id="ph-consult" className="input" type="number" min={0} step="0.01" value={form.data.consultation_fee} onChange={(event) => form.setData('consultation_fee', event.target.value)} />
                            <span className="hint">Prefilled from the doctor profile; editable for this bill.</span>
                        </div>
                        <div className="field c-12 sm-c-6">
                            <label className="label" htmlFor="ph-discount-kind">Discount kind</label>
                            <select id="ph-discount-kind" className="select" value={form.data.discount_kind} onChange={(event) => form.setData('discount_kind', event.target.value)}>
                                <option value="none">None</option>
                                <option value="percent">Percentage</option>
                                <option value="fixed">Flat amount</option>
                            </select>
                        </div>
                        <div className="field c-12 sm-c-6">
                            <label className="label" htmlFor="ph-discount-value">Discount value</label>
                            <input id="ph-discount-value" className="input" type="number" min={0} step="0.01" value={form.data.discount_value} disabled={form.data.discount_kind === 'none'} onChange={(event) => form.setData('discount_value', event.target.value)} />
                        </div>
                        <div className="field c-12 sm-c-6">
                            <label className="label req" htmlFor="ph-method">Payment method</label>
                            <select id="ph-method" className="select" value={form.data.payment_method} onChange={(event) => form.setData('payment_method', event.target.value)}>
                                {paymentMethods.map((method) => <option key={method.value} value={method.value}>{method.label}</option>)}
                            </select>
                        </div>
                        <div className="field c-12">
                            <label className="label" htmlFor="ph-notes">Invoice notes</label>
                            <textarea id="ph-notes" className="textarea" rows={2} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
                        </div>
                    </div>

                    <label className="check mt-m"><input type="checkbox" checked={form.data.mark_paid} onChange={(event) => form.setData('mark_paid', event.target.checked)} /><span>Payment received now (mark invoice paid)</span></label>
                    <label className="check"><input type="checkbox" checked={form.data.safety_ack} onChange={(event) => form.setData('safety_ack', event.target.checked)} /><span>Final gate — checks re-verified, patient counselled, dispense authorised</span></label>

                    <button
                        type="button"
                        className="btn btn-primary btn-block mt-l"
                        disabled={!canSave}
                        onClick={() => form.post(`/pharmacist/prescriptions/${prescription.id}/dispense`, { preserveScroll: true })}
                    >
                        {form.processing ? 'Dispensing…' : 'Dispense · deduct stock · create invoice'}
                    </button>
                    {insufficient ? <p className="field-error mt-s">One or more quantities exceed current stock. Reduce the quantity or restock first.</p> : null}
                    {form.errors.safety_ack ? <p className="field-error mt-s">{form.errors.safety_ack}</p> : null}
                    {form.errors.lines ? <p className="field-error mt-s">{form.errors.lines}</p> : null}
                    <p className="caption mt-s">Atomic: lock rows → validate stock → deduct → write ledger → bill → close prescription → notify. Any failure rolls back all.</p>
                </section>

                <aside className="c-12 lg-c-5 stack">
                    <DataView
                        name="Data view · Live totals"
                        state={state}
                        onStateChange={setState}
                        lastKnownAt={lastKnownAt}
                        emptyTitle="Draft totals"
                        emptyBody="Totals recalculate as quantities change."
                        errorMessage="Totals unavailable. Saving remains server-authoritative, but review before dispensing."
                    >
                        <div className="card card-pad">
                            <h2 className="h4">Live totals</h2>
                            <dl className="mt-s">
                                <div className="def-row"><dt>Gross subtotal</dt><dd><Money formatted={quote?.subtotalFormatted ?? '—'} /></dd></div>
                                <div className="def-row"><dt>Discount</dt><dd>{quote ? <Money formatted={quote.discountFormatted} /> : '—'}</dd></div>
                                <div className="def-row" style={{ borderBottom: '2px solid var(--mp-text-heading)' }}><dt><strong>Net total</strong></dt><dd>{quote ? <Money formatted={quote.netFormatted} size="lg" /> : '—'}</dd></div>
                            </dl>
                            <p className="caption mt-s">Currency snapshot {quote?.currencyCode ?? '—'} stored per invoice; later settings changes never rewrite history.</p>
                            {quoteError ? <p className="field-error mt-s">{quoteError}</p> : null}
                        </div>
                    </DataView>

                    {safety ? (
                        <div className="card card-pad">
                            <h2 className="h4">Gate summary</h2>
                            <p className="caption mt-s">{safety.blocked ? 'Blocked checks were found. The dispense will fail unless a sealed override is present and stock is sufficient.' : 'Deterministic checks passed when the gate loaded. They re-run again inside the dispense transaction.'}</p>
                            <ul className="list-quiet mt-s" style={{ fontSize: 13 }}>
                                <li>{safety.allergies.length} allergy conflict(s)</li>
                                <li>{safety.interactions.length} interaction finding(s)</li>
                                <li>{safety.stock.length} stock/expiry finding(s)</li>
                            </ul>
                        </div>
                    ) : null}
                </aside>
            </div>
        </RoleLayout>
    );
}