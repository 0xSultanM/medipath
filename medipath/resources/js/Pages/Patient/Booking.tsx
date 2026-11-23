/**
 * Patient · Booking / reschedule — prototypes/patient/02-booking.html.
 * Slots are server-authoritative (schedules − leaves − bookings); the confirm
 * call re-checks availability and relies on the unique active-slot key.
 */
import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { DataState, DateChip, DoctorSummary, SharedProps, Slot } from '@/types';
import { usePage } from '@inertiajs/react';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { SlotPicker } from '@/Components/Data/SlotPicker';
import { Money } from '@/Components/ui/Money';

interface BookingProps {
    doctor: DoctorSummary & { bookCode: string };
    dates: DateChip[];
    slots: Slot[];
    selectedDate: string;
    selectedTime: string;
    reschedule: { id: number; code: string; visitReason: string; date: string | null; time: string } | null;
    cancellationCutoffHours: number;
    dataState: DataState;
    lastKnownAt: string;
}

export default function PatientBooking({ doctor, dates, slots, selectedDate, selectedTime: initialTime, reschedule, cancellationCutoffHours }: BookingProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>('success');
    const [time, setTime] = useState(initialTime);
    const form = useForm({
        doctor_code: doctor.bookCode,
        date: selectedDate,
        time: initialTime,
        visit_reason: reschedule?.visitReason ?? '',
    });

    useEffect(() => {
        setTime(initialTime);
        form.setData({ ...form.data, date: selectedDate, time: initialTime });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selectedDate, initialTime]);

    const changeDate = (date: string) => {
        setTime('');
        form.setData({ ...form.data, date, time: '' });
        router.get(`/patient/book/${doctor.bookCode}`, { date, ...(reschedule ? { reschedule: reschedule.id } : {}) }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const changeTime = (value: string) => {
        setTime(value);
        form.setData('time', value);
    };

    const submit = () => {
        if (!time) return;
        if (reschedule) {
            form.post(`/patient/appointments/${reschedule.id}/reschedule`, { preserveScroll: true });
        } else {
            form.post('/patient/appointments', { preserveScroll: true });
        }
    };

    return (
        <RoleLayout
            role="patient"
            title={reschedule ? `Reschedule ${reschedule.code}` : `Book with ${doctor.name}`}
            subtitle={`${doctor.specialty} · ${doctor.experienceYears} yrs experience`}
            activeHref="/patient/appointments"
            topbarActions={<a className="btn btn-ghost" href="/patient/doctors">Change doctor</a>}
        >
            <Head title={reschedule ? 'Reschedule appointment — MediPath' : 'Book appointment — MediPath'} />

            {(form.errors as Record<string, string | undefined>).slot ? (
                <div className="alert alert-danger" role="alert">
                    <span className="a-ico" aria-hidden="true">⛔</span>
                    <span><strong>That slot was just taken.</strong> {(form.errors as Record<string, string | undefined>).slot} <button type="button" className="btn btn-ghost btn-sm" onClick={() => router.reload()}>Reload availability</button></span>
                </div>
            ) : null}

            <div className="grid" style={{ alignItems: 'start' }}>
                <div className="c-12 lg-c-8 stack">
                    <DataView
                        name="Data view · Available slots"
                        state={state}
                        onStateChange={setState}
                        showSwitch
                        lastKnownAt={new Date().toISOString()}
                        emptyTitle="No open slots in the next 7 days"
                        emptyBody="Try another doctor or check back after a cancellation."
                        errorMessage="Availability could not load. We never show stale slots for booking."
                        skeleton={<div className="date-strip" aria-hidden="true">{Array.from({ length: 7 }).map((_, index) => <span className="sk" style={{ width: 64, height: 64, borderRadius: 12 }} key={index} />)}</div>}
                    >
                        <SlotPicker
                            dates={dates}
                            selectedDate={form.data.date}
                            onSelectDate={changeDate}
                            slots={slots}
                            selectedTime={time}
                            onSelectTime={changeTime}
                            state="success"
                            emptyHint="The next opening may be after this 7-day window."
                            onRetry={() => router.reload()}
                            conflict={(form.errors as Record<string, string | undefined>).slot ?? null}
                            onReload={() => router.reload()}
                        />
                    </DataView>

                    <div className="card card-pad">
                        <h2 className="h4">Reason for visit</h2>
                        <div className="field mt-s">
                            <label className="label req" htmlFor="booking-reason">Notes for the doctor</label>
                            <textarea
                                id="booking-reason"
                                className="textarea"
                                rows={4}
                                value={form.data.visit_reason}
                                onChange={(event) => form.setData('visit_reason', event.target.value)}
                                placeholder="e.g., BP still high in the mornings; home readings attached."
                                disabled={Boolean(reschedule)}
                            />
                            <span className="hint">{reschedule ? 'The original visit reason is kept on a reschedule.' : 'Visible to the doctor before your visit. Minimum 10 characters.'}</span>
                            {form.errors.visit_reason ? <span className="field-error" role="alert">{form.errors.visit_reason}</span> : null}
                        </div>
                    </div>
                </div>

                <aside className="c-12 lg-c-4 card card-pad" style={{ position: 'sticky', top: 'calc(var(--mp-navbar-h) + 16px)' }}>
                    <h2 className="h4">Booking summary</h2>
                    <dl className="mt-s">
                        <div className="def-row"><dt>Doctor</dt><dd>{doctor.name}</dd></div>
                        <div className="def-row"><dt>Specialty</dt><dd>{doctor.specialty}</dd></div>
                        <div className="def-row"><dt>Date</dt><dd className="tabular">{form.data.date}</dd></div>
                        <div className="def-row"><dt>Slot</dt><dd className="tabular">{time || 'Choose a slot'}</dd></div>
                        <div className="def-row"><dt>Duration</dt><dd className="tabular">30 min</dd></div>
                        <div className="def-row"><dt>Fee</dt><dd><Money formatted={doctor.feeFormatted} size="sm" /></dd></div>
                    </dl>
                    <div className="alert alert-warning" style={{ fontSize: 13 }}>
                        <span className="a-ico" aria-hidden="true">⏱</span>
                        <span>Slots lock at confirm; double-booking is impossible (unique active-slot key).</span>
                    </div>
                    <button type="button" className="btn btn-primary btn-block" disabled={!time || form.processing} onClick={submit}>
                        {form.processing ? 'Sending request…' : reschedule ? 'Confirm reschedule' : 'Confirm booking request'}
                    </button>
                    <p className="caption mt-m">Cancellation cutoff: {cancellationCutoffHours}h before the slot. The clinic currency is {settings.currencyCode}.</p>
                </aside>
            </div>
        </RoleLayout>
    );
}