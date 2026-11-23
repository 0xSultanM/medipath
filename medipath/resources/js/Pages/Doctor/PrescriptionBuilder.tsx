/**
 * Doctor · Prescription builder — prototypes/doctor/07-prescription-builder.html.
 * Live stock search, deterministic allergy/interaction/stock checks and a
 * sealed override path. The same checks run again server-side on save.
 */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { DataState, SharedProps } from '@/types';
import type { DoctorMedicine, PrescriptionAiReview, PrescriptionSafety } from '@/types/doctor';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { AdminModal } from '@/Components/Admin/AdminModal';
import { getJson, postJson } from '@/lib/http';
import { useDebouncedValue } from '@/hooks/useUi';
import { formatDate } from '@/lib/format';

interface PatientOption { id: number; code: string; name: string }
interface DraftItem { medicine_id: number; name: string; strength: string | null; stock: number; dosage: string; schedule: string; duration: string; quantity: number }

interface BuilderProps {
    patients: PatientOption[];
    selectedPatient: null | {
        id: number; code: string; name: string; initials: string; age: number | null; gender: string | null; condition: string | null;
        allergies: Array<{ id: number; substance: string; type: string; severity: string; severityLabel: string; notes: string | null }>;
        medications: Array<{ id: number; name: string; dosage: string; frequency: string }>;
    };
    appointment: null | { id: number; code: string; date: string | null; time: string; reason: string };
    dataState: DataState;
    lastKnownAt: string;
}

export default function DoctorPrescriptionBuilder({ patients, selectedPatient, appointment, lastKnownAt }: BuilderProps) {
    const { settings } = usePage<SharedProps>().props;
    const [q, setQ] = useState('');
    const debounced = useDebouncedValue(q, 300);
    const [medicines, setMedicines] = useState<DoctorMedicine[]>([]);
    const [stockState, setStockState] = useState<DataState>('empty');
    const [stockError, setStockError] = useState<string | null>(null);
    const [safety, setSafety] = useState<PrescriptionSafety | null>(null);
    const [safetyError, setSafetyError] = useState<string | null>(null);
    const [sideEffect, setSideEffect] = useState<DoctorMedicine | null>(null);
    const [ai, setAi] = useState<PrescriptionAiReview['ai'] | null>(null);
    const [aiBusy, setAiBusy] = useState(false);
    const [aiError, setAiError] = useState<string | null>(null);

    const form = useForm<{
        patient_id: number;
        appointment_id: number | null;
        items: DraftItem[];
        clinical_notes: string;
        patient_notes: string;
        pharmacy_notes: string;
        override_justification: string;
        ai_requested: boolean;
    }>({
        patient_id: selectedPatient?.id ?? 0,
        appointment_id: appointment?.id ?? null,
        items: [],
        clinical_notes: '',
        patient_notes: '',
        pharmacy_notes: '',
        override_justification: '',
        ai_requested: false,
    });

    useEffect(() => {
        if (!debounced.trim()) { setMedicines([]); setStockState('empty'); return; }
        setStockState('skeleton');
        setStockError(null);
        getJson<{ items: DoctorMedicine[] }>(`/doctor/medicines/search?q=${encodeURIComponent(debounced)}`)
            .then((data) => { setMedicines(data.items); setStockState(data.items.length ? 'success' : 'empty'); })
            .catch((error) => { setStockError(error.message ?? 'Medicine search failed.'); setStockState('error'); });
    }, [debounced]);

    const items = form.data.items;

    useEffect(() => {
        if (!selectedPatient || items.length === 0) { setSafety(null); return; }
        let cancelled = false;
        setSafetyError(null);
        postJson<PrescriptionSafety>('/doctor/prescriptions/safety-check', {
            patient_id: selectedPatient.id,
            items: items.map((item) => ({ medicine_id: item.medicine_id, quantity: item.quantity })),
        }).then((data) => { if (!cancelled) setSafety(data); })
            .catch((error) => { if (!cancelled) setSafetyError(error.message ?? 'Safety check failed.'); });
        return () => { cancelled = true; };
    }, [items, selectedPatient]);

    const resetAi = () => {
        setAi(null);
        setAiError(null);
        if (form.data.ai_requested) form.setData('ai_requested', false);
    };

    const requestAi = async () => {
        if (!selectedPatient || items.length === 0) return;
        setAiBusy(true);
        setAiError(null);
        try {
            const result = await postJson<PrescriptionAiReview>('/doctor/prescriptions/ai-review', {
                patient_id: selectedPatient.id,
                items,
            });
            setAi(result.ai);
            form.setData('ai_requested', true);
        } catch (error) {
            setAiError((error as Error).message ?? 'AI review failed.');
        } finally {
            setAiBusy(false);
        }
    };

    const addMedicine = (medicine: DoctorMedicine) => {
        if (items.some((item) => item.medicine_id === medicine.id)) return;
        resetAi();
        form.setData('items', [...items, {
            medicine_id: medicine.id,
            name: medicine.name,
            strength: medicine.strength,
            stock: medicine.stock,
            dosage: '1 tablet',
            schedule: 'Once daily',
            duration: '30 days',
            quantity: 30,
        }]);
    };

    const updateItem = (index: number, key: keyof DraftItem, value: string | number) => {
        resetAi();
        form.setData('items', items.map((item, current) => current === index ? { ...item, [key]: value } : item));
    };

    const removeItem = (index: number) => {
        resetAi();
        form.setData('items', items.filter((_, current) => current !== index));
    };

    const selectPatient = (id: string) => router.get('/doctor/prescriptions/new', id ? { patient: id } : {}, { preserveState: true, preserveScroll: true, replace: true });

    const save = () => form.post('/doctor/prescriptions', { preserveScroll: true });
    const overrideNeeded = Boolean(safety?.requiresOverride);
    const stockBlocked = Boolean(safety && (safety.expired || safety.stock.some((row) => ['out', 'insufficient'].includes(row.kind))));
    const canSave = Boolean(selectedPatient) && items.length > 0 && !stockBlocked && (!overrideNeeded || form.data.override_justification.trim().length >= 20);

    if (!selectedPatient) {
        return (
            <RoleLayout role="doctor" title="Prescribe" subtitle="select a patient you have treated" activeHref="/doctor/prescriptions/new">
                <Head title="Prescribe — MediPath" />
                <section className="card card-pad" style={{ maxWidth: 620 }}>
                    <h2 className="h4">Choose a patient</h2>
                    <p className="caption">Prescribing is limited to patients you have treated.</p>
                    <div className="field mt-m">
                        <label className="label" htmlFor="rx-patient">Patient</label>
                        <select id="rx-patient" className="select" defaultValue="" onChange={(event) => selectPatient(event.target.value)}>
                            <option value="">Select a patient…</option>
                            {patients.map((patient) => <option key={patient.id} value={patient.id}>{patient.name} · {patient.code}</option>)}
                        </select>
                    </div>
                    {patients.length === 0 ? <p className="caption mt-s">No treated patients yet. Register a walk-in or wait for a booking.</p> : null}
                </section>
            </RoleLayout>
        );
    }

    return (
        <RoleLayout role="doctor" title="Prescribe" subtitle={`${selectedPatient.name} · ${selectedPatient.code}`} activeHref="/doctor/prescriptions/new"
            topbarActions={<><select className="select" style={{ maxWidth: 260 }} value={selectedPatient.id} onChange={(event) => selectPatient(event.target.value)} aria-label="Switch patient">{patients.map((p) => <option key={p.id} value={p.id}>{p.name} · {p.code}</option>)}</select><a className="btn btn-outline" href="/doctor/prescriptions">History</a><a className="btn btn-outline" href={`/doctor/patients/${selectedPatient.id}`}>Open chart</a></>}>
            <Head title="Prescribe — MediPath" />

            <div className="card card-pad">
                <div className="spread" style={{ flexWrap: 'wrap' }}>
                    <div className="mp-row">
                        <span className="avatar avatar-initials" aria-hidden="true">{selectedPatient.initials}</span>
                        <div><strong>{selectedPatient.name}</strong> <span className="mono caption">{selectedPatient.code}</span><div className="caption">{selectedPatient.age ?? '—'} · {selectedPatient.gender ?? '—'} · {selectedPatient.condition ?? 'condition not recorded'}</div></div>
                    </div>
                    <span className="caption">{appointment ? `Visit ${appointment.code} · ${appointment.date} ${appointment.time}` : 'No linked appointment'}</span>
                </div>
                {selectedPatient.allergies.length > 0 ? (
                    <div className="retry-banner mt-m" role="alert" style={{ background: 'var(--mp-danger-weak-bg)' }}>
                        <span className="ico" aria-hidden="true">⚠️</span>
                        <span className="txt"><strong>Allergy alert:</strong> {selectedPatient.allergies.map((a) => `${a.substance} — ${a.severityLabel}`).join(' · ')}. A class match hard-blocks save unless you justify an override.</span>
                    </div>
                ) : null}
            </div>

            <div className="grid builder mt-m" style={{ alignItems: 'start' }}>
                <section className="c-12 lg-c-7 stack">
                    <div className="card card-pad">
                        <label className="label req" htmlFor="rx-search">Add medicine — live pharmacy stock</label>
                        <input id="rx-search" className="input mt-s" type="search" value={q} onChange={(event) => setQ(event.target.value)} placeholder="name, generic, class, batch" />
                        <div className="mt-m">
                            <DataView name="Data view · Stock search" state={stockState} onStateChange={setStockState} lastKnownAt={lastKnownAt}
                                emptyTitle="No medicine matches" emptyBody="Check spelling or search by generic name / batch."
                                errorMessage={stockError ?? 'Stock lookup failed — you can still type the medicine name.'}>
                                <ul className="list-quiet" style={{ fontSize: 14 }}>
                                    {medicines.map((medicine) => (
                                        <li key={medicine.id}>
                                            <div style={{ minWidth: 0 }}>
                                                <strong>{medicine.name} {medicine.strength}</strong>
                                                <div className="caption">{medicine.genericName} · {medicine.category} · batch {medicine.batch} · exp {medicine.expiryDate}</div>
                                            </div>
                                            <StatusBadge family="stock" status={medicine.stockStatus} size="sm" />
                                            <button type="button" className="btn btn-primary btn-sm" disabled={medicine.stock <= 0 || items.some((i) => i.medicine_id === medicine.id)} onClick={() => addMedicine(medicine)}>Add</button>
                                            <button type="button" className="btn btn-ghost btn-sm" onClick={() => setSideEffect(medicine)}>Side effects</button>
                                        </li>
                                    ))}
                                </ul>
                            </DataView>
                        </div>
                    </div>

                    <div className="tbl-wrap">
                        <div className="tbl-head"><h2 className="h4">Prescription items</h2><span className="caption">quantity validated at save and dispense</span></div>
                        <table className="tbl"><caption className="sr-only">Draft prescription items</caption><thead><tr><th>Medicine</th><th>Dosage</th><th>Schedule</th><th>Duration</th><th>Qty</th><th>Stock</th><th /></tr></thead>
                            <tbody>
                                {items.length === 0 ? <tr><td colSpan={7}><span className="caption">No items yet — search and add at least one medicine.</span></td></tr> : items.map((item, index) => (
                                    <tr key={item.medicine_id}>
                                        <td data-label="Medicine"><strong>{item.name} {item.strength}</strong></td>
                                        <td data-label="Dosage"><input className="input" aria-label={`Dosage for ${item.name}`} style={{ minHeight: 36, width: 110 }} value={item.dosage} onChange={(event) => updateItem(index, 'dosage', event.target.value)} /></td>
                                        <td data-label="Schedule"><input className="input" aria-label={`Schedule for ${item.name}`} style={{ minHeight: 36, width: 150 }} value={item.schedule} onChange={(event) => updateItem(index, 'schedule', event.target.value)} /></td>
                                        <td data-label="Duration"><input className="input" aria-label={`Duration for ${item.name}`} style={{ minHeight: 36, width: 100 }} value={item.duration} onChange={(event) => updateItem(index, 'duration', event.target.value)} /></td>
                                        <td data-label="Qty"><input className="input num" aria-label={`Quantity for ${item.name}`} type="number" min={1} max={1000} style={{ minHeight: 36, width: 80 }} value={item.quantity} onChange={(event) => updateItem(index, 'quantity', Number(event.target.value))} /></td>
                                        <td data-label="Stock"><StatusBadge family="stock" status={item.stock <= 0 ? 'out_of_stock' : item.quantity > item.stock ? 'critical' : 'healthy'} size="sm" /></td>
                                        <td data-label="Remove"><button type="button" className="btn btn-ghost btn-sm" style={{ color: 'var(--mp-danger-text)' }} onClick={() => removeItem(index)} aria-label={`Remove ${item.name}`}>✕</button></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="c-12 lg-c-5 stack">
                    <div className="card card-pad" style={{ borderLeft: '4px solid var(--mp-danger)' }}>
                        <h2 className="h3" style={{ color: 'var(--mp-danger-text)' }}>Safety checks</h2>
                        {safetyError ? <div className="field-error mt-s">{safetyError}</div> : null}
                        {!safety ? <p className="caption mt-s">Add medicines to run allergy, interaction, stock and expiry checks.</p> : (
                            <div className="stack-sm mt-s" style={{ fontSize: 14 }}>
                                {safety.allergies.length > 0 ? safety.allergies.map((a) => <div key={`${a.medicine}-${a.substance}`} className="alert alert-danger"><span className="a-ico">⚠</span><span><strong>{a.medicine}</strong> conflicts with {a.substance} ({a.severityLabel}){a.classMatch ? ' — class match' : ''}. {a.notes}</span></div>) : <p className="caption">No allergy conflict.</p>}
                                {safety.interactions.length > 0 ? safety.interactions.map((i) => <div key={i.id} className={`alert ${i.severity === 'contraindicated' || i.severity === 'major' ? 'alert-danger' : 'alert-warning'}`}><span className="a-ico">🧪</span><span><strong>{i.severityLabel}:</strong> {i.a} × {i.b} — {i.description} {i.management}</span></div>) : <p className="caption">No interactions found in the matrix.</p>}
                                {safety.stock.map((s) => <div key={`${s.medicine}-${s.kind}`} className="alert alert-warning"><span className="a-ico">💊</span><span>{s.medicine}: {s.kind === 'out' ? 'out of stock' : s.kind === 'expired' ? 'expired batch' : `only ${s.available} available (requested ${s.requested})`}.</span></div>)}
                                {overrideNeeded ? (
                                    <div className="field"><label className="label req" htmlFor="rx-override">Clinical justification (required, ≥20 chars)</label><textarea id="rx-override" className="textarea" rows={3} value={form.data.override_justification} onChange={(event) => form.setData('override_justification', event.target.value)} placeholder="culture-confirmed; desensitisation protocol; co-sign obtained" />{form.errors.override_justification ? <span className="field-error">{form.errors.override_justification}</span> : null}</div>
                                ) : null}
                                <p className="caption">Source: deterministic checks {safety.checkedAt ? `· checked ${formatDate(safety.checkedAt, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat, withTime: true })}` : ''}.</p>
                            </div>
                        )}

                        <div className="mt-m" style={{ borderTop: '1px solid var(--mp-border)', paddingTop: 'var(--mp-space-md)' }}>
                            <div className="spread" style={{ flexWrap: 'wrap', gap: 8 }}>
                                <div>
                                    <h3 className="h4" style={{ margin: 0 }}>AI safety summary</h3>
                                    <p className="caption">Optional advisory text. Deterministic checks above stay authoritative.</p>
                                </div>
                                <button type="button" className="btn btn-outline btn-sm" disabled={!safety || aiBusy || items.length === 0} onClick={requestAi}>
                                    {aiBusy ? 'Reviewing…' : ai ? 'Refresh summary' : 'Generate summary'}
                                </button>
                            </div>
                            {aiError ? <div className="field-error mt-s">{aiError}</div> : null}
                            {ai ? (
                                <div className="mt-s" role="status" aria-live="polite">
                                    <StatusBadge family="ai" status={ai.status} label={ai.status === 'ai' ? 'AI-assisted · advisory' : 'Deterministic fallback'} size="sm" />
                                    <p className="mt-s" style={{ fontSize: 14 }}>{ai.summary}</p>
                                    <p className="caption">Source: {ai.source}. Saved with the prescription as a provenance record.</p>
                                </div>
                            ) : <p className="caption mt-s">No AI summary generated. Saving without it stores a deterministic summary.</p>}
                        </div>
                    </div>

                    <div className="card card-pad">
                        <h2 className="h4">Notes</h2>
                        <div className="field"><label className="label" htmlFor="rx-patient-notes">Patient-facing notes</label><textarea id="rx-patient-notes" className="textarea" rows={3} value={form.data.patient_notes} onChange={(event) => form.setData('patient_notes', event.target.value)} /></div>
                        <div className="field"><label className="label" htmlFor="rx-pharmacy-notes">Pharmacy-facing notes</label><textarea id="rx-pharmacy-notes" className="textarea" rows={3} value={form.data.pharmacy_notes} onChange={(event) => form.setData('pharmacy_notes', event.target.value)} /></div>
                        <div className="field"><label className="label" htmlFor="rx-clinical-notes">Clinical notes</label><textarea id="rx-clinical-notes" className="textarea" rows={3} value={form.data.clinical_notes} onChange={(event) => form.setData('clinical_notes', event.target.value)} /></div>
                    </div>

                    <div className="card card-pad">
                        <button type="button" className="btn btn-primary btn-block" disabled={!canSave || form.processing} onClick={save}>{form.processing ? 'Saving…' : 'Save prescription'}</button>
                        {stockBlocked ? <p className="field-error mt-s">Resolve out-of-stock/expired items before saving.</p> : null}
                        {overrideNeeded && form.data.override_justification.trim().length < 20 ? <p className="caption mt-s">Override justification is required for the highlighted conflict.</p> : null}
                        <p className="caption mt-m">On save: items + notes + safety snapshot + override seal are written atomically; pharmacy receives it pending dispense.</p>
                    </div>
                </section>
            </div>

            <AdminModal open={Boolean(sideEffect)} title={`${sideEffect?.name ?? 'Medicine'} — side effects`} icon="💊" onClose={() => setSideEffect(null)} footer={<button type="button" className="btn btn-quiet" onClick={() => setSideEffect(null)}>Close</button>}>
                {sideEffect ? (
                    <div className="stack-sm">
                        <p className="caption">{sideEffect.genericName} · {sideEffect.strength} · {sideEffect.category}</p>
                        <h3 className="h4">Common</h3>
                        <ul className="list-quiet">{sideEffect.sideEffects.map((effect) => <li key={effect}>{effect}</li>)}</ul>
                        {sideEffect.contraindications ? <><h3 className="h4 mt-m">Contraindications</h3><p>{sideEffect.contraindications}</p></> : null}
                        <p className="caption">DB content shown verbatim to prescriber and retained in the prescription context.</p>
                    </div>
                ) : null}
            </AdminModal>
        </RoleLayout>
    );
}