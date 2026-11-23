/**
 * Emergency information — prototypes/guest/04-emergency.html.
 * Scoped red zone (§12): one-tap call cards, red-flag accordion by category,
 * time-bounded clinic banner, offline cache note. Never relies on AI.
 */
import { Head, router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import type { EmergencyContactItem, SharedProps } from '@/types';
import { GuestLayout } from '@/Layouts/GuestLayout';
import { EmergencyKit } from '@/Components/Safety/EmergencyBlocks';

interface EmergencyProps {
    contacts: EmergencyContactItem[];
    fallbackContacts: EmergencyContactItem[];
    groups: Array<{ category: string; items: Array<{ id: number; title: string; directive: string }> }>;
    banner: { title: string; message: string; startsAt: string | null; endsAt: string | null } | null;
    dataState: 'success' | 'skeleton' | 'empty' | 'error';
    cachedAt: string;
}

export default function Emergency({ contacts, fallbackContacts, groups, banner, dataState, cachedAt }: EmergencyProps) {
    const { auth } = usePage<SharedProps>().props;

    useEffect(() => {
        // Cache only the anonymous emergency document. An authenticated page
        // contains session-scoped Inertia props and must not be persisted for
        // another person using the same device.
        if (auth.user) return;
        if (typeof navigator === 'undefined' || !('serviceWorker' in navigator)) return;

        navigator.serviceWorker.register('/sw.js').catch(() => {
            /* Offline cache is an enhancement; the page remains usable online. */
        });
    }, [auth.user]);

    return (
        <GuestLayout title="Emergency information" activeHref="/emergency" chatbot={false}>
            <Head title="Emergency information — MediPath">
                <meta name="description" content="One-tap emergency numbers, red-flag guidance and offline emergency information." />
            </Head>

            <div className="stack">
                <div>
                    <span className="badge badge-emergency">Emergency help</span>
                    <h1 className="mt-m">If this is a life-threatening emergency, call now.</h1>
                    <p className="lead mt-m" style={{ maxWidth: '70ch' }}>
                        Do not wait for an appointment, the chatbot, or an AI reply. The rules below are checked
                        deterministically — but the fastest safety step is always the phone.
                    </p>
                </div>

                <div>
                    <h2>Call, one tap</h2>
                    <p className="caption muted mt-s">
                        Numbers come from the clinic emergency directory and stay tappable offline after the first load.
                    </p>
                </div>

                <EmergencyKit
                    contacts={contacts}
                    fallbackContacts={fallbackContacts}
                    groups={groups}
                    banner={banner}
                    state={dataState}
                    onRetry={() => router.reload()}
                    cachedAt={cachedAt}
                />

                <section className="card card-pad" aria-labelledby="not-emergency">
                    <h2 className="h4" id="not-emergency">Not an emergency, but worried?</h2>
                    <p className="muted mt-s" style={{ fontSize: 14 }}>
                        Sign in and use the AI Symptom Checker for non-red-flag concerns, or book a verified doctor.
                        The checker runs the same deterministic rules first — if they match, it stops and brings you
                        back here.
                    </p>
                    <div className="mp-row mt-b">
                        <a className="btn btn-primary" href="/login?redirect=/patient/dashboard">Sign in to check symptoms</a>
                        <a className="btn btn-outline" href="/doctors">Browse doctors</a>
                    </div>
                </section>

                <p className="caption">
                    This page and its numbers are cached on the device after the first load, so they open without
                    network. Bookings and the chatbot are never offered as substitutes in an emergency.
                </p>
            </div>
        </GuestLayout>
    );
}