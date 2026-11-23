/** Pharmacist · Workflow tracker — prototypes/pharmacist/08-workflow-tracker.html. */
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import type { DataState } from '@/types';
import type { PharmacyWorkflowStage } from '@/types/pharmacy';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { WorkflowTracker, type Step } from '@/Components/Workflow/Stepper';

interface WorkflowProps {
    query: string;
    matches: Array<{ id: number; name: string; code: string; phone: string | null }>;
    patient: null | { id: number; name: string; code: string; initials: string; phone: string | null; age: number | null; gender: string | null; condition: string | null; walkInCode: string | null };
    prescription: null | { id: number; code: string; status: string; statusLabel: string; hasOverride: boolean };
    invoice: null | { id: number; code: string; netFormatted: string; status: string; statusLabel: string; pdfUrl: string };
    stages: PharmacyWorkflowStage[];
    stockBlocked: boolean;
    dataState: DataState;
    lastKnownAt: string;
}

export default function PharmacistWorkflow({ query, matches, patient, prescription, invoice, stages, stockBlocked, dataState, lastKnownAt }: WorkflowProps) {
    const [state, setState] = useState<DataState>(dataState);
    const [search, setSearch] = useState(query);
    const firstUndone = stages.findIndex((stage) => !stage.done);

    const steps: Step[] = stages.map((stage, index) => {
        const blocked = stage.key === 'stock' && stockBlocked && !stage.done;
        return {
            key: stage.key,
            label: stage.label,
            sub: stage.sub,
            state: stage.done ? 'done' : blocked ? 'blocked' : index === firstUndone ? 'current' : 'upcoming',
            reason: blocked ? 'Safety / stock conflict' : null,
        };
    });

    return (
        <RoleLayout role="pharmacist" title="Workflow tracker" subtitle="Registration → prescription → stock → dispense → billing → discharge" activeHref="/pharmacist/workflow">
            <Head title="Workflow tracker — MediPath" />

            <form className="filter-bar" aria-label="Workflow search" onSubmit={(event) => { event.preventDefault(); router.get('/pharmacist/workflow', search ? { q: search } : {}, { preserveState: true, preserveScroll: true, replace: true }); }}>
                <div className="field" style={{ margin: 0 }}>
                    <label className="label" htmlFor="wf-q">Patient</label>
                    <input id="wf-q" className="input mono" type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="PT-… / WI-… / name / phone" />
                </div>
                <div className="f-actions f-full">
                    <span className="caption">Status is derived from the latest prescription and invoice.</span>
                    <button type="submit" className="btn btn-primary mla">Trace patient</button>
                </div>
            </form>

            {matches.length > 1 && !patient ? (
                <section className="card card-pad mt-m">
                    <h2 className="h4">Pick a patient</h2>
                    <ul className="list-quiet mt-s">
                        {matches.map((match) => (
                            <li key={match.id}>
                                <div><strong>{match.name}</strong> <span className="mono caption">{match.code}</span><div className="caption">{match.phone ?? '—'}</div></div>
                                <a className="btn btn-outline btn-sm" href={`/pharmacist/workflow?patient=${match.id}`}>Open</a>
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}

            <DataView
                name="Data view · 6-stage journey"
                state={state}
                onStateChange={setState}
                showSwitch
                onRetry={() => router.reload()}
                lastKnownAt={lastKnownAt}
                emptyTitle="Pick a patient"
                emptyBody="Search a patient ID, name, phone or walk-in claim code to trace their journey."
                errorMessage="Journey failed to load."
            >
                {patient ? (
                    <div className="stack">
                        <div className="card card-pad">
                            <div className="spread" style={{ flexWrap: 'wrap' }}>
                                <div className="mp-row">
                                    <span className="avatar avatar-initials" aria-hidden="true">{patient.initials}</span>
                                    <div>
                                        <strong className="h3">{patient.name}</strong> <span className="mono caption">{patient.code}</span>
                                        <div className="caption">{patient.age ?? '—'} · {patient.gender ?? '—'} · {patient.condition ?? 'condition not recorded'}{patient.walkInCode ? ` · claim ${patient.walkInCode}` : ''}</div>
                                    </div>
                                </div>
                                {prescription ? <StatusBadge family="prescription" status={prescription.status} label={prescription.statusLabel} /> : null}
                            </div>
                        </div>

                        <WorkflowTracker steps={steps} state={state} ariaLabel={`${patient.name} clinical workflow`} />

                        <div className="grid">
                            {stages.map((stage) => (
                                <div className="c-12 md-c-6 card card-pad" key={stage.key} style={{ borderLeft: stage.key === 'dispense' && !stage.done ? '4px solid var(--mp-primary)' : undefined }}>
                                    <div className="spread">
                                        <h2 className="h4">{stage.label}</h2>
                                        <span className={`badge ${stage.done ? 'badge-success' : 'badge-neutral'}`}>{stage.done ? 'Done' : 'Pending'}</span>
                                    </div>
                                    <p className="caption mt-s" style={{ fontSize: 13 }}>{stage.detail}</p>
                                    <p className="caption">{stage.sub}</p>
                                    {stage.href && stage.action ? <a className="btn btn-outline btn-sm mt-s" href={stage.href}>{stage.action}</a> : null}
                                </div>
                            ))}
                        </div>

                        {invoice ? (
                            <div className="card card-pad">
                                <div className="spread" style={{ flexWrap: 'wrap' }}>
                                    <div><strong>{invoice.code}</strong> · {invoice.netFormatted} <StatusBadge family="payment" status={invoice.status} label={invoice.statusLabel} size="sm" /></div>
                                    <a className="btn btn-outline btn-sm" href={invoice.pdfUrl}>⬇ Invoice PDF</a>
                                </div>
                            </div>
                        ) : null}
                    </div>
                ) : null}
            </DataView>
        </RoleLayout>
    );
}