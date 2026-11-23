/**
 * Patient · Two-stage symptom checker — prototypes/patient/04-symptom-checker.
 * Stage 1 red flags are deterministic and halt the flow before any AI call.
 * Stage 2 uses the configured AI provider when available, otherwise shows an
 * explicitly labelled deterministic fallback with the specialty map.
 */
import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { DataState, SharedProps } from '@/types';
import type { TriageEmergency, TriageGuidance } from '@/types/patient';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { ChatbotWidget } from '@/Components/AI/ChatbotWidget';
import { AdminModal } from '@/Components/Admin/AdminModal';
import { Money } from '@/Components/ui/Money';
import { createChatbotTransport, CHATBOT_OFFLINE_ANSWER } from '@/lib/chatbot';
import { ApiError, postJson } from '@/lib/http';
import { cn } from '@/lib/format';

interface SymptomCheckerProps {
    suggestions: string[];
    contacts: Array<{ key: string; label: string; number: string; tel: string; icon: string | null }>;
    offlineAnswer: string;
    recentChecks: Array<{ id: number; summary: string; emergencyTriggered: boolean; source: string | null; sourceLabel: string; createdAt: string | null }>;
    counts: { checks: number };
    lastKnownAt: string;
}

const COMMON_SYMPTOMS = ['Fever', 'Sore throat', 'Cough', 'Headache', 'Chest pain', 'Shortness of breath', 'Rash', 'Abdominal pain'];

const URGENCY = {
    self_care: { label: 'Self-care guidance', className: 'badge-success' },
    monitor: { label: 'Short-term monitoring', className: 'badge-warning' },
    urgent: { label: 'Prompt assessment', className: 'badge-danger' },
} as const;

export default function PatientSymptomChecker({ suggestions, contacts, offlineAnswer, recentChecks, counts, lastKnownAt }: SymptomCheckerProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>('empty');
    const [symptoms, setSymptoms] = useState('');
    const [duration, setDuration] = useState('2-5 days');
    const [severity, setSeverity] = useState(4);
    const [onset, setOnset] = useState('gradual');
    const [bodyRegion, setBodyRegion] = useState('throat_neck');
    const [consent, setConsent] = useState(false);
    const [result, setResult] = useState<TriageGuidance | null>(null);
    const [emergency, setEmergency] = useState<TriageEmergency | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [halted, setHalted] = useState(false);
    const [modalOpen, setModalOpen] = useState(false);

    const addSymptom = (symptom: string) => {
        setSymptoms((current) => current.trim() === '' ? symptom : current.includes(symptom) ? current : `${current}, ${symptom}`);
    };

    const submit = async () => {
        setError(null);
        setResult(null);
        setEmergency(null);
        setState('skeleton');

        try {
            const response = await postJson<TriageGuidance | TriageEmergency>('/patient/symptom-check', {
                symptoms,
                duration,
                severity,
                onset,
                body_region: bodyRegion,
                consent,
            });

            if (response.kind === 'emergency') {
                setEmergency(response);
                setHalted(true);
                setModalOpen(true);
                setState('success');
                return;
            }

            setResult(response);
            setState('success');
        } catch (caught) {
            const message = caught instanceof ApiError ? caught.message : 'The symptom check could not be completed.';
            setError(message);
            setState('error');
        }
    };

    const reset = () => {
        setEmergency(null);
        setResult(null);
        setHalted(false);
        setModalOpen(false);
        setState('empty');
        setError(null);
    };

    return (
        <RoleLayout role="patient" title="AI Symptom Checker" subtitle="rule-first triage · general guidance, not a diagnosis" activeHref="/patient/symptom-checker">
            <Head title="AI symptom checker — MediPath" />

            <div className="alert alert-warning">
                <span className="a-ico" aria-hidden="true">⚠</span>
                <span><strong>Not a diagnosis and not dosing advice.</strong> Deterministic red-flag rules run first. A match stops this flow and shows emergency contacts immediately.</span>
            </div>

            <div className="grid" style={{ alignItems: 'start' }}>
                <section className="c-12 lg-c-5 card card-pad" aria-labelledby="intake-title">
                    <h2 className="h4" id="intake-title">1 · Describe what you feel</h2>
                    <div className="field mt-s">
                        <span className="label">Common symptoms</span>
                        <div className="mp-row" role="group" aria-label="Common symptoms">
                            {COMMON_SYMPTOMS.map((symptom) => <button type="button" key={symptom} className="chip chip-select" onClick={() => addSymptom(symptom)}>{symptom}</button>)}
                        </div>
                    </div>
                    <div className="field">
                        <label className="label req" htmlFor="triage-symptoms">Describe your symptoms</label>
                        <textarea id="triage-symptoms" className="textarea" rows={4} value={symptoms} onChange={(event) => setSymptoms(event.target.value)} placeholder="e.g., sore throat and fever for three days; swallowing is painful." disabled={halted} />
                        <span className="hint">Mention duration, what makes it better/worse, and any home readings.</span>
                    </div>
                    <div className="grid tight">
                        <div className="c-12 sm-c-6 field">
                            <label className="label" htmlFor="triage-duration">Duration</label>
                            <select id="triage-duration" className="select" value={duration} onChange={(event) => setDuration(event.target.value)} disabled={halted}>
                                <option>&lt; 1 day</option><option>2-5 days</option><option>1-2 weeks</option><option>&gt; 2 weeks</option>
                            </select>
                        </div>
                        <div className="c-12 sm-c-6 field">
                            <label className="label" htmlFor="triage-onset">Onset</label>
                            <select id="triage-onset" className="select" value={onset} onChange={(event) => setOnset(event.target.value)} disabled={halted}>
                                <option value="gradual">Gradual</option><option value="sudden">Sudden</option><option value="comes_and_goes">Comes and goes</option>
                            </select>
                        </div>
                        <div className="c-12 sm-c-6 field">
                            <label className="label" htmlFor="triage-region">Body region</label>
                            <select id="triage-region" className="select" value={bodyRegion} onChange={(event) => setBodyRegion(event.target.value)} disabled={halted}>
                                <option value="head">Head</option><option value="throat_neck">Throat / neck</option><option value="chest">Chest</option><option value="abdomen">Abdomen</option><option value="limbs">Limbs</option><option value="whole_body">Whole body</option><option value="other">Other</option>
                            </select>
                        </div>
                        <div className="c-12 sm-c-6 field">
                            <label className="label" htmlFor="triage-severity">Severity: {severity}/10</label>
                            <input id="triage-severity" className="input" type="range" min={1} max={10} value={severity} onChange={(event) => setSeverity(Number(event.target.value))} disabled={halted} />
                        </div>
                        <div className="c-12">
                            <label className="check">
                                <input id="triage-consent" type="checkbox" checked={consent} onChange={(event) => setConsent(event.target.checked)} disabled={halted} />
                                <span style={{ fontSize: 14 }}>I understand this is decision support, not emergency care, and consent to storing this check in my history.</span>
                            </label>
                        </div>
                    </div>
                    <div className="mp-row mt-b">
                        <button type="button" className="btn btn-primary" disabled={!consent || symptoms.trim().length < 5 || state === 'skeleton' || halted} onClick={submit}>
                            {state === 'skeleton' ? 'Checking…' : 'Run symptom check'}
                        </button>
                        {halted ? <button type="button" className="btn btn-quiet" onClick={reset}>Start a new check</button> : null}
                    </div>
                    <p className="caption">Stage 1: deterministic red-flag engine (no network). Stage 2: AI guidance when enabled, otherwise labelled rule-based fallback.</p>
                </section>

                <section className="c-12 lg-c-7">
                    <DataView
                        name="Data view · Check result"
                        state={state}
                        onStateChange={setState}
                        showSwitch
                        lastKnownAt={lastKnownAt}
                        emptyTitle="No result yet"
                        emptyBody="Describe your symptoms and run a check — guidance, specialty and doctor options appear here."
                        errorMessage={error ?? 'AI triage unavailable — no clinical data was lost. Try again.'}
                        onRetry={submit}
                        skeleton={<div className="card card-pad"><div className="sk sk-line" style={{ width: '60%' }} /><div className="sk sk-box" /><div className="sk sk-box" style={{ width: '70%' }} /></div>}
                    >
                        {emergency ? (
                            <EmergencyResult emergency={emergency} onOpen={() => setModalOpen(true)} />
                        ) : result ? (
                            <GuidanceResult result={result} />
                        ) : null}
                    </DataView>

                    {recentChecks.length > 0 ? (
                        <section className="card card-pad mt-l">
                            <h2 className="h4">Recent checks <span className="caption">({counts.checks})</span></h2>
                            <ul className="list-quiet mt-s" style={{ fontSize: 13 }}>
                                {recentChecks.map((check) => (
                                    <li key={check.id}>
                                        <span>{check.summary || 'Symptom check'}</span>
                                        <span className="caption mla">{check.emergencyTriggered ? 'Emergency escalation' : check.sourceLabel}</span>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ) : null}
                </section>
            </div>

            <AdminModal
                open={modalOpen && Boolean(emergency)}
                title="Red flag detected — get help now"
                subtitle={emergency ? `${emergency.rule.category} · ${emergency.rule.severity}` : undefined}
                icon="🚨"
                tone="emergency"
                onClose={() => setModalOpen(false)}
                footer={
                    <>
                        <button type="button" className="btn btn-quiet" onClick={() => setModalOpen(false)}>I'm safe — this wasn't an emergency</button>
                        {emergency?.contacts[0] ? <a className="btn btn-emergency mla" href={emergency.contacts[0].tel}>📞 Call {emergency.contacts[0].number}</a> : null}
                    </>
                }
            >
                {emergency ? (
                    <div className="stack">
                        <div className="alert alert-danger">
                            <span className="a-ico" aria-hidden="true">🚨</span>
                            <span><strong>{emergency.rule.title}</strong><br />{emergency.rule.directive}</span>
                        </div>
                        <div>
                            <strong>Call one of these immediately</strong>
                            <div className="stack-sm mt-s">
                                {emergency.contacts.map((contact) => (
                                    <a className="call-card" href={contact.tel} key={contact.key} style={{ width: '100%' }}>
                                        <span className="cc-ico" aria-hidden="true">{contact.icon ?? '☎'}</span>
                                        <span><span className="cc-cat">{contact.label}</span><span className="cc-num">{contact.number}</span></span>
                                    </a>
                                ))}
                            </div>
                        </div>
                        <div>
                            <strong>While you wait</strong>
                            <ul className="list-quiet mt-s" style={{ fontSize: 14 }}>
                                {emergency.rule.while_waiting.map((line) => <li key={line}>{line}</li>)}
                            </ul>
                        </div>
                        <p className="caption">This escalation was written to the activity log and stored in your symptom history. Triage is halted until you start a new check.</p>
                    </div>
                ) : null}
            </AdminModal>

            <ChatbotWidget
                transport={createChatbotTransport()}
                suggestions={suggestions}
                contacts={contacts}
                offlineAnswer={offlineAnswer || CHATBOT_OFFLINE_ANSWER}
                label="Ask MediPath assistant"
            />
        </RoleLayout>
    );
}

function EmergencyResult({ emergency, onOpen }: { emergency: TriageEmergency; onOpen: () => void }) {
    return (
        <div className="card card-pad" style={{ borderLeft: '4px solid var(--mp-emergency)' }}>
            <span className="badge badge-emergency"><span className="dot" />Emergency red flag</span>
            <h2 className="h3 mt-s">{emergency.rule.title}</h2>
            <p style={{ fontSize: 14 }}>{emergency.rule.directive}</p>
            <div className="mp-row mt-m">
                <button type="button" className="btn btn-emergency" onClick={onOpen}>Show emergency contacts</button>
                <a className="btn btn-outline" href="/emergency">Open emergency page</a>
            </div>
            <p className="caption mt-m">Deterministic Stage-1 match · triage halted · no AI was called.</p>
        </div>
    );
}

function GuidanceResult({ result }: { result: TriageGuidance }) {
    const urgency = URGENCY[result.urgency];
    return (
        <div className="stack">
            <div className="card card-pad">
                <div className="spread" style={{ flexWrap: 'wrap' }}>
                    <h2 className="h3">Guidance</h2>
                    <span className={cn('badge', urgency.className)}><span className="dot" />{urgency.label}</span>
                </div>
                <p style={{ fontSize: 14 }} className="mt-s">{result.summary}</p>
                <p className="caption">Source: {result.source === 'ai' ? 'AI-assisted, rule-cleared' : 'deterministic fallback (AI unavailable/unconfigured)'} · {result.disclaimer}</p>
            </div>

            <div className="card card-pad">
                <h3 className="h4">Questions to ask your doctor</h3>
                <ul className="list-quiet mt-s" style={{ fontSize: 14 }}>
                    {result.questions.map((question) => <li key={question}>• {question}</li>)}
                </ul>
                <div className="alert alert-info mt-m">
                    <span className="a-ico" aria-hidden="true">🎯</span>
                    <span>Recommended specialty: <strong>{result.specialty.name ?? 'General Medicine'}</strong>{result.specialty.matched_category ? ` · matched “${result.specialty.matched_category}”` : ' · default from the active specialty list'}.</span>
                </div>
                <div className="mp-row mt-m">
                    <a className="btn btn-outline" href={result.specialty.browseUrl}>Browse specialists</a>
                    {result.doctors[0] ? <a className="btn btn-primary" href={result.doctors[0].bookingUrl}>Book {result.specialty.name}</a> : null}
                </div>
            </div>

            {result.doctors.length > 0 ? (
                <div className="card card-pad">
                    <h3 className="h4">Verified doctors for this specialty</h3>
                    <div className="doctor-grid mt-s">
                        {result.doctors.map((doctor) => (
                            <article className="card doc-card" key={doctor.id}>
                                <div className="doc-top">
                                    <span className="avatar avatar-initials" aria-hidden="true">{doctor.initials}</span>
                                    <div>
                                        <h4 className="h4" style={{ margin: 0 }}>{doctor.name}</h4>
                                        <div className="caption">{doctor.specialty} · {doctor.experienceYears} yrs</div>
                                    </div>
                                </div>
                                <div className="doc-stats">
                                    <div className="doc-stat"><span className="stars" aria-label={`${doctor.rating} of 5`}>★</span><span className="caption">{doctor.rating.toFixed(1)} ({doctor.reviewCount})</span></div>
                                    <div className="doc-stat"><span className="muted">Fee</span><Money formatted={doctor.feeFormatted} size="sm" /></div>
                                </div>
                                <div className="doc-next">{doctor.nextAvailable ?? 'No open slots this week'}</div>
                                <div className="doc-actions"><a className="btn btn-primary" href={doctor.bookingUrl} aria-disabled={!doctor.hasSlots}>{doctor.hasSlots ? 'Book' : 'Waitlist'}</a></div>
                            </article>
                        ))}
                    </div>
                </div>
            ) : (
                <div className="card card-pad">
                    <h3 className="h4">Verified doctors for this specialty</h3>
                    <p className="caption mt-s">No verified {result.specialty.name ?? 'specialist'} doctors are listed yet. Browse all doctors or contact the clinic desk for the next available specialist.</p>
                    <a className="btn btn-outline mt-s" href="/patient/doctors">Browse all doctors</a>
                </div>
            )}

            <div className="alert alert-warning">
                <span className="a-ico" aria-hidden="true">⚠</span>
                <span>If symptoms worsen, you develop chest pain, breathing difficulty, fainting, or a new severe symptom, call emergency services — do not wait for an online answer. <a href="/emergency">Emergency info</a>.</span>
            </div>
        </div>
    );
}