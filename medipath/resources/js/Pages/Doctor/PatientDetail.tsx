/**
 * Doctor · Patient chart — prototypes/doctor/05-patient-detail.html.
 * Allergy strip loads with the chart; clinical history is read-only for the
 * doctor and scoped to patients this doctor has treated.
 */
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { DataState, SharedProps, Tone } from '@/types';
import type { DoctorPrescriptionRow } from '@/types/doctor';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { Money } from '@/Components/ui/Money';
import { formatDate } from '@/lib/format';

interface PatientDetailProps {
    tab: 'prescriptions' | 'appointments' | 'billing';
    patient: { id: number; code: string; name: string; initials: string; age: number | null; gender: string | null; phone: string | null; condition: string | null; address: string | null; walkInCode: string | null };
    allergies: Array<{ id: number; substance: string; type: string; severity: string; severityLabel: string; reactionNotes: string | null }>;
    medications: Array<{ id: number; name: string; dosage: string; frequency: string }>;
    prescriptions: Array<{ id: number; code: string; doctor: string | null; status: string; statusLabel: string; hasOverride: boolean; overrideJustification: string | null; itemNames: string[]; createdAt: string | null }>;
    appointments: Array<{ id: number; code: string; date: string | null; time: string; doctor: string | null; status: string; statusLabel: string; reason: string }>;
    invoices: Array<{ id: number; code: string; netFormatted: string; status: string; statusLabel: string; createdAt: string | null }>;
    dataState: DataState;
    lastKnownAt: string;
}

export default function DoctorPatientDetail({ tab, patient, allergies, medications, prescriptions, appointments, invoices, dataState, lastKnownAt }: PatientDetailProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(dataState);
    const severe = allergies.filter((a) => ['severe', 'life_threatening'].includes(a.severity));

    const switchTab = (next: PatientDetailProps['tab']) => {
        router.get(`/doctor/patients/${patient.id}`, next === 'prescriptions' ? {} : { tab: next }, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <RoleLayout
            role="doctor"
            title={`Chart — ${patient.name}`}
            subtitle={`${patient.code} · ${patient.age ?? '—'}${patient.gender ? patient.gender.charAt(0) : ''} · ${patient.condition ?? 'condition not recorded'}`}
            activeHref="/doctor/patients"
            topbarActions={<a className="btn btn-primary" href={`/doctor/prescriptions/new?patient=${patient.id}`}>New prescription</a>}
        >
            <Head title={`Chart — ${patient.name}`} />

            {severe.length > 0 ? (
                <div className="retry-banner" role="alert" aria-label="Allergy alert" style={{ background: 'var(--mp-danger-weak-bg)' }}>
                    <span className="ico" aria-hidden="true">⚠️</span>
                    <span className="txt"><strong>{severe.length} severe allergy alert(s):</strong> {severe.map((a) => `${a.substance} (${a.severityLabel})`).join(' · ')} <span className="caption">Enforced again at the pharmacy gate.</span></span>
                </div>
            ) : null}

            <div className="grid" style={{ alignItems: 'start' }}>
                <aside className="c-12 lg-c-4 card card-pad">
                    <h2 className="h4">Demographics</h2>
                    <dl className="mt-s">
                        <div className="def-row"><dt>Age / gender</dt><dd>{patient.age ?? '—'} · {patient.gender ?? '—'}</dd></div>
                        <div className="def-row"><dt>Phone</dt><dd className="mono">{patient.phone ?? '—'}</dd></div>
                        <div className="def-row"><dt>Condition</dt><dd>{patient.condition ?? '—'}</dd></div>
                        {patient.walkInCode ? <div className="def-row"><dt>Claim code</dt><dd className="mono">{patient.walkInCode}</dd></div> : null}
                    </dl>
                    <h3 className="h4 mt-m">Allergies</h3>
                    {allergies.length === 0 ? <p className="caption mt-s">None recorded.</p> : <ul className="list-quiet mt-s" style={{ fontSize: 13 }}>{allergies.map((a) => <li key={a.id}><strong>{a.substance}</strong><StatusBadge family="allergy" status={a.severity} label={a.severityLabel} size="sm" /><span className="caption mla">{a.reactionNotes}</span></li>)}</ul>}
                    <h3 className="h4 mt-m">Current medications</h3>
                    {medications.length === 0 ? <p className="caption mt-s">None recorded.</p> : <ul className="list-quiet mt-s" style={{ fontSize: 13 }}>{medications.map((m) => <li key={m.id}>{m.name} · {m.dosage} · {m.frequency}</li>)}</ul>}
                </aside>

                <div className="c-12 lg-c-8">
                    <DataView name="Data view · Clinical history" state={state} onStateChange={setState} showSwitch onRetry={() => router.reload()} lastKnownAt={lastKnownAt}
                        emptyTitle="New patient — no history yet" emptyBody="Prescriptions and visits appear here after care is recorded." errorMessage="History unavailable. The allergy strip loaded separately and stands.">
                        <div className="tabs" role="tablist" aria-label="Chart sections">
                            <button type="button" role="tab" aria-selected={tab === 'prescriptions'} onClick={() => switchTab('prescriptions')}>Prescriptions <span className="cnt tabular">{prescriptions.length}</span></button>
                            <button type="button" role="tab" aria-selected={tab === 'appointments'} onClick={() => switchTab('appointments')}>Appointments <span className="cnt tabular">{appointments.length}</span></button>
                            <button type="button" role="tab" aria-selected={tab === 'billing'} onClick={() => switchTab('billing')}>Billing <span className="cnt tabular">{invoices.length}</span></button>
                        </div>

                        <div className="mt-m">
                            {tab === 'prescriptions' ? (
                                <ul className="list-quiet" style={{ fontSize: 14 }}>
                                    {prescriptions.map((rx) => (
                                        <li key={rx.id} className="stack-sm" style={{ gap: 4 }}>
                                            <div className="mp-row"><span className="mono caption">{rx.code}</span><strong>{rx.itemNames.join(' · ')}</strong><StatusBadge family="prescription" status={rx.status} label={rx.statusLabel} size="sm" /><span className="caption mla">{formatDate(rx.createdAt, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })}</span><a className="btn btn-outline btn-sm" href={`/doctor/prescriptions/${rx.id}/pdf`}>⬇ PDF</a></div>
                                            {rx.hasOverride ? <span className="caption">⚠ Override: {rx.overrideJustification}</span> : null}
                                        </li>
                                    ))}
                                </ul>
                            ) : tab === 'appointments' ? (
                                <ul className="list-quiet" style={{ fontSize: 14 }}>
                                    {appointments.map((appointment) => (
                                        <li key={appointment.id}><span className="tabular">{formatDate(appointment.date, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })} · {appointment.time}</span><StatusBadge family="appointment" status={appointment.status} label={appointment.statusLabel} size="sm" /><span>{appointment.reason}</span><span className="caption mla">{appointment.doctor}</span></li>
                                    ))}
                                </ul>
                            ) : (
                                <div className="tbl-wrap"><table className="tbl"><caption className="sr-only">Billing context</caption><thead><tr><th>Invoice</th><th>Net</th><th>Status</th></tr></thead><tbody>
                                    {invoices.map((invoice) => <tr key={invoice.id}><td data-label="Invoice" className="mono">{invoice.code}</td><td data-label="Net" className="num"><Money formatted={invoice.netFormatted} size="sm" /></td><td data-label="Status"><StatusBadge family="payment" status={invoice.status} label={invoice.statusLabel} size="sm" /></td></tr>)}
                                </tbody></table></div>
                            )}
                        </div>
                    </DataView>
                </div>
            </div>
        </RoleLayout>
    );
}