/**
 * DoctorCard — COMPONENT-SPEC §7 (Verified=Yes|No, Photo=Image|Initials,
 * CTA=Guest|Patient, Availability=HasSlots|FullyBooked, card-level error).
 * Reproduces prototypes/guest/01-landing + 02-directory + 03-doctor-profile.
 */
import type { ReactNode } from 'react';
import type { DoctorSummary } from '@/types';
import { cn } from '@/lib/format';
import { Money } from '@/Components/ui/Money';
import { Stars } from '@/Components/Data/StarRating';

interface DoctorCardProps {
    doctor: DoctorSummary;
    cta?: 'guest' | 'patient';
    state?: 'success' | 'skeleton' | 'error';
    onRetry?: () => void;
    onBook?: () => void;
    bookHref?: string;
    profileHref?: string;
    actionSlot?: ReactNode;
}

export function DoctorCard({
    doctor,
    cta = 'guest',
    state = 'success',
    onRetry,
    onBook,
    bookHref,
    profileHref,
    actionSlot,
}: DoctorCardProps) {
    if (state === 'skeleton') {
        return (
            <article className="card doc-card" aria-busy="true">
                <div className="doc-top">
                    <span className="sk sk-av" aria-hidden="true" />
                    <div style={{ flex: 1 }}>
                        <span className="sk sk-title" aria-hidden="true" />
                        <span className="sk sk-line" style={{ width: '40%' }} aria-hidden="true" />
                    </div>
                </div>
                <span className="sk sk-line" aria-hidden="true" />
                <span className="sk sk-line" style={{ width: '70%' }} aria-hidden="true" />
                <span className="sk sk-box" style={{ height: 44 }} aria-hidden="true" />
                <span className="sk" style={{ height: 'var(--mp-touch)' }} aria-hidden="true" />
            </article>
        );
    }

    const profile = profileHref ?? '#';
    const bookLink = bookHref ?? '/login';

    return (
        <article className={cn('card doc-card card-hover')} aria-label={doctor.name}>
            <div className="doc-top">
                {doctor.photoUrl ? (
                    <img className="avatar" src={doctor.photoUrl} alt="" />
                ) : (
                    <span className="avatar avatar-initials" aria-hidden="true">{doctor.initials}</span>
                )}

                <div style={{ minWidth: 0 }}>
                    <h3 className="h3 doc-name">
                        <a href={profile}>{doctor.name}</a>
                        {doctor.verified ? (
                            <span className="badge badge-verified" title="License verified by clinic admin">
                                ✓ Verified
                            </span>
                        ) : (
                            <span className="badge badge-info">Under review</span>
                        )}
                    </h3>

                    <div className="doc-meta">
                        <span className="chip" style={{ minHeight: 28, padding: '2px 10px' }}>
                            {doctor.specialty}
                        </span>
                    </div>

                    <div className="doc-meta mt-s">
                        {doctor.experienceYears} yrs experience
                        {doctor.clinic ? ` · ${doctor.clinic}` : ''}
                    </div>
                </div>
            </div>

            {state === 'error' ? (
                <div className="doc-error" role="alert">
                    <strong>Details unavailable.</strong> Fee and rating could not be loaded.
                    <button type="button" className="btn btn-ghost btn-sm" onClick={onRetry}>
                        Retry
                    </button>
                </div>
            ) : (
                <div className="doc-stats">
                    <div className="doc-stat">
                        <Stars
                            value={doctor.rating}
                            label={`Rated ${doctor.rating} out of 5, ${doctor.reviewCount} reviews`}
                        />
                        <span className="muted tabular">
                            {doctor.rating.toFixed(1)} <span className="caption">({doctor.reviewCount})</span>
                        </span>
                    </div>
                    <div className="doc-stat">
                        <span className="muted">Consultation fee</span>
                        <Money formatted={doctor.feeFormatted} />
                    </div>
                </div>
            )}

            <div className={cn('doc-next', !doctor.hasSlots && 'is-full')}>
                {doctor.hasSlots ? (
                    <>⚡ Next available: {doctor.nextAvailable ?? 'This week'}</>
                ) : (
                    <>🗓 No open slots this week — join the waitlist</>
                )}
            </div>

            <div className="doc-actions">
                <a className="btn btn-outline" href={profile}>
                    View profile
                </a>

                {actionSlot ??
                    (doctor.hasSlots ? (
                        cta === 'guest' ? (
                            <a
                                className="btn btn-primary"
                                href={bookLink}
                                title="Sign in required to book"
                                onClick={onBook}
                            >
                                Book
                            </a>
                        ) : (
                            <a className="btn btn-primary" href={bookLink} onClick={onBook}>
                                Book
                            </a>
                        )
                    ) : (
                        <button
                            type="button"
                            className="btn btn-quiet"
                            disabled
                            title="No leave-aware slots in the next 7 days"
                        >
                            Join waitlist
                        </button>
                    ))}
            </div>
        </article>
    );
}