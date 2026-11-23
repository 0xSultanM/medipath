/** Pending / rejected application holding screen — prototypes/guest/08-pending.html. */
import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { PendingApplication, SharedProps } from '@/types';
import { GuestLayout } from '@/Layouts/GuestLayout';
import { DataView } from '@/Components/Data/DataView';
import { Stepper, type Step } from '@/Components/Workflow/Stepper';
import { Spinner } from '@/Components/Feedback/FeedbackKit';
import { getJson } from '@/lib/http';
import { formatDate } from '@/lib/format';

interface PendingProps {
    application: PendingApplication;
    dataState: 'success' | 'skeleton' | 'empty' | 'error';
}

type PollPayload = PendingApplication & { checked_at: string };

export default function PendingApproval({ application, dataState }: PendingProps) {
    const { settings } = usePage<SharedProps>().props;
    const [payload, setPayload] = useState(application);
    const [state, setState] = useState(dataState);
    const [checking, setChecking] = useState(false);
    const [checkedAt, setCheckedAt] = useState<string | null>(application.checked_at ?? null);
    const [error, setError] = useState<string | null>(null);
    const mounted = useRef(true);

    const poll = async () => {
        if (checking) return;
        setChecking(true);
        setState('skeleton');
        setError(null);

        try {
            const result = await getJson<PollPayload>('/pending/status', 1);
            if (!mounted.current) return;

            setPayload(result);
            setCheckedAt(result.checked_at);
            setState(result.state === 'none' ? 'empty' : 'success');
        } catch {
            if (!mounted.current) return;
            setError('Could not refresh the application status.');
            setState('error');
        } finally {
            if (mounted.current) setChecking(false);
        }
    };

    useEffect(() => {
        mounted.current = true;

        const timer = window.setInterval(() => {
            void poll();
        }, 60_000);

        return () => {
            mounted.current = false;
            window.clearInterval(timer);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const steps: Step[] = [
        { key: 'submitted', label: 'Submitted', sub: 'details and license received', state: 'done' },
        {
            key: 'review',
            label: 'Admin review',
            sub: 'usually ≤2 business days',
            state: payload.state === 'rejected' ? 'blocked' : payload.state === 'approved' ? 'done' : 'current',
            reason: payload.state === 'rejected' ? 'application not approved' : null,
        },
        {
            key: 'activation',
            label: 'Activation',
            sub: 'portal opens on approval',
            state: payload.state === 'approved' ? 'done' : 'upcoming',
        },
    ];

    const progress = payload.queuePosition && payload.totalPending > 0
        ? Math.max(10, Math.round((payload.queuePosition / payload.totalPending) * 100))
        : null;

    return (
        <GuestLayout title="Application status" chatbot={false}>
            <Head title="Application status — MediPath">
                <meta name="robots" content="noindex" />
            </Head>

            <div className="pending-shell stack">
                <section className="card card-pad pending-hero" aria-labelledby="pending-title">
                    <span className={`badge ${payload.state === 'rejected' ? 'badge-danger' : payload.state === 'approved' ? 'badge-success' : 'badge-warning'}`} style={{ justifyContent: 'center' }}>
                        {payload.state === 'approved' ? 'Approved' : payload.state === 'rejected' ? 'Not approved' : 'Pending review'}
                    </span>
                    <h1 className="mt-m" id="pending-title">
                        {payload.state === 'approved'
                            ? 'Your account is active'
                            : payload.state === 'rejected'
                                ? 'Your application was not approved'
                                : 'Your application is with the clinic admin'}
                    </h1>
                    <p className="muted mt-s" style={{ fontSize: 14 }}>
                        {payload.requestedRoleLabel ? `Requested role: ${payload.requestedRoleLabel} · ` : ''}
                        {payload.submittedAt ? `submitted ${formatDate(payload.submittedAt, { ...settings, withTime: true })}` : 'No application attached'}
                    </p>

                    <div className="mt-l" style={{ textAlign: 'left' }}>
                        <Stepper steps={steps} ariaLabel="Approval progress" />
                    </div>
                </section>

                <DataView
                    name="Data view · Application status (poll /pending/status)"
                    state={state}
                    onRetry={() => void poll()}
                    lastKnownAt={checkedAt}
                    errorMessage={error ?? 'Could not refresh the application status. Your application is unaffected.'}
                    emptyTitle="No pending application"
                    emptyBody="This account already has full access, or no application is attached to the session."
                    emptyAction={<a className="btn btn-primary" href="/login">Go to sign in</a>}
                    skeleton={
                        <div className="card card-pad" aria-hidden="true">
                            <span className="sk sk-line" style={{ width: '40%' }} />
                            <span className="sk sk-box" />
                            <span className="sk" style={{ height: 'var(--mp-touch)' }} />
                        </div>
                    }
                >
                    {payload.state === 'rejected' ? (
                        <div className="stack">
                            <div className="rejection-panel" role="alert">
                                <h2 className="h3">Application not approved</h2>
                                <p className="mt-s" style={{ fontSize: 14 }}>
                                    Reason from the reviewer:
                                    <em> “{payload.reviewReason ?? 'No reason was recorded. Contact the clinic desk.'}”</em>
                                </p>
                                <div className="mp-row mt-m">
                                    <a className="btn btn-primary" href={`/register?role=${payload.requestedRole ?? 'patient'}`}>Re-submit application</a>
                                    <a className="btn btn-ghost" href="/emergency">Contact support</a>
                                </div>
                            </div>
                            <div className="card card-pad pending-status-card">
                                <div className="spread">
                                    <span className="muted" style={{ fontSize: 14 }}>Signed in as</span>
                                    <span className="badge badge-neutral">{payload.statusLabel}</span>
                                </div>
                                <button type="button" className="btn btn-outline" onClick={() => router.post('/logout')}>Sign out</button>
                            </div>
                        </div>
                    ) : payload.state === 'approved' ? (
                        <div className="card card-pad pending-status-card">
                            <p>Your portal is now open. The destination is built from your database role.</p>
                            <a className="btn btn-primary" href={payload.dashboardUrl}>Go to my workspace</a>
                            <button type="button" className="btn btn-ghost" onClick={() => router.post('/logout')}>Sign out</button>
                        </div>
                    ) : (
                        <div className="card card-pad pending-status-card">
                            {payload.queuePosition !== null ? (
                                <div className="spread">
                                    <span className="muted" style={{ fontSize: 14 }}>Queue position</span>
                                    <strong className="tabular" style={{ fontSize: 20 }}>
                                        {payload.queuePosition} of {payload.totalPending}
                                    </strong>
                                </div>
                            ) : null}

                            {progress !== null ? (
                                <div
                                    className="progress"
                                    role="progressbar"
                                    aria-valuenow={progress}
                                    aria-valuemin={0}
                                    aria-valuemax={100}
                                    aria-label="Typical review progress"
                                >
                                    <span style={{ width: `${progress}%` }} />
                                </div>
                            ) : null}

                            <div className="pending-actions">
                                <button type="button" className="btn btn-primary" onClick={() => void poll()} disabled={checking}>
                                    {checking ? <Spinner label="Checking status" /> : <span aria-hidden="true">⟳</span>}
                                    {checking ? 'Checking…' : 'Check status'}
                                </button>
                                <button type="button" className="btn btn-quiet" onClick={() => router.post('/logout')}>Sign out</button>
                            </div>

                            <p className="caption" role="status" aria-live="polite">
                                {checkedAt ? `Last check: ${formatDate(checkedAt, { ...settings, withTime: true })}` : 'Checking automatically every 60 seconds.'}
                                {' · '}Nothing to do — the clinic emails you when a decision is recorded.
                            </p>
                        </div>
                    )}

                </DataView>
            </div>
        </GuestLayout>
    );
}