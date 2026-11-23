/**
 * Doctor · Walk-in registration — prototypes/doctor/06-walkin.html.
 * Patient + accepted appointment + claim code are created atomically.
 */
import { Head, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { SharedProps } from '@/types';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import type { DataState } from '@/types';

interface WalkInProps {
    days: Array<{ date: string; label: string; openTimes: string[] }>;
    receipt: { claimCode: string; appointmentCode: string; patientCode: string; patientName: string; doctorName: string; date: string; time: string; feeFormatted: string } | null;
    genders: string[];
    dataState: DataState;
    lastKnownAt: string;
}

export default function DoctorWalkIn({ days, receipt, genders, dataState, lastKnownAt }: WalkInProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(dataState);
    const [date, setDate] = useState(days[0]?.date ?? '');
    const day = days.find((d) => d.date === date) ?? days[0];
    const form = useForm({
        name: '',
        phone: '',
        age: '',
        gender: 'Female',
        condition: '',
        date: days[0]?.date ?? '',
        time: days[0]?.openTimes[0] ?? '',
        link_patient_code: '',
    });

    useEffect(() => {
        if (day && !day.openTimes.includes(form.data.time)) form.setData('time', day.openTimes[0] ?? '');
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [date]);

    const submit = () => form.post('/doctor/walk-in', { preserveScroll: true });

    return (
        <RoleLayout role="doctor" title="Walk-in registration" subtitle="creates patient + today's appointment atomically · issue a claim code" activeHref="/doctor/walk-in">
            <Head title="Walk-in registration — MediPath" />
            <div className="grid" style={{ alignItems: 'start' }}>
                <form className="c-12 lg-c-6 card card-pad stack" onSubmit={(event) => { event.preventDefault(); submit(); }}>
                    <h2 className="h4">Patient details</h2>
                    <div className="grid tight">
                        <div className="c-12 sm-c-6 field"><label className="label req" htmlFor="wi-name">Full name</label><input id="wi-name" className="input" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />{form.errors.name ? <span className="field-error">{form.errors.name}</span> : null}</div>
                        <div className="c-12 sm-c-6 field"><label className="label req" htmlFor="wi-phone">Phone</label><input id="wi-phone" className="input mono" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} />{form.errors.phone ? <span className="field-error">{form.errors.phone}</span> : null}</div>
                        <div className="c-12 sm-c-4 field"><label className="label" htmlFor="wi-age">Age</label><input id="wi-age" className="input" type="number" min={0} max={120} value={form.data.age} onChange={(event) => form.setData('age', event.target.value)} /></div>
                        <div className="c-12 sm-c-4 field"><label className="label" htmlFor="wi-gender">Gender</label><select id="wi-gender" className="select" value={form.data.gender} onChange={(event) => form.setData('gender', event.target.value)}>{genders.map((gender) => <option key={gender} value={gender}>{gender}</option>)}</select></div>
                        <div className="c-12 sm-c-4 field"><label className="label req" htmlFor="wi-condition">Presenting complaint</label><input id="wi-condition" className="input" value={form.data.condition} onChange={(event) => form.setData('condition', event.target.value)} /></div>
                    </div>
                    <h2 className="h4 mt-l">Slot</h2>
                    <div className="grid tight">
                        <div className="c-12 sm-c-6 field"><label className="label req" htmlFor="wi-date">Date</label><select id="wi-date" className="select" value={date} onChange={(event) => { setDate(event.target.value); form.setData('date', event.target.value); }}>{days.map((d) => <option key={d.date} value={d.date}>{d.label}</option>)}</select></div>
                        <div className="c-12 sm-c-6 field"><label className="label req" htmlFor="wi-time">Open slot</label><select id="wi-time" className="select" value={form.data.time} onChange={(event) => form.setData('time', event.target.value)}>{(day?.openTimes ?? []).map((time) => <option key={time} value={time}>{time}</option>)}</select></div>
                    </div>
                    {form.errors.time ? <div className="alert alert-danger"><span className="a-ico">⚠</span><span>{form.errors.time}</span></div> : null}
                    <div className="field"><label className="label" htmlFor="wi-claim">Link an existing walk-in claim code (optional)</label><input id="wi-claim" className="input mono" value={form.data.link_patient_code} onChange={(event) => form.setData('link_patient_code', event.target.value)} placeholder="WI-…" /></div>
                    <div className="alert alert-warning"><span className="a-ico" aria-hidden="true">🔎</span><span>Patient + appointment + claim code are one transaction. If the slot is taken, nothing is saved.</span></div>
                    <button type="submit" className="btn btn-primary" disabled={form.processing || !day}>{form.processing ? 'Creating…' : 'Register walk-in'}</button>
                </form>

                <section className="c-12 lg-c-6">
                    <DataView name="Data view · Registration outcome" state={state} onStateChange={setState} lastKnownAt={lastKnownAt}
                        emptyTitle="No walk-in registered yet" emptyBody="Register a walk-in — the receipt and claim code appear here."
                        errorMessage="Transaction rolled back — nothing was saved.">
                        {receipt ? (
                            <div className="card card-pad" role="status" aria-live="polite">
                                <span className="badge badge-success"><span className="dot" />Registered · appointment confirmed</span>
                                <h3 className="h3 mt-m">Claim code</h3>
                                <p className="mono" style={{ fontSize: 30, fontWeight: 800, color: 'var(--mp-primary-text)', letterSpacing: '.06em' }}>{receipt.claimCode}</p>
                                <p className="caption">Hand this code to the patient. When they self-register later, they enter it to merge the walk-in chart.</p>
                                <dl className="mt-m">
                                    <div className="def-row"><dt>Patient</dt><dd>{receipt.patientName} · <span className="mono">{receipt.patientCode}</span></dd></div>
                                    <div className="def-row"><dt>Appointment</dt><dd className="mono">{receipt.appointmentCode}</dd></div>
                                    <div className="def-row"><dt>Slot</dt><dd>{receipt.date} · {receipt.time}</dd></div>
                                    <div className="def-row"><dt>Fee</dt><dd>{receipt.feeFormatted}</dd></div>
                                </dl>
                                <p className="caption">Currency: {settings.currencyCode}. The receipt is audit-linked to your user.</p>
                            </div>
                        ) : null}
                    </DataView>
                </section>
            </div>
        </RoleLayout>
    );
}