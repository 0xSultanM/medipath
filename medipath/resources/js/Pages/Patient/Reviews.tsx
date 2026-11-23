/**
 * Patient · Reviews — completed visits can be rated once; moderation status is
 * visible to the patient, and rejected/hidden reviews keep their private rating.
 */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { DataState, SharedProps } from '@/types';
import type { PatientReview } from '@/types/patient';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { DataTable, type DataColumn } from '@/Components/Data/DataTable';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { formatDate } from '@/lib/format';

interface ReviewableAppointment {
    id: number;
    code: string;
    date: string | null;
    doctorName: string | null;
    doctorCode: string | null;
    specialty: string | null;
}

interface ReviewsProps {
    reviewable: ReviewableAppointment[];
    submitted: PatientReview[];
    selectedAppointmentId: number | null;
    selectedAppointment: ReviewableAppointment | null;
    counts: { awaitingReview: number; submitted: number; published: number; pendingModeration: number };
    dataState: DataState;
    lastKnownAt: string;
}

export default function PatientReviews({ reviewable, submitted, selectedAppointmentId, selectedAppointment, counts, dataState, lastKnownAt }: ReviewsProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(dataState);
    const [selected, setSelected] = useState<number | null>(selectedAppointmentId);
    const form = useForm({ appointment_id: selectedAppointmentId ?? 0, rating: 5, comment: '' });

    useEffect(() => {
        form.setData({ appointment_id: selected ?? 0, rating: form.data.rating, comment: form.data.comment });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selected]);

    const columns: Array<DataColumn<PatientReview>> = [
        { key: 'doctor', label: 'Doctor', render: (row) => <div><strong>{row.doctorName}</strong><div className="caption">{row.specialty} · {row.appointmentCode}</div></div> },
        { key: 'rating', label: 'Rating', render: (row) => <span className="tabular" aria-label={`${row.rating} of 5`}>{'★'.repeat(row.rating)}{'☆'.repeat(5 - row.rating)}</span> },
        { key: 'comment', label: 'Comment', render: (row) => <span className="caption">{row.comment || '—'}</span> },
        { key: 'status', label: 'Status', render: (row) => <StatusBadge family="moderation" status={row.status} label={row.statusLabel} /> },
        { key: 'date', label: 'Submitted', render: (row) => <span className="tabular">{formatDate(row.createdAt, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })}</span> },
    ];

    return (
        <RoleLayout role="patient" title="Reviews" subtitle={`${counts.awaitingReview} visit(s) awaiting review · ${counts.published} published`} activeHref="/patient/reviews">
            <Head title="Reviews — MediPath" />

            <DataView
                name="Data view · Review invitations & history"
                state={state}
                onStateChange={setState}
                showSwitch
                onRetry={() => router.reload()}
                lastKnownAt={lastKnownAt}
                emptyTitle="No reviews yet"
                emptyBody="After a completed visit, you can rate the doctor. Reviews appear publicly after moderation."
                errorMessage="Reviews failed to load. Nothing was lost; retry is safe."
            >
                <div className="grid" style={{ alignItems: 'start' }}>
                    <div className="c-12 lg-c-5 stack">
                        <section className="card card-pad" aria-labelledby="review-form-title">
                            <h2 className="h4" id="review-form-title">Leave a review</h2>
                            {reviewable.length === 0 ? (
                                <p className="muted mt-s">No completed visit is waiting for a review.</p>
                            ) : (
                                <>
                                    <div className="field mt-s">
                                        <label className="label" htmlFor="review-appointment">Visit</label>
                                        <select
                                            id="review-appointment"
                                            className="select"
                                            value={selected ?? ''}
                                            onChange={(event) => {
                                                const id = Number(event.target.value);
                                                setSelected(id);
                                                router.get('/patient/reviews', { appointment: id }, { preserveState: true, preserveScroll: true, replace: true });
                                            }}
                                        >
                                            {reviewable.map((appointment) => (
                                                <option key={appointment.id} value={appointment.id}>
                                                    {appointment.doctorName} · {appointment.specialty} · {formatDate(appointment.date, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="field">
                                        <span className="label req">Rating</span>
                                        <div className="mp-row" role="radiogroup" aria-label="Rating">
                                            {[1, 2, 3, 4, 5].map((rating) => (
                                                <button key={rating} type="button" className={`btn ${form.data.rating === rating ? 'btn-primary' : 'btn-outline'}`} aria-pressed={form.data.rating === rating} onClick={() => form.setData('rating', rating)}>
                                                    {rating} ★
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                    <div className="field">
                                        <label className="label" htmlFor="review-comment">Comment (optional)</label>
                                        <textarea id="review-comment" className="textarea" rows={4} value={form.data.comment} onChange={(event) => form.setData('comment', event.target.value)} placeholder="What went well? What could improve?" />
                                        {form.errors.appointment_id ? <span className="field-error">{form.errors.appointment_id}</span> : null}
                                    </div>
                                    <button
                                        type="button"
                                        className="btn btn-primary"
                                        disabled={!selected || form.processing}
                                        onClick={() => form.post('/patient/reviews', { preserveScroll: true })}
                                    >
                                        {form.processing ? 'Submitting…' : 'Submit review'}
                                    </button>
                                    {selectedAppointment ? <p className="caption mt-s">Reviewing {selectedAppointment.code} with {selectedAppointment.doctorName}.</p> : null}
                                </>
                            )}
                        </section>

                        <section className="card card-pad">
                            <h2 className="h4">Privacy rule</h2>
                            <p className="caption">Do not name other patients or include private contact details. Such reviews are hidden by moderation; your rating still counts privately.</p>
                        </section>
                    </div>

                    <div className="c-12 lg-c-7">
                        <DataTable
                            className="tbl-cards-md"
                            caption="Reviews you submitted"
                            columns={columns}
                            rows={submitted}
                            getRowKey={(row) => row.id}
                            emptyTitle="No submitted reviews"
                            emptyBody="Complete a visit to unlock a review."
                        />
                        <p className="caption mt-m">Moderation statuses: pending, published or rejected. Rejection notes are shown to you only.</p>
                    </div>
                </div>
            </DataView>
        </RoleLayout>
    );
}