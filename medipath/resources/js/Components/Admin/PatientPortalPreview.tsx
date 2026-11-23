/**
 * PatientPortalPreview — COMPONENT-SPEC §17 cross-component pattern: read-only
 * “as-patient” surface used by admin/05.
 * Money values arrive pre-formatted by CurrencyFormatter (G1); clinical status
 * labels/badges come from the shared StatusBadge family mapping.
 */
import type { PortalData } from '@/types/admin';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { Money } from '@/Components/ui/Money';
import { EmptyState } from '@/Components/Feedback/EmptyState';
import { formatDate } from '@/lib/format';
import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';

export function PatientPortalPreview({ portal }: { portal: PortalData }) {
    const { settings } = usePage<SharedProps>().props;

    return (
        <div className="card card-pad">
            <div className="spread" style={{ flexWrap: 'wrap' }}>
                <div>
                    <h2 className="h4" style={{ margin: 0 }}>Portal preview — {portal.patient.name} ({portal.patient.code})</h2>
                    <div className="caption">
                        Admin opens the patient-facing surface read-only; every access is stamped in the activity log.
                    </div>
                </div>
                <span className="badge badge-neutral">read-only</span>
            </div>

            <dl className="meta-grid mt-m">
                <div><dt>Age / gender</dt><dd>{portal.patient.age ?? '—'} · {portal.patient.gender ?? '—'}</dd></div>
                <div><dt>Phone</dt><dd className="mono">{portal.patient.phone ?? '—'}</dd></div>
                <div><dt>Account</dt><dd>{portal.patient.accountStatusLabel ?? 'No linked account'}</dd></div>
                <div><dt>Primary condition</dt><dd>{portal.patient.condition ?? '—'}</dd></div>
            </dl>

            <div className="grid mt-l" style={{ alignItems: 'start' }}>
                <section className="c-12 md-c-6" aria-labelledby="portal-rx">
                    <h3 className="h4" id="portal-rx">Prescription history</h3>
                    {portal.prescriptions.length === 0 ? (
                        <EmptyState glyph="💊" title="No prescriptions yet" body="Prescriptions appear here after a verified doctor completes a visit." />
                    ) : (
                        <ul className="list-quiet" style={{ fontSize: 13 }}>
                            {portal.prescriptions.map((rx) => (
                                <li key={rx.id}>
                                    <span className="mono">{rx.code}</span>
                                    <StatusBadge family="prescription" status={rx.status} label={rx.statusLabel} size="sm" />
                                    <span className="caption mla">
                                        {formatDate(rx.createdAt, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="c-12 md-c-6" aria-labelledby="portal-billing">
                    <h3 className="h4" id="portal-billing">Billing history</h3>
                    {portal.invoices.length === 0 ? (
                        <EmptyState glyph="🧾" title="No invoices yet" body="Dispensed prescriptions generate invoices here." />
                    ) : (
                        <ul className="list-quiet" style={{ fontSize: 13 }}>
                            {portal.invoices.map((invoice) => (
                                <li key={invoice.id}>
                                    <span className="mono">{invoice.code}</span>
                                    <Money formatted={invoice.netTotal} size="sm" />
                                    <StatusBadge family="payment" status={invoice.paymentStatus} label={invoice.paymentStatusLabel} size="sm" />
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="c-12 md-c-6" aria-labelledby="portal-allergies">
                    <h3 className="h4" id="portal-allergies">Allergies</h3>
                    {portal.allergies.length === 0 ? (
                        <p className="caption">No recorded allergies. This is not a substitute for clinical review.</p>
                    ) : (
                        <ul className="list-quiet" style={{ fontSize: 13 }}>
                            {portal.allergies.map((allergy) => (
                                <li key={allergy.id}>
                                    <strong>{allergy.substance}</strong>
                                    <StatusBadge family="allergy" status={allergy.severity} label={allergy.severityLabel} size="sm" />
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="c-12 md-c-6" aria-labelledby="portal-meds">
                    <h3 className="h4" id="portal-meds">Active medicines</h3>
                    {portal.medications.length === 0 ? (
                        <p className="caption">No active long-term medicines recorded.</p>
                    ) : (
                        <ul className="list-quiet" style={{ fontSize: 13 }}>
                            {portal.medications.map((medication) => (
                                <li key={medication.id}>
                                    <strong>{medication.name}</strong>
                                    <span className="caption">{medication.dosage} · {medication.frequency}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </div>
    );
}