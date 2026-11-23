/** Pharmacist · Safety gate — prototypes/pharmacist/03-safety-gate.html. */
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { DataState } from '@/types';
import type { PharmacyGateItem, PharmacySafety } from '@/types/pharmacy';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { AdminModal } from '@/Components/Admin/AdminModal';
import { formatDate } from '@/lib/format';

interface SafetyGateProps {
    prescription: {
        id: number; code: string; status: string; statusLabel: string; issuedAt: string | null;
        pharmacyNotes: string | null; patientNotes: string | null; aiSummary: string | null; aiStatus: string;
        overrideJustification: string | null; hasOverride: boolean;
    };
    patient: {
        id: number; name: string; code: string; initials: string; age: number | null; gender: string | null; condition: string | null;
        allergies: Array<{ id: number; substance: string; severity: string; severityLabel: string; notes: string | null }>;
    };
    doctor: { name: string | null; fee: string };
    items: PharmacyGateItem[];
    safety: PharmacySafety;
    canDispense: boolean;
    dispenseUrl: string;
    dataState: DataState;
    lastKnownAt: string;
}

export default function PharmacistSafetyGate({ prescription, patient, doctor, items, safety, canDispense, dispenseUrl, dataState, lastKnownAt }: SafetyGateProps) {
    const [state, setState] = useState<DataState>(dataState);
    const [ack, setAck] = useState(false);
    const [overrideAck, setOverrideAck] = useState(false);
    const [refusing, setRefusing] = useState(false);
    const refuseForm = useForm({ reason: '' });
    const severe = patient.allergies.filter((allergy) => ['severe', 'life_threatening'].includes(allergy.severity));
    const ready = ack && canDispense && (!prescription.hasOverride || overrideAck);

    const submitRefusal = () => {
        refuseForm.post(`/pharmacist/prescriptions/${prescription.id}/refuse`, {
            preserveScroll: true,
            onSuccess: () => setRefusing(false),
        });
    };

    return (
        <RoleLayout
            role="pharmacist"
            title={`Safety gate — ${prescription.code}`}
            subtitle="Every check re-runs at the counter · deterministic first, AI summary advisory"
            activeHref="/pharmacist/prescriptions"
            topbarActions={(
                <>
                    <span className="badge badge-primary" style={{ minHeight: 36 }}>{patient.name} · <span className="mono">{patient.code}</span></span>
                    <a className="btn btn-outline" href="/pharmacist/prescriptions">Queue</a>
                </>
            )}
        >
            <Head title={`Safety gate — ${prescription.code}`} />

            <div className="grid" style={{ alignItems: 'start' }}>
                <section className="c-12 lg-c-5 card card-pad">
                    <h2 className="sr-only">Patient and prescription context</h2>
                    <div className="mp-row">
                        <span className="avatar avatar-initials" aria-hidden="true">{patient.initials}</span>
                        <div>
                            <strong>{patient.name}</strong> <span className="mono caption">{patient.code}</span>
                            <div className="caption">{patient.age ?? '—'}{patient.gender ? patient.gender.charAt(0) : ''} · {doctor.name ?? '—'} · issued {formatDate(prescription.issuedAt, { dateFormat: 'd M Y' })}</div>
                        </div>
                    </div>
                    {patient.allergies.length > 0 ? (
                        <div className="retry-banner mt-m" role="alert" style={{ background: 'var(--mp-danger-weak-bg)' }}>
                            <span className="ico" aria-hidden="true">⚠️</span>
                            <span className="txt"><strong>Allergies:</strong> {patient.allergies.map((allergy) => `${allergy.substance} — ${allergy.severityLabel}`).join(' · ')}</span>
                        </div>
                    ) : null}

                    <h3 className="h4 mt-m">Items</h3>
                    <ul className="list-quiet" style={{ fontSize: 14 }}>
                        {items.map((item) => (
                            <li key={item.id}>
                                <div>
                                    <strong>{item.name}</strong>
                                    <div className="caption">{item.dosage} · {item.schedule} · {item.duration} · qty {item.quantity}</div>
                                    <div className="caption mono">{item.batch ? `batch ${item.batch}` : 'no batch'} · exp {item.expiryDate ?? '—'}</div>
                                </div>
                                <StatusBadge family="stock" status={item.stockStatus} label={`${item.stock} available`} size="sm" />
                            </li>
                        ))}
                    </ul>

                    {prescription.pharmacyNotes ? (
                        <>
                            <h3 className="h4 mt-m">Pharmacy-facing note (doctor)</h3>
                            <p className="caption" style={{ fontSize: 13 }}>“{prescription.pharmacyNotes}”</p>
                        </>
                    ) : null}

                    <h3 className="h4 mt-m">AI safety summary</h3>
                    <p className="caption" style={{ fontSize: 13 }}>{prescription.aiSummary ?? 'No AI summary stored; deterministic checks are authoritative.'}</p>
                    <p className="caption">Status: {prescription.aiStatus}. Advisory only — it cannot clear a blocker.</p>
                    {severe.length > 0 ? <p className="caption" style={{ color: 'var(--mp-danger-text)' }}>{severe.length} severe allergy record(s) must be re-verified.</p> : null}
                </section>

                <section className="c-12 lg-c-7">
                    <DataView
                        name="Data view · Gate results"
                        state={state}
                        onStateChange={setState}
                        showSwitch
                        onRetry={() => router.reload()}
                        lastKnownAt={lastKnownAt}
                        emptyTitle="No safety data"
                        emptyBody="The deterministic services returned nothing — dispensing stays locked until checks are re-fetched."
                        errorMessage="Safety engine unreachable. Deterministic rules are the gate; do not dispense from stale results."
                    >
                        <h2 className="sr-only">Gate results</h2>
                        <div className="stack">
                            {safety.allergies.map((allergy) => (
                                <div className="card card-pad" key={`${allergy.medicine}-${allergy.substance}`} style={{ borderLeft: '4px solid var(--mp-danger)' }}>
                                    <div className="spread"><h3 className="h4">Allergy conflict — <span style={{ color: 'var(--mp-danger-text)' }}>BLOCKED</span></h3><StatusBadge family="allergy" status={allergy.severity} label={allergy.severityLabel} size="sm" /></div>
                                    <p className="mt-s" style={{ fontSize: 14 }}>{allergy.medicine} ↔ <strong>{allergy.substance}</strong>{allergy.classMatch ? ' (class match)' : ''}. {allergy.notes}</p>
                                    {prescription.hasOverride ? <p className="caption">Doctor override: “{prescription.overrideJustification}”</p> : <p className="caption">No override recorded — refuse or return to the doctor.</p>}
                                </div>
                            ))}

                            {safety.interactions.map((interaction) => (
                                <div className="card card-pad" key={interaction.id} style={{ borderLeft: `4px solid var(--mp-${interaction.blocking ? 'danger' : 'warning'})` }}>
                                    <div className="spread"><h3 className="h4">Drug interaction — <span style={{ color: `var(--mp-${interaction.blocking ? 'danger' : 'warning'}-text)` }}>{interaction.severityLabel}</span></h3><StatusBadge family="interaction" status={interaction.severity} label={interaction.severityLabel} size="sm" /></div>
                                    <p className="mt-s" style={{ fontSize: 14 }}>{interaction.a} × {interaction.b} — {interaction.description}</p>
                                    {interaction.management ? <p className="caption">Management: {interaction.management}</p> : null}
                                </div>
                            ))}

                            {safety.stock.map((row) => (
                                <div className="card card-pad" key={`${row.medicine}-${row.kind}`} style={{ borderLeft: '4px solid var(--mp-danger)' }}>
                                    <div className="spread"><h3 className="h4">Stock / expiry — <span style={{ color: 'var(--mp-danger-text)' }}>BLOCKED</span></h3><span className="badge badge-neutral">{row.kind}</span></div>
                                    <p className="mt-s" style={{ fontSize: 14 }}>{row.medicine}: requested {row.requested}, available {row.available}. Resolve before dispensing.</p>
                                </div>
                            ))}

                            {!safety.blocked ? (
                                <div className="card card-pad" style={{ borderLeft: '4px solid var(--mp-success)' }}>
                                    <div className="spread"><h3 className="h4">Deterministic checks — <span style={{ color: 'var(--mp-success-text)' }}>PASS</span></h3><StatusBadge family="stock" status="healthy" label="Clear" size="sm" /></div>
                                    <p className="mt-s" style={{ fontSize: 14 }}>Allergy, interaction, stock and expiry checks passed. Dispense deducts stock atomically under row locks.</p>
                                </div>
                            ) : null}

                            <div className="card card-pad">
                                <h3 className="h4">Pharmacist verification</h3>
                                <label className="check"><input type="checkbox" checked={ack} onChange={(event) => setAck(event.target.checked)} /><span>Identity, dose, quantity and counselling confirmed</span></label>
                                {prescription.hasOverride ? (
                                    <label className="check"><input type="checkbox" checked={overrideAck} onChange={(event) => setOverrideAck(event.target.checked)} /><span>Doctor override reviewed and accepted (or refuse below)</span></label>
                                ) : null}
                                <div className="mp-row mt-m" style={{ flexWrap: 'wrap' }}>
                                    {ready ? (
                                        <a className="btn btn-primary" href={dispenseUrl}>Proceed to dispense &amp; bill</a>
                                    ) : (
                                        <button type="button" className="btn btn-primary" disabled title="Complete the verification checks first">Proceed to dispense &amp; bill</button>
                                    )}
                                    <button type="button" className="btn btn-danger-outline" onClick={() => setRefusing(true)}>Refuse dispense…</button>
                                </div>
                                {!canDispense ? <p className="field-error mt-s">Dispense is locked until the blocker above is resolved or the prescription is refused.</p> : null}
                            </div>
                        </div>
                    </DataView>
                </section>
            </div>

            <AdminModal
                open={refusing}
                title={`Refuse ${prescription.code}`}
                subtitle="This cancels the prescription and notifies the doctor and patient. Nothing is dispensed."
                icon="🛑"
                danger
                onClose={() => setRefusing(false)}
                footer={(
                    <>
                        <button type="button" className="btn btn-quiet" onClick={() => setRefusing(false)}>Cancel</button>
                        <button type="button" className="btn btn-danger" disabled={refuseForm.processing || refuseForm.data.reason.trim().length < 10} onClick={submitRefusal}>
                            {refuseForm.processing ? 'Refusing…' : 'Refuse & return to doctor'}
                        </button>
                    </>
                )}
            >
                <div className="field">
                    <label className="label req" htmlFor="ph-refuse">Reason (required, ≥10 characters)</label>
                    <textarea id="ph-refuse" className="textarea" rows={4} value={refuseForm.data.reason} onChange={(event) => refuseForm.setData('reason', event.target.value)} placeholder="e.g., stock unavailable and patient cannot wait; medicine interaction requires doctor review" />
                    {refuseForm.errors.reason ? <span className="field-error">{refuseForm.errors.reason}</span> : null}
                </div>
                <p className="caption">The refusal is audit-logged with your user and re-runs no stock deduction.</p>
            </AdminModal>
        </RoleLayout>
    );
}