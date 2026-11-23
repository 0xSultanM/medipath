/**
 * Doctor · My reviews — prototypes/doctor/08-reviews.html.
 * Doctors see their reviews and moderation state; only admin-approved reviews
 * appear publicly. Ratings remain private when a review is rejected.
 */
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { DataState, SharedProps } from '@/types';
import type { DoctorReviewRow } from '@/types/doctor';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { formatDate } from '@/lib/format';

interface ReviewsProps {
    summary: { average: number | null; approved: number; pending: number; rejected: number; distribution: Record<string, number> };
    reviews: DoctorReviewRow[];
    dataState: DataState;
    lastKnownAt: string;
}

export default function DoctorReviews({ summary, reviews, dataState, lastKnownAt }: ReviewsProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(dataState);
    const total = Math.max(1, summary.approved);

    return (
        <RoleLayout role="doctor" title="My reviews" subtitle={`${summary.approved} approved · ${summary.pending} pending moderation`} activeHref="/doctor/reviews">
            <Head title="My reviews — MediPath" />
            <DataView name="Data view · Reviews" state={state} onStateChange={setState} showSwitch onRetry={() => router.reload()} lastKnownAt={lastKnownAt}
                emptyTitle="No reviews yet" emptyBody="Patients can review only after a completed appointment — nothing you need to do."
                errorMessage="Reviews failed to load.">
                <div className="grid" style={{ alignItems: 'start' }}>
                    <section className="c-12 md-c-4 card card-pad">
                        <div className="mp-row"><span className="money" style={{ fontSize: 36 }}>{summary.average ?? '—'}</span><span aria-hidden="true" style={{ fontSize: 20, color: 'var(--mp-warning)' }}>{'★'.repeat(Math.round(summary.average ?? 0))}</span></div>
                        <p className="caption mt-s">{summary.approved} approved · {summary.pending} pending · {summary.rejected} rejected</p>
                        <div className="mt-m">
                            {[5, 4, 3, 2, 1].map((star) => {
                                const count = Number(summary.distribution?.[String(star)] ?? 0);
                                return (
                                    <div className="mp-row" key={star} style={{ gap: 8 }}>
                                        <span style={{ width: 34 }}>{star} ★</span>
                                        <span style={{ flex: 1, height: 8, background: 'var(--mp-surface-muted)', borderRadius: 999, overflow: 'hidden' }}><span style={{ display: 'block', width: `${(count / total) * 100}%`, height: '100%', background: 'var(--mp-warning)' }} /></span>
                                        <span className="tabular caption" style={{ width: 24, textAlign: 'right' }}>{count}</span>
                                    </div>
                                );
                            })}
                        </div>
                    </section>
                    <section className="c-12 md-c-8 card card-pad">
                        {reviews.length === 0 ? <p className="muted">No reviews yet.</p> : reviews.map((review) => (
                            <article key={review.id} className="review" style={{ borderBottom: '1px solid var(--mp-border)', paddingBlock: 'var(--mp-space-md)' }}>
                                <div className="mp-row">
                                    <strong>{review.patientName}</strong>
                                    <span aria-label={`${review.rating} of 5`} style={{ color: 'var(--mp-warning)' }}>{'★'.repeat(review.rating)}{'☆'.repeat(5 - review.rating)}</span>
                                    <StatusBadge family="moderation" status={review.status} label={review.statusLabel} size="sm" />
                                    <time className="caption mla">{formatDate(review.createdAt, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })}</time>
                                </div>
                                <p className="mt-s" style={{ fontSize: 14 }}>{review.comment || <span className="muted">No comment.</span>}</p>
                                {review.moderationReason ? <p className="caption">Moderation note: {review.moderationReason}</p> : null}
                            </article>
                        ))}
                        <p className="caption mt-m">Only admin-approved reviews are public. You cannot reply publicly; contact the admin with factual corrections.</p>
                    </section>
                </div>
            </DataView>
        </RoleLayout>
    );
}