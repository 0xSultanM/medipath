/**
 * Admin · Approvals & verification — prototypes/admin/03-approvals.html.
 * Sign-up queue with private credential preview + rejection reasons, and the
 * three doctor verification decisions. Decisions are atomic server-side and
 * always write notification + immutable audit entries.
 */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { DataState, SharedProps } from '@/types';
import type { AdminApprovalRow, AdminVerificationRow } from '@/types/admin';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { DataTable, type DataColumn } from '@/Components/Data/DataTable';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { AdminModal } from '@/Components/Admin/AdminModal';
import { cn, formatDate } from '@/lib/format';

interface ApprovalsProps {
    tab: 'signups' | 'verification';
    pending: AdminApprovalRow[];
    doctors: AdminVerificationRow[];
    counts: { signups: number; verification: number };
    dataState: DataState;
    lastKnownAt: string;
}

export default function AdminApprovals({ tab, pending, doctors, counts, dataState, lastKnownAt }: ApprovalsProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(dataState);
    const [preview, setPreview] = useState<{ url: string; title: string } | null>(null);
    const [rejectTarget, setRejectTarget] = useState<AdminApprovalRow | null>(null);
    const [decision, setDecision] = useState<{ doctor: AdminVerificationRow; type: 'resubmit' | 'revoke' } | null>(null);

    const switchTab = (next: 'signups' | 'verification') => {
        router.get('/admin/approvals', next === 'verification' ? { tab: 'verification' } : {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const approveDoctor = (doctor: AdminVerificationRow) => {
        router.post(`/admin/doctors/${doctor.id}/verification`, { decision: 'approve' }, { preserveScroll: true });
    };

    const columns: Array<DataColumn<AdminVerificationRow>> = [
        {
            key: 'doctor',
            label: 'Doctor',
            render: (doctor) => (
                <div>
                    <strong>{doctor.name}</strong>
                    <div className="caption mono">{doctor.licenseNumber}</div>
                    <div className="caption">{doctor.email}</div>
                </div>
            ),
        },
        { key: 'specialty', label: 'Specialty', render: (doctor) => doctor.specialty ?? '—' },
        {
            key: 'status',
            label: 'Status',
            render: (doctor) => (
                <div className="stack-sm" style={{ gap: 4 }}>
                    <StatusBadge family="verification" status={doctor.verificationStatus} label={doctor.verificationLabel ?? undefined} />
                    {doctor.verifiedAt ? <span className="caption">since {formatDate(doctor.verifiedAt, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })}</span> : null}
                    {doctor.reviewReason ? <span className="caption">{doctor.reviewReason}</span> : null}
                </div>
            ),
        },
        {
            key: 'decisions',
            label: 'Decisions',
            render: (doctor) => (
                <div className="mp-row" style={{ flexWrap: 'wrap', gap: 6 }}>
                    {doctor.credentialAvailable ? (
                        <button
                            type="button"
                            className="btn btn-ghost btn-sm"
                            onClick={() => setPreview({ url: doctor.credentialUrl as string, title: `Credential — ${doctor.name}` })}
                        >
                            Preview
                        </button>
                    ) : null}
                    {doctor.canVerify ? (
                        <>
                            <button type="button" className="btn btn-primary btn-sm" onClick={() => approveDoctor(doctor)}>
                                {doctor.verificationStatus === 'revoked' ? 'Re-verify' : 'Approve & verify'}
                            </button>
                            <button type="button" className="btn btn-outline btn-sm" onClick={() => setDecision({ doctor, type: 'resubmit' })}>
                                Request resubmission
                            </button>
                        </>
                    ) : null}
                    <button
                        type="button"
                        className="btn btn-ghost btn-sm"
                        style={{ color: 'var(--mp-danger-text)' }}
                        onClick={() => setDecision({ doctor, type: 'revoke' })}
                    >
                        Revoke…
                    </button>
                </div>
            ),
        },
    ];

    return (
        <RoleLayout
            role="admin"
            title="Approvals & verification"
            subtitle={`${counts.signups} sign-up${counts.signups === 1 ? '' : 's'} · ${counts.verification} verification${counts.verification === 1 ? '' : 's'} awaiting action`}
            activeHref="/admin/approvals"
        >
            <Head title="Approvals & verification — MediPath" />

            <DataView
                name="Data view · Approval queues"
                state={state}
                onStateChange={setState}
                showSwitch
                onRetry={() => router.reload()}
                lastKnownAt={lastKnownAt}
                emptyTitle={tab === 'signups' ? 'No sign-ups waiting' : 'Verification queue clear'}
                emptyBody={tab === 'signups'
                    ? 'New credential submissions appear here in real time.'
                    : 'Every doctor on file has a settled verification state.'}
                errorMessage="Queue load failed — decisions are never lost; the database queue is the source of truth."
            >
                <div className="tabs" role="tablist" aria-label="Approval queues">
                    <button
                        type="button"
                        role="tab"
                        aria-selected={tab === 'signups'}
                        onClick={() => switchTab('signups')}
                    >
                        Pending sign-ups <span className="cnt tabular">{counts.signups}</span>
                    </button>
                    <button
                        type="button"
                        role="tab"
                        aria-selected={tab === 'verification'}
                        onClick={() => switchTab('verification')}
                    >
                        Doctor verification <span className="cnt tabular">{counts.verification}</span>
                    </button>
                </div>

                {tab === 'signups' ? (
                    <div className="stack mt-m">
                        {pending.map((approval) => (
                            <article className="card card-pad" key={approval.id} aria-labelledby={`approval-${approval.id}`}>
                                <div className="spread" style={{ flexWrap: 'wrap', gap: 'var(--mp-space-md)' }}>
                                    <div>
                                        <strong id={`approval-${approval.id}`}>{approval.applicantName}</strong>
                                        {' · '}
                                        <span>{approval.requestedRoleLabel}</span>
                                        {approval.specialty ? <> · <span>{approval.specialty}</span></> : null}
                                        {' · requested '}
                                        <time className="caption" dateTime={approval.submittedAt ?? undefined}>
                                            {formatDate(approval.submittedAt, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat, withTime: true })}
                                        </time>
                                        <div className="caption">
                                            license <span className="mono">{approval.licenseNumber ?? '—'}</span>
                                            {' · '}{approval.licenseAuthority ?? 'authority not provided'}
                                            {approval.experienceYears ? ` · ${approval.experienceYears} yrs` : ''}
                                        </div>
                                        <div className="caption mono">{approval.email}</div>
                                    </div>
                                    <div className="mp-row">
                                        {approval.credentialUrl ? (
                                            <button
                                                type="button"
                                                className="btn btn-quiet"
                                                onClick={() => setPreview({ url: approval.credentialUrl as string, title: `Credential — ${approval.applicantName}` })}
                                            >
                                                Preview credential ⧉
                                            </button>
                                        ) : (
                                            <span className="caption">No credential uploaded</span>
                                        )}
                                    </div>
                                </div>

                                <div className="card card-pad mt-m" style={{ background: 'var(--mp-surface-muted)' }}>
                                    <p className="caption" style={{ margin: 0 }}>
                                        Credential document · private-disk preview renders in an admin-only dialog; each open is written to the activity log.
                                    </p>
                                </div>

                                <div className="mp-row mt-m" style={{ flexWrap: 'wrap' }}>
                                    <button
                                        type="button"
                                        className="btn btn-primary"
                                        onClick={() => router.post(`/admin/approvals/${approval.id}/approve`, {}, { preserveScroll: true })}
                                    >
                                        Approve
                                    </button>
                                    <button type="button" className="btn btn-danger-outline" onClick={() => setRejectTarget(approval)}>
                                        Reject with reason…
                                    </button>
                                </div>
                            </article>
                        ))}
                        <p className="caption">
                            Approving is atomic: status → approved, profile activation, queue item closed, notification + activity log in one transaction.
                        </p>
                    </div>
                ) : (
                    <div className="mt-m">
                        <DataTable
                            caption="Doctors and their verification state"
                            className="tbl-cards-md"
                            columns={columns}
                            rows={doctors}
                            getRowKey={(doctor) => doctor.id}
                            state="success"
                        />
                        <ul className="list-quiet mt-m" style={{ fontSize: 13 }}>
                            <li><strong>Approve &amp; verify</strong> → booking opens for patients; the license snapshot stays on the profile.</li>
                            <li><strong>Request resubmission</strong> → stays under review with an itemised reason shown in the doctor workspace/holding screen.</li>
                            <li><strong>Revoke</strong> → booking hides instantly; completed records remain; future prescription actions blocked; reason logged.</li>
                        </ul>
                    </div>
                )}
            </DataView>

            <AdminModal
                open={Boolean(preview)}
                title={preview?.title ?? 'Credential preview'}
                subtitle="Private credentials disk · authenticated admin only · no-store"
                icon="🔒"
                wide
                onClose={() => setPreview(null)}
                footer={<button type="button" className="btn btn-quiet" onClick={() => setPreview(null)}>Close preview</button>}
            >
                {preview ? (
                    <iframe
                        title={preview.title}
                        src={preview.url}
                        style={{ width: '100%', height: '70vh', border: '1px solid var(--mp-border)', borderRadius: 'var(--mp-radius-card)', background: 'var(--mp-surface)' }}
                        referrerPolicy="no-referrer"
                    />
                ) : null}
            </AdminModal>

            <RejectApprovalModal approval={rejectTarget} onClose={() => setRejectTarget(null)} />
            <DoctorDecisionModal decision={decision} onClose={() => setDecision(null)} />
        </RoleLayout>
    );
}

function RejectApprovalModal({ approval, onClose }: { approval: AdminApprovalRow | null; onClose: () => void }) {
    const form = useForm({ review_reason: '' });

    useEffect(() => {
        if (approval) {
            form.reset();
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [approval]);

    return (
        <AdminModal
            open={Boolean(approval)}
            title={`Reject application — ${approval?.applicantName ?? ''}`}
            subtitle="The reason is shown to the applicant on the holding screen and written to the audit log."
            icon="✖"
            danger
            onClose={onClose}
            footer={
                <>
                    <button type="button" className="btn btn-quiet" onClick={onClose}>Cancel</button>
                    <button type="submit" form="reject-approval-form" className="btn btn-danger" disabled={form.processing}>
                        {form.processing ? 'Sending…' : 'Send rejection'}
                    </button>
                </>
            }
        >
            <form
                id="reject-approval-form"
                onSubmit={(event) => {
                    event.preventDefault();
                    if (!approval) return;
                    form.post(`/admin/approvals/${approval.id}/reject`, { preserveScroll: true, onSuccess: onClose });
                }}
            >
                <div className="field">
                    <label className="label req" htmlFor="reject-reason">Rejection reason (required, shown to applicant)</label>
                    <textarea
                        id="reject-reason"
                        className="textarea"
                        style={{ minHeight: 90 }}
                        value={form.data.review_reason}
                        onChange={(event) => form.setData('review_reason', event.target.value)}
                        placeholder="e.g., license page 2 missing — resubmit a complete document"
                    />
                    <span className="hint">Minimum 10 characters. The applicant can sign in to read this reason.</span>
                    {form.errors.review_reason ? <span className="field-error" role="alert">{form.errors.review_reason}</span> : null}
                </div>
            </form>
        </AdminModal>
    );
}

function DoctorDecisionModal({
    decision,
    onClose,
}: {
    decision: { doctor: AdminVerificationRow; type: 'resubmit' | 'revoke' } | null;
    onClose: () => void;
}) {
    const form = useForm({ decision: 'resubmit', reason: '' });

    useEffect(() => {
        if (decision) {
            form.setData({ decision: decision.type, reason: '' });
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [decision]);

    const copy = decision?.type === 'revoke'
        ? {
            title: `Revoke verification — ${decision?.doctor.name ?? ''}`,
            subtitle: 'Bookings hide immediately. Existing completed records remain readable forever.',
            action: 'Revoke verification',
        }
        : {
            title: `Request resubmission — ${decision?.doctor.name ?? ''}`,
            subtitle: 'The doctor returns to the holding screen with your itemised reason.',
            action: 'Request resubmission',
        };

    return (
        <AdminModal
            open={Boolean(decision)}
            title={copy.title}
            subtitle={copy.subtitle}
            icon={decision?.type === 'revoke' ? '🚫' : '🔁'}
            danger={decision?.type === 'revoke'}
            onClose={onClose}
            footer={
                <>
                    <button type="button" className="btn btn-quiet" onClick={onClose}>Cancel</button>
                    <button
                        type="submit"
                        form="doctor-decision-form"
                        className={cn('btn', decision?.type === 'revoke' ? 'btn-danger' : 'btn-primary')}
                        disabled={form.processing}
                    >
                        {form.processing ? 'Saving…' : copy.action}
                    </button>
                </>
            }
        >
            <form
                id="doctor-decision-form"
                onSubmit={(event) => {
                    event.preventDefault();
                    if (!decision) return;
                    form.post(`/admin/doctors/${decision.doctor.id}/verification`, { preserveScroll: true, onSuccess: onClose });
                }}
            >
                <div className="field">
                    <label className="label req" htmlFor="doctor-reason">Reason (required, visible to the doctor)</label>
                    <textarea
                        id="doctor-reason"
                        className="textarea"
                        rows={4}
                        value={form.data.reason}
                        onChange={(event) => form.setData('reason', event.target.value)}
                        placeholder="e.g., license expired 12 Nov — upload the renewed certificate"
                    />
                    {form.errors.reason ? <span className="field-error" role="alert">{form.errors.reason}</span> : null}
                </div>
            </form>
        </AdminModal>
    );
}