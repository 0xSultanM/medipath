/**
 * Public doctor profile — prototypes/guest/03-doctor-profile.html.
 * Verified badge, leave-aware 7-day availability table (§8), approved-only
 * reviews + distribution (§9), guest booking gate.
 */
import { Head, router, usePage } from '@inertiajs/react';
import type { AvailabilityRow, BookingGate, DoctorSummary, PageMeta, RatingSummaryData, ReviewItem, SharedProps } from '@/types';
import { GuestLayout } from '@/Layouts/GuestLayout';
import { DataView } from '@/Components/Data/DataView';
import { Money } from '@/Components/ui/Money';
import { RatingSummary, Stars } from '@/Components/Data/StarRating';
import { Pager } from '@/Components/Data/DataTable';
import { EmptyState } from '@/Components/Feedback/EmptyState';
import { formatDate, relativeTime } from '@/lib/format';

interface DoctorProfileProps {
    doctor: DoctorSummary;
    availability: AvailabilityRow[];
    availabilityState: 'success' | 'skeleton' | 'empty' | 'error';
    reviews: { data: ReviewItem[]; meta: PageMeta };
    ratingSummary: RatingSummaryData;
    reviewsState: 'success' | 'skeleton' | 'empty' | 'error';
    bookingGate: BookingGate;
    breadcrumbSpecialty: string;
}

const STATUS_LABELS: Record<AvailabilityRow['status'], { label: string; tone: string }> = {
    available: { label: 'Available', tone: 'badge-success' },
    leave: { label: 'On leave', tone: 'badge-warning' },
    full: { label: 'Fully booked', tone: 'badge-danger' },
    closed: { label: 'Clinic closed', tone: 'badge-neutral' },
};

export default function DoctorProfile({
    doctor,
    availability,
    availabilityState,
    reviews,
    ratingSummary,
    reviewsState,
    bookingGate,
    breadcrumbSpecialty,
}: DoctorProfileProps) {
    const { settings } = usePage<SharedProps>().props;

    const goToReviewsPage = (page: number) => {
        router.get(window.location.pathname, { reviews_page: page }, { preserveScroll: true, preserveState: true });
    };

    return (
        <GuestLayout title={doctor.name} activeHref="/doctors">
            <Head title={`${doctor.name} · ${doctor.specialty} — MediPath`}>
                <meta name="description" content={doctor.bio ?? `Book a consultation with ${doctor.name}, ${doctor.specialty}.`} />
            </Head>

            <nav className="breadcrumb" aria-label="Breadcrumb">
                <a href="/doctors">Find Doctors</a> <span aria-hidden="true">›</span>
                <a href={`/doctors?specialty=${encodeURIComponent(doctor.specialty.toLowerCase().replaceAll(' ', '-'))}`}>{breadcrumbSpecialty}</a>
                <span aria-hidden="true">›</span>
                <span aria-current="page">{doctor.name}</span>
            </nav>

            <section className="card card-pad mt-b" aria-labelledby="pn">
                <div className="grid">
                    <div className="c-12 md-c-8 profile-header-main">
                        <span className="avatar avatar-lg avatar-initials profile-avatar" aria-hidden="true">{doctor.initials}</span>
                        <div style={{ minWidth: 0 }}>
                            <h1 id="pn" className="doc-name profile-title">
                                {doctor.name}
                                <span className="badge badge-verified">✓ Verified doctor</span>
                            </h1>
                            <div className="mp-row mt-s">
                                <span className="chip">{doctor.specialty}</span>
                                <span className="chip">{doctor.experienceYears} yrs experience</span>
                                {doctor.room ? <span className="chip">{doctor.room}</span> : null}
                            </div>
                            <p className="caption mt-s">
                                License {doctor.licenseMasked} · {doctor.licenseAuthority}
                                {doctor.verifiedAt ? ` · verified ${formatDate(doctor.verifiedAt, settings)}` : ''}
                            </p>
                            <div className="mp-row mt-m">
                                <Stars
                                    value={doctor.rating}
                                    label={`Rated ${doctor.rating} out of 5 from ${doctor.reviewCount} approved reviews`}
                                    size="lg"
                                />
                                <strong className="tabular">{doctor.rating.toFixed(1)}</strong>
                                <span className="muted caption">({doctor.reviewCount} approved reviews)</span>
                            </div>
                        </div>
                    </div>

                    <div className="c-12 md-c-4 stack" style={{ alignContent: 'start' }}>
                        <div className="card card-pad profile-fee-card">
                            <div className="spread">
                                <span className="muted" style={{ fontSize: 14 }}>Consultation fee</span>
                                <Money formatted={doctor.feeFormatted} size="lg" />
                            </div>
                            <div className={`doc-next mt-s ${doctor.hasSlots ? '' : 'is-full'}`}>
                                {doctor.hasSlots ? `⚡ Next available: ${doctor.nextAvailable}` : 'No open slots in the next 14 days'}
                            </div>
                        </div>

                        {bookingGate.disabled ? (
                            <button type="button" className="btn btn-primary btn-block" disabled title={bookingGate.note}>
                                {bookingGate.label}
                            </button>
                        ) : (
                            <a className="btn btn-primary btn-block" href={bookingGate.href ?? '#'}>{bookingGate.label}</a>
                        )}
                        <a className="btn btn-outline btn-block" href="#avail">View availability</a>
                        <p className="caption">{bookingGate.note}</p>
                    </div>
                </div>

                <div className="divider" />
                <h2 className="h4">About</h2>
                <p className="lead mt-s" style={{ fontSize: 16, lineHeight: '26px' }}>{doctor.bio ?? 'Profile details are being completed.'}</p>
                <p className="caption mt-s">{doctor.clinic}{doctor.address ? ` · ${doctor.address}` : ''}</p>
            </section>

            <section className="stack mt-l" id="avail">
                <h2>Availability — next 7 days</h2>
                <DataView
                    name="Data view · Availability (schedules − leave − bookings)"
                    state={availabilityState}
                    onRetry={() => router.reload()}
                    lastKnownAt={new Date(Date.now() - 5 * 60 * 1000).toISOString()}
                    emptyTitle="No published availability"
                    emptyBody="The doctor has not published weekly hours yet, or every visible day is on leave."
                    emptyAction={<a className="btn btn-outline" href="/doctors">Back to directory</a>}
                    skeleton={
                        <div className="card card-pad" aria-hidden="true">
                            <span className="sk sk-line" style={{ width: '30%' }} />
                            {[0, 1, 2, 3, 4, 5, 6].map((row) => (
                                <span className="sk sk-box" style={{ height: 46 }} key={row} />
                            ))}
                        </div>
                    }
                >
                    <div className="tbl-wrap">
                        <table className="tbl">
                            <caption className="sr-only">Seven-day availability for {doctor.name}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Day</th>
                                    <th scope="col">Window</th>
                                    <th scope="col" className="num">Open slots</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {availability.map((row) => {
                                    const status = STATUS_LABELS[row.status];
                                    return (
                                        <tr key={row.date}>
                                            <td data-label="Day">
                                                <strong>{row.label}</strong>
                                                {row.is_today ? <span className="caption"> (today)</span> : null}
                                            </td>
                                            <td data-label="Window" className="tabular">{row.windows}</td>
                                            <td data-label="Open slots" className="num">{row.open_slots}</td>
                                            <td data-label="Status">
                                                <span className={`badge ${status.tone}`}>
                                                    <span className="dot" aria-hidden="true" />
                                                    {status.label}
                                                </span>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    <p className="availability-note mt-s">
                        Availability is computed live. Leave removes the whole day; booked slots are never offered twice.
                    </p>
                </DataView>
            </section>

            <section className="stack mt-l">
                <h2>Approved reviews</h2>
                <DataView
                    name="Data view · Reviews (approved only)"
                    state={reviewsState}
                    onRetry={() => router.reload()}
                    lastKnownAt={new Date(Date.now() - 30 * 60 * 1000).toISOString()}
                    emptyTitle="No approved reviews yet"
                    emptyBody="Reviews appear here after a completed appointment and admin moderation."
                    skeleton={
                        <div className="grid" aria-hidden="true">
                            <div className="c-12 md-c-4 card card-pad"><RatingSummary average={0} total={0} distribution={{}} state="skeleton" /></div>
                            <div className="c-12 md-c-8 card card-pad">
                                {[0, 1, 2].map((row) => (
                                    <div key={row} style={{ marginBottom: 'var(--mp-space-lg)' }}>
                                        <span className="sk sk-line" style={{ width: '40%' }} />
                                        <span className="sk sk-line" style={{ width: '90%' }} />
                                        <span className="sk sk-line" style={{ width: '70%' }} />
                                    </div>
                                ))}
                            </div>
                        </div>
                    }
                >
                    <div className="grid">
                        <div className="c-12 md-c-4 card card-pad">
                            <RatingSummary
                                average={ratingSummary.average}
                                total={ratingSummary.total}
                                distribution={ratingSummary.distribution}
                                state="success"
                            />
                            <p className="caption mt-s">
                                Based on {ratingSummary.total} approved reviews. Sign in after a completed appointment to leave one.
                            </p>
                        </div>

                        <div className="c-12 md-c-8 card card-pad">
                            {reviews.data.length > 0 ? (
                                <div className="review-list">
                                    {reviews.data.map((review) => (
                                        <article className="review-item" key={review.id}>
                                            <div className="review-head">
                                                <span className="avatar avatar-sm avatar-initials" aria-hidden="true">{review.initials}</span>
                                                <strong>{review.patientName}</strong>
                                                <Stars value={review.rating} label={`${review.rating} out of 5`} />
                                                <time className="caption review-time" dateTime={review.createdAt ?? undefined}>
                                                    {relativeTime(review.createdAt)}
                                                </time>
                                            </div>
                                            <p className="review-body">{review.comment}</p>
                                        </article>
                                    ))}
                                </div>
                            ) : (
                                <EmptyState
                                    glyph="⭐"
                                    title="No approved reviews yet"
                                    body="Reviews are published after admin moderation."
                                />
                            )}

                            {reviews.meta.last_page > 1 ? (
                                <Pager page={reviews.meta} onPageChange={goToReviewsPage} />
                            ) : null}
                        </div>
                    </div>
                </DataView>
            </section>
        </GuestLayout>
    );
}