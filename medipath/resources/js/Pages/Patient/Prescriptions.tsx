/**
 * Patient · My prescriptions — prototypes/patient/05-prescriptions.html.
 * Owner-only reads; pharmacy notes are not exposed, safety gates are shown as
 * passed/overridden and the PDF uses the shared server-rendered template.
 */
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { DataState, PageMeta, SharedProps } from '@/types';
import type { PatientPrescription } from '@/types/patient';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { Pager } from '@/Components/Data/DataTable';
import { formatDate } from '@/lib/format';

interface PrescriptionsProps {
    prescriptions: PatientPrescription[];
    meta: PageMeta;
    dataState: DataState;
    lastKnownAt: string;
}

export default function PatientPrescriptions({ prescriptions, meta, dataState, lastKnownAt }: PrescriptionsProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(dataState);

    return (
        <RoleLayout role="patient" title="My prescriptions" subtitle="pharmacy-facing notes are never shown here" activeHref="/patient/prescriptions">
            <Head title="My prescriptions — MediPath" />

            <DataView
                name="Data view · Prescriptions (owner-only)"
                state={state}
                onStateChange={setState}
                showSwitch
                onRetry={() => router.reload()}
                lastKnownAt={lastKnownAt}
                emptyTitle="No prescriptions yet"
                emptyBody="After a visit, your doctor's prescription lands here with the safety-check history and a printable PDF."
                errorMessage="Prescriptions couldn't load. The pharmacy counter copy is unaffected."
            >
                <div className="stack">
                    {prescriptions.map((prescription) => (
                        <details className="card" key={prescription.id} open={prescription === prescriptions[0]}>
                            <summary style={{ display: 'flex', flexWrap: 'wrap', gap: 'var(--mp-space-md)', alignItems: 'center', minHeight: 64, padding: '0 var(--mp-space-base)', cursor: 'pointer', fontWeight: 600 }}>
                                <span className="mono" style={{ color: 'var(--mp-text-muted)' }}>{prescription.code}</span>
                                <span>{prescription.doctorName}</span>
                                <StatusBadge family="prescription" status={prescription.status} label={prescription.statusLabel} />
                                <time className="caption mla" dateTime={prescription.createdAt ?? undefined}>
                                    {formatDate(prescription.createdAt, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })}
                                </time>
                                <a className="btn btn-outline btn-sm" href={prescription.pdfUrl} onClick={(event) => event.stopPropagation()}>⬇ Rx PDF</a>
                            </summary>
                            <div className="card-body" style={{ borderTop: '1px solid var(--mp-border)', padding: 'var(--mp-space-base)' }}>
                                <div className="tbl-wrap">
                                    <table className="tbl">
                                        <caption className="sr-only">Items on {prescription.code}</caption>
                                        <thead><tr><th>Medicine</th><th>Dosage</th><th>Schedule</th><th>Duration</th><th>Qty</th></tr></thead>
                                        <tbody>
                                            {prescription.items.map((item) => (
                                                <tr key={item.id}>
                                                    <td data-label="Medicine"><strong>{item.name} {item.strength}</strong><div className="caption">{item.genericName}</div></td>
                                                    <td data-label="Dosage">{item.dosage}</td>
                                                    <td data-label="Schedule">{item.schedule}</td>
                                                    <td data-label="Duration">{item.duration}</td>
                                                    <td data-label="Qty" className="num">{item.quantity}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                {prescription.patientNotes ? (
                                    <div className="alert alert-info mt-m"><span className="a-ico" aria-hidden="true">📝</span><span><strong>Doctor's note to you:</strong> {prescription.patientNotes}</span></div>
                                ) : null}
                                <p className="caption mt-s">
                                    Safety history: {prescription.safe ? 'allergy and interaction checks passed.' : 'an override was recorded and sealed by the pharmacy.'}
                                    {prescription.invoiceCode ? ` · Invoice ${prescription.invoiceCode}` : ''}
                                </p>
                            </div>
                        </details>
                    ))}
                    <Pager page={meta} onPageChange={(page) => router.get('/patient/prescriptions', { page }, { preserveState: true, preserveScroll: true })} />
                </div>
            </DataView>
        </RoleLayout>
    );
}