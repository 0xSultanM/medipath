/**
 * Guest landing — prototypes/guest/01-landing.html.
 * Hero + search (§1), emergency strip (§12 scoped red), featured doctors
 * data view (§7 + §0 four states) and the four workflow steps.
 */
import { Head, router } from '@inertiajs/react';
import type { DoctorSummary } from '@/types';
import { GuestLayout } from '@/Layouts/GuestLayout';
import { DataView } from '@/Components/Data/DataView';
import { DoctorCard } from '@/Components/Data/DoctorCard';

interface LandingProps {
    featuredDoctors: DoctorSummary[];
    specialties: Array<{ id: number; name: string; slug: string }>;
    emergencyContact: { label: string; number: string; tel: string } | null;
    dataState: 'success' | 'skeleton' | 'empty' | 'error';
    today: string;
}

export default function Landing({ featuredDoctors, specialties, emergencyContact, dataState, today }: LandingProps) {
    return (
        <GuestLayout title="Find a verified doctor" activeHref="/">
            <Head title="Verified doctors, safe appointments — MediPath">
                <meta name="description" content="Book leave-aware appointments with verified doctors and get every prescription safety-checked." />
            </Head>

            <section className="hero-section">
                <div className="grid" style={{ alignItems: 'start' }}>
                    <div className="c-12 md-c-6">
                        <span className="badge badge-primary">
                            <span className="dot" aria-hidden="true" />
                            Verified clinic platform · AI decision-support
                        </span>

                        <h1 className="hero-title mt-m">
                            Find a <span className="accent">verified doctor</span>, book a safe slot, get medicine checked before it reaches you.
                        </h1>

                        <p className="lead mt-m hero-sub">
                            MediPath pairs leave-aware availability with deterministic allergy and drug-interaction
                            gates. The AI assists — it never diagnoses.
                        </p>

                        <form className="card card-pad mt-l hero-search" action="/doctors" method="get" aria-label="Find a doctor">
                            <div className="grid tight">
                                <div className="field c-12 sm-c-6" style={{ margin: 0 }}>
                                    <label className="label" htmlFor="hl-spec">Specialty</label>
                                    <select className="select" id="hl-spec" name="specialty" defaultValue="">
                                        <option value="">Any specialty</option>
                                        {specialties.map((specialty) => (
                                            <option key={specialty.id} value={specialty.slug}>{specialty.name}</option>
                                        ))}
                                    </select>
                                </div>

                                <div className="field c-12 sm-c-6" style={{ margin: 0 }}>
                                    <label className="label" htmlFor="hl-date">Preferred date</label>
                                    <input className="input" type="date" id="hl-date" name="date" min={today} defaultValue={today} />
                                </div>

                                <div className="c-12">
                                    <button className="btn btn-primary btn-block" type="submit">Search doctors</button>
                                </div>
                            </div>
                        </form>

                        <div className="emergency-strip mt-m">
                            <a className="cta-emergency" href="/emergency">
                                🚨 Emergency? Call {emergencyContact?.number ?? 'your local emergency number'}
                            </a>
                            <span className="caption">ER directions · works offline once loaded</span>
                        </div>
                    </div>

                    <div className="c-12 md-c-6 stack" style={{ gap: 'var(--mp-space-md)' }}>
                        <div className="card card-pad trust-card">
                            <span className="trust-ico" aria-hidden="true">🩺</span>
                            <div className="trust-body">
                                <h2 className="h3">Verified doctors only</h2>
                                <p className="muted mt-s" style={{ fontSize: 14 }}>
                                    License review by the clinic admin before a doctor can take bookings.
                                </p>
                            </div>
                        </div>
                        <div className="card card-pad trust-card">
                            <span className="trust-ico" aria-hidden="true">🧪</span>
                            <div className="trust-body">
                                <h2 className="h3">Every prescription safety-checked</h2>
                                <p className="muted mt-s" style={{ fontSize: 14 }}>
                                    Allergy and interaction gates run before dispensing. Overrides need written justification.
                                </p>
                            </div>
                        </div>
                        <div className="card card-pad trust-card">
                            <span className="trust-ico" aria-hidden="true">🤖</span>
                            <div className="trust-body">
                                <h2 className="h3">AI symptom checker, rule-first</h2>
                                <p className="muted mt-s" style={{ fontSize: 14 }}>
                                    Red flags are matched by deterministic rules — the emergency path never depends on AI.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section className="stack" aria-labelledby="feat-h">
                <h2 id="feat-h">Featured verified doctors</h2>

                <DataView
                    name="Data view · Featured doctors (server-side)"
                    state={dataState}
                    onRetry={() => router.reload()}
                    lastKnownAt={new Date(Date.now() - 6 * 60 * 1000).toISOString()}
                    emptyTitle="No featured doctors yet"
                    emptyBody="Verification is in progress — new profiles appear here once the clinic approves their license."
                    emptyAction={<a className="btn btn-outline" href="/doctors">Browse the full directory</a>}
                    skeleton={
                        <div className="doctor-grid" aria-hidden="true">
                            {[0, 1, 2].map((item) => (
                                <DoctorCard
                                    key={item}
                                    doctor={{
                                        id: item,
                                        name: 'Loading',
                                        initials: 'MP',
                                        specialty: '—',
                                        verified: false,
                                        experienceYears: 0,
                                        rating: 0,
                                        reviewCount: 0,
                                        feeFormatted: '—',
                                        hasSlots: false,
                                    }}
                                    state="skeleton"
                                />
                            ))}
                        </div>
                    }
                >
                    <div className="doctor-grid">
                        {featuredDoctors.map((doctor) => (
                            <DoctorCard
                                key={doctor.id}
                                doctor={doctor}
                                cta="guest"
                                profileHref={doctor.profileUrl ?? `/doctors/${doctor.doctorCode ?? doctor.id}`}
                                bookHref={`/login?redirect=${encodeURIComponent(doctor.profileUrl ?? '/doctors')}`}
                            />
                        ))}
                    </div>
                </DataView>
            </section>

            <section className="stack mt-xl" aria-labelledby="how-title">
                <h2 id="how-title">How MediPath works</h2>
                <div className="grid">
                    {[
                        ['1', 'Check symptoms (optional)', 'Describe what you feel. Red flags escalate instantly to emergency guidance — no waiting on AI.'],
                        ['2', 'Book a verified doctor', 'Slots respect schedules, breaks and approved leave — what you see is truly open.'],
                        ['3', 'Safety-checked prescription', 'Allergy and interaction rules are checked live while the doctor prescribes, then again at the pharmacy.'],
                        ['4', 'Dispense & bill', 'Stock deducts atomically with billing; one PDF serves both prescription and invoice.'],
                    ].map(([number, title, body]) => (
                        <div className="c-12 sm-c-6 md-c-3" key={number}>
                            <article className="card card-pad how-card">
                                <div className="mp-row row-lg">
                                    <span className="badge badge-primary how-num" aria-hidden="true">{number}</span>
                                    <h3 className="h3">{title}</h3>
                                </div>
                                <p className="muted mt-s" style={{ fontSize: 14 }}>{body}</p>
                            </article>
                        </div>
                    ))}
                </div>
            </section>

            <section className="card card-pad mt-xl" aria-labelledby="cta-title">
                <div className="spread">
                    <div>
                        <h2 className="h4" id="cta-title">Ready when you are</h2>
                        <p className="muted" style={{ fontSize: 14 }}>
                            Browse the full directory or check emergency information — no account needed.
                        </p>
                    </div>
                    <div className="mp-row">
                        <a className="btn btn-primary" href="/doctors">Find doctors</a>
                        <a className="btn btn-outline" href="/emergency">Emergency info</a>
                    </div>
                </div>
            </section>
        </GuestLayout>
    );
}