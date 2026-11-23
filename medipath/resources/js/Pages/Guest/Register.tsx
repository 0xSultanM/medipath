/** Multi-role registration — prototypes/guest/06-register.html. */
import { Head, Link, useForm } from '@inertiajs/react';
import { useId } from 'react';
import type { FormEvent } from 'react';
import { GuestLayout } from '@/Layouts/GuestLayout';
import { InlineError, Spinner } from '@/Components/Feedback/FeedbackKit';

interface RegisterProps {
    specialties: Array<{ id: number; name: string }>;
    authorities: string[];
    genders: string[];
    roles: Record<string, string>;
    passwordMin: number;
    demoWalkInCode: string | null;
    preselectedRole: string;
}

const ROLE_ICONS: Record<string, string> = {
    patient: '🧑',
    doctor: '🩺',
    pharmacist: '💊',
};

export default function Register({
    specialties,
    authorities,
    genders,
    roles,
    passwordMin,
    demoWalkInCode,
    preselectedRole,
}: RegisterProps) {
    const prefix = useId();
    const { data, setData, post, processing, errors, progress } = useForm({
        role: preselectedRole,
        name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
        terms: false,
        claim_code: '',
        age: '',
        gender: 'Prefer not to say',
        condition: '',
        license_number: '',
        license_authority: authorities[0] ?? '',
        specialty_id: '',
        experience_years: '',
        fee: '',
        credential: null as File | null,
    });

    const isPatient = data.role === 'patient';
    const isDoctor = data.role === 'doctor';
    const isPharmacist = data.role === 'pharmacist';
    const needsCredential = isDoctor || isPharmacist;

    const pw = data.password;
    const rules = [
        { label: `${passwordMin}+ characters`, ok: pw.length >= passwordMin },
        { label: 'uppercase', ok: /[A-Z]/.test(pw) },
        { label: 'lowercase', ok: /[a-z]/.test(pw) },
        { label: 'number', ok: /[0-9]/.test(pw) },
        { label: 'symbol', ok: /[^A-Za-z0-9]/.test(pw) },
    ];

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/register', { forceFormData: true });
    };

    const fieldError = (key: keyof typeof errors) =>
        errors[key] ? <InlineError id={`${prefix}-${key}-err`} message={errors[key]} /> : null;

    return (
        <GuestLayout title="Create your account" chatbot={false}>
            <Head title="Create your account — MediPath">
                <meta name="description" content="Register as a patient, doctor or pharmacist on MediPath." />
            </Head>

            <div style={{ maxWidth: 820, marginInline: 'auto' }} className="stack">
                <div>
                    <h1>Create your account</h1>
                    <p className="muted" style={{ fontSize: 14 }}>
                        One atomic step — your profile, role and credentials are created together or not at all.
                        Doctor and pharmacist accounts go to admin approval before sign-in works.
                    </p>
                </div>

                <form className="card card-pad stack" onSubmit={submit} noValidate>
                    <fieldset style={{ border: 0, padding: 0, margin: 0 }}>
                        <legend className="label req" style={{ padding: 0, marginBottom: 'var(--mp-space-sm)' }}>
                            I am registering as
                        </legend>
                        <div className="role-picker" role="radiogroup" aria-label="Account role">
                            {(Object.keys(roles) as Array<keyof typeof roles>).map((role) => (
                                <label className={`role-pick ${data.role === role ? 'is-selected' : ''}`} key={role}>
                                    <input
                                        type="radio"
                                        name="role"
                                        value={role}
                                        checked={data.role === role}
                                        onChange={() => setData('role', role)}
                                    />
                                    <span className="role-ico" aria-hidden="true">{ROLE_ICONS[role] ?? '👤'}</span>
                                    <strong>{roles[role]}</strong>
                                    <span className="caption">
                                        {role === 'patient'
                                            ? 'Book doctors, AI symptom check, prescriptions and bills'
                                            : role === 'doctor'
                                                ? 'Needs license review by clinic admin'
                                                : 'Needs license review before dispensing'}
                                    </span>
                                </label>
                            ))}
                        </div>
                        <p className="caption mt-s">Admin accounts are created internally — not available here.</p>
                        {fieldError('role')}
                    </fieldset>

                    <div className="divider" />

                    <div className="form-grid">
                        <div className={`field ${errors.name ? 'is-invalid' : ''}`}>
                            <label className="label req" htmlFor={`${prefix}-name`}>Full name</label>
                            <input
                                id={`${prefix}-name`}
                                className="input"
                                autoComplete="name"
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                placeholder="e.g., Farhana Karim"
                                aria-invalid={Boolean(errors.name)}
                            />
                            {fieldError('name')}
                        </div>

                        <div className={`field ${errors.phone ? 'is-invalid' : ''}`}>
                            <label className="label req" htmlFor={`${prefix}-phone`}>Phone</label>
                            <input
                                id={`${prefix}-phone`}
                                className="input"
                                type="tel"
                                autoComplete="tel"
                                value={data.phone}
                                onChange={(event) => setData('phone', event.target.value)}
                                placeholder="+880 17XX-XXXXXX"
                                aria-invalid={Boolean(errors.phone)}
                            />
                            {fieldError('phone')}
                        </div>

                        <div className={`field span-2 ${errors.email ? 'is-invalid' : ''}`}>
                            <label className="label req" htmlFor={`${prefix}-email`}>Email</label>
                            <input
                                id={`${prefix}-email`}
                                className="input"
                                type="email"
                                autoComplete="email"
                                value={data.email}
                                onChange={(event) => setData('email', event.target.value)}
                                placeholder="you@example.com"
                                aria-invalid={Boolean(errors.email)}
                            />
                            <span className="hint">Used for appointment reminders and password reset only.</span>
                            {fieldError('email')}
                        </div>

                        <div className={`field ${errors.password ? 'is-invalid' : ''}`}>
                            <label className="label req" htmlFor={`${prefix}-password`}>Password</label>
                            <input
                                id={`${prefix}-password`}
                                className="input"
                                type="password"
                                autoComplete="new-password"
                                value={data.password}
                                onChange={(event) => setData('password', event.target.value)}
                                aria-describedby={`${prefix}-pw-rules`}
                                aria-invalid={Boolean(errors.password)}
                            />
                            <ul className="password-rules" id={`${prefix}-pw-rules`}>
                                {rules.map((rule) => (
                                    <li key={rule.label} className={rule.ok ? 'ok' : undefined}>
                                        {rule.ok ? '✓' : '○'} {rule.label}
                                    </li>
                                ))}
                            </ul>
                            {fieldError('password')}
                        </div>

                        <div className={`field ${errors.password_confirmation ? 'is-invalid' : ''}`}>
                            <label className="label req" htmlFor={`${prefix}-password2`}>Confirm password</label>
                            <input
                                id={`${prefix}-password2`}
                                className="input"
                                type="password"
                                autoComplete="new-password"
                                value={data.password_confirmation}
                                onChange={(event) => setData('password_confirmation', event.target.value)}
                            />
                            {fieldError('password_confirmation')}
                        </div>
                    </div>

                    {isPatient ? (
                        <>
                            <div className="alert alert-info" role="group" aria-label="Walk-in claim code">
                                <span className="a-ico" aria-hidden="true">ℹ</span>
                                <div className="a-body">
                                    <strong>Visited as a walk-in before?</strong> Enter the claim code from your paper
                                    receipt to merge that history into this account.
                                    {demoWalkInCode ? <span className="caption"> Local demo code: <code className="mono">{demoWalkInCode}</code></span> : null}
                                    <div className="mt-s">
                                        <label className="sr-only" htmlFor={`${prefix}-claim`}>Walk-in claim code</label>
                                        <input
                                            id={`${prefix}-claim`}
                                            className="input"
                                            style={{ maxWidth: 260 }}
                                            value={data.claim_code}
                                            onChange={(event) => setData('claim_code', event.target.value)}
                                            placeholder="e.g., WI-4821"
                                        />
                                    </div>
                                    {fieldError('claim_code')}
                                </div>
                            </div>

                            <div className="form-grid">
                                <div className="field">
                                    <label className="label" htmlFor={`${prefix}-age`}>Age</label>
                                    <input
                                        id={`${prefix}-age`}
                                        className="input"
                                        type="number"
                                        min={0}
                                        max={120}
                                        value={data.age}
                                        onChange={(event) => setData('age', event.target.value)}
                                    />
                                    {fieldError('age')}
                                </div>
                                <div className="field">
                                    <label className="label" htmlFor={`${prefix}-gender`}>Gender</label>
                                    <select
                                        id={`${prefix}-gender`}
                                        className="select"
                                        value={data.gender}
                                        onChange={(event) => setData('gender', event.target.value)}
                                    >
                                        {genders.map((gender) => <option key={gender}>{gender}</option>)}
                                    </select>
                                    {fieldError('gender')}
                                </div>
                                <div className="field span-2">
                                    <label className="label" htmlFor={`${prefix}-condition`}>Primary condition (optional)</label>
                                    <input
                                        id={`${prefix}-condition`}
                                        className="input"
                                        value={data.condition}
                                        onChange={(event) => setData('condition', event.target.value)}
                                        placeholder="e.g., hypertension"
                                    />
                                    {fieldError('condition')}
                                </div>
                            </div>
                        </>
                    ) : null}

                    {isDoctor ? (
                        <>
                            <div className="alert alert-warning credential-alert">
                                <span className="a-ico" aria-hidden="true">🪪</span>
                                <span>Your account stays <strong>pending</strong> until the admin verifies your license. You can track progress on the approval screen.</span>
                            </div>
                            <div className="form-grid">
                                <div className="field">
                                    <label className="label req" htmlFor={`${prefix}-lic`}>License number</label>
                                    <input
                                        id={`${prefix}-lic`}
                                        className="input mono"
                                        value={data.license_number}
                                        onChange={(event) => setData('license_number', event.target.value)}
                                        placeholder="BMDC-A-XXXXXX"
                                    />
                                    {fieldError('license_number')}
                                </div>
                                <div className="field">
                                    <label className="label req" htmlFor={`${prefix}-auth`}>License authority</label>
                                    <select
                                        id={`${prefix}-auth`}
                                        className="select"
                                        value={data.license_authority}
                                        onChange={(event) => setData('license_authority', event.target.value)}
                                    >
                                        {authorities.map((authority) => <option key={authority}>{authority}</option>)}
                                    </select>
                                    {fieldError('license_authority')}
                                </div>
                                <div className="field">
                                    <label className="label req" htmlFor={`${prefix}-spec`}>Specialty</label>
                                    <select
                                        id={`${prefix}-spec`}
                                        className="select"
                                        value={data.specialty_id}
                                        onChange={(event) => setData('specialty_id', event.target.value)}
                                    >
                                        <option value="">Choose a specialty</option>
                                        {specialties.map((specialty) => (
                                            <option key={specialty.id} value={specialty.id}>{specialty.name}</option>
                                        ))}
                                    </select>
                                    {fieldError('specialty_id')}
                                </div>
                                <div className="field">
                                    <label className="label" htmlFor={`${prefix}-exp`}>Years of experience</label>
                                    <input
                                        id={`${prefix}-exp`}
                                        className="input"
                                        type="number"
                                        min={0}
                                        max={70}
                                        value={data.experience_years}
                                        onChange={(event) => setData('experience_years', event.target.value)}
                                    />
                                    {fieldError('experience_years')}
                                </div>
                                <div className="field">
                                    <label className="label req" htmlFor={`${prefix}-fee`}>Consultation fee</label>
                                    <input
                                        id={`${prefix}-fee`}
                                        className="input money"
                                        inputMode="decimal"
                                        value={data.fee}
                                        onChange={(event) => setData('fee', event.target.value)}
                                        placeholder="1,200.00"
                                    />
                                    <span className="hint">Amounts use the clinic currency configured by the admin.</span>
                                    {fieldError('fee')}
                                </div>
                                <div className="field">
                                    <label className="label req" htmlFor={`${prefix}-cred`}>License document (PDF/JPG ≤5 MB)</label>
                                    <input
                                        id={`${prefix}-cred`}
                                        className="input"
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        style={{ padding: 10 }}
                                        onChange={(event) => setData('credential', event.target.files?.[0] ?? null)}
                                    />
                                    <span className="hint">Stored on a private disk; only admins can preview it.</span>
                                    {fieldError('credential')}
                                </div>
                            </div>
                        </>
                    ) : null}

                    {isPharmacist ? (
                        <>
                            <div className="alert alert-warning credential-alert">
                                <span className="a-ico" aria-hidden="true">🪪</span>
                                <span>Dispensing access begins after admin approval of your pharmacy license.</span>
                            </div>
                            <div className="form-grid">
                                <div className="field">
                                    <label className="label req" htmlFor={`${prefix}-plic`}>Pharmacy license number</label>
                                    <input
                                        id={`${prefix}-plic`}
                                        className="input mono"
                                        value={data.license_number}
                                        onChange={(event) => setData('license_number', event.target.value)}
                                        placeholder="BMDC-P-XXXXXX"
                                    />
                                    {fieldError('license_number')}
                                </div>
                                <div className="field">
                                    <label className="label req" htmlFor={`${prefix}-pauth`}>Issuing authority</label>
                                    <select
                                        id={`${prefix}-pauth`}
                                        className="select"
                                        value={data.license_authority}
                                        onChange={(event) => setData('license_authority', event.target.value)}
                                    >
                                        {authorities.map((authority) => <option key={authority}>{authority}</option>)}
                                    </select>
                                    {fieldError('license_authority')}
                                </div>
                                <div className="field span-2">
                                    <label className="label req" htmlFor={`${prefix}-pcred`}>License document (PDF/JPG ≤5 MB)</label>
                                    <input
                                        id={`${prefix}-pcred`}
                                        className="input"
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        style={{ padding: 10 }}
                                        onChange={(event) => setData('credential', event.target.files?.[0] ?? null)}
                                    />
                                    {fieldError('credential')}
                                </div>
                            </div>
                        </>
                    ) : null}

                    <label className={`check mt-m ${errors.terms ? 'is-invalid' : ''}`}>
                        <input
                            type="checkbox"
                            checked={data.terms}
                            onChange={(event) => setData('terms', event.target.checked)}
                            aria-invalid={Boolean(errors.terms)}
                        />
                        <span style={{ fontSize: 14 }}>
                            I agree to the <a href="#">Terms</a> and <a href="#">Health Data Privacy Notice</a>, and confirm
                            the credentials above are mine.
                        </span>
                    </label>
                    {fieldError('terms')}

                    {progress ? (
                        <div className="progress" aria-label={`Upload ${progress.percentage}%`}>
                            <span style={{ width: `${progress.percentage}%` }} />
                        </div>
                    ) : null}

                    <button className="btn btn-primary btn-block mt-b" type="submit" disabled={processing}>
                        {processing ? <Spinner label="Creating account" /> : null}
                        {processing ? 'Creating account…' : 'Create account'}
                    </button>

                    <p className="caption">
                        Patients land in the portal after approval; doctors and pharmacists continue to the{' '}
                        <Link href="/pending">approval status screen</Link>. Already registered? <Link href="/login">Sign in</Link>.
                    </p>
                </form>
            </div>
        </GuestLayout>
    );
}