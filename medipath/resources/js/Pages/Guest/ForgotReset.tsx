/** Forgot / reset password — prototypes/guest/07-forgot-reset.html. */
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useId } from 'react';
import type { SharedProps } from '@/types';
import { GuestLayout } from '@/Layouts/GuestLayout';
import { Stepper } from '@/Components/Workflow/Stepper';
import { InlineError, Spinner } from '@/Components/Feedback/FeedbackKit';

interface ForgotResetProps {
    token: string;
    email: string;
    tokenValid: boolean;
    ttlMinutes: number;
}

export default function ForgotReset({ token, email, tokenValid, ttlMinutes }: ForgotResetProps) {
    const prefix = useId();
    const { flash } = usePage<SharedProps>().props;

    const forgot = useForm({ email });
    const reset = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const rules = [
        { label: '10+ characters', ok: reset.data.password.length >= 10 },
        { label: 'uppercase', ok: /[A-Z]/.test(reset.data.password) },
        { label: 'lowercase', ok: /[a-z]/.test(reset.data.password) },
        { label: 'number', ok: /[0-9]/.test(reset.data.password) },
        { label: 'symbol', ok: /[^A-Za-z0-9]/.test(reset.data.password) },
    ];

    const requestSent = tokenValid || Boolean(flash.success);
    const steps = [
        { key: 'request', label: 'Request', sub: 'email submitted', state: requestSent ? ('done' as const) : ('current' as const) },
        {
            key: 'inbox',
            label: 'Check inbox',
            sub: `token valid ${ttlMinutes} min`,
            state: tokenValid ? ('done' as const) : (requestSent ? ('current' as const) : ('upcoming' as const)),
        },
        {
            key: 'new',
            label: 'New password',
            sub: 'then sign in',
            state: tokenValid ? ('current' as const) : ('upcoming' as const),
        },
    ];

    return (
        <GuestLayout title="Reset your password" chatbot={false}>
            <Head title="Reset your password — MediPath">
                <meta name="description" content="Request a one-time MediPath password reset link." />
            </Head>

            <div style={{ maxWidth: 920, marginInline: 'auto' }} className="stack">
                <h1>Reset your password</h1>

                <div className="card card-pad">
                    <Stepper steps={steps} ariaLabel="Password reset progress" />
                </div>

                <div className="grid">
                    <section className="c-12 md-c-6 card card-pad stack" aria-labelledby="rq">
                        <h2 className="h4" id="rq">1 · Request a reset link</h2>
                        <p className="caption">
                            We never reveal whether an address is registered. The link is single-use and expires in {ttlMinutes} minutes.
                        </p>
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                forgot.post('/forgot-password', { preserveScroll: true });
                            }}
                        >
                            <div className={`field ${forgot.errors.email ? 'is-invalid' : ''}`}>
                                <label className="label req" htmlFor={`${prefix}-email`}>Account email</label>
                                <input
                                    id={`${prefix}-email`}
                                    className="input"
                                    type="email"
                                    autoComplete="email"
                                    value={forgot.data.email}
                                    onChange={(event) => forgot.setData('email', event.target.value)}
                                    aria-invalid={Boolean(forgot.errors.email)}
                                />
                                <InlineError id={`${prefix}-email-err`} message={forgot.errors.email} />
                            </div>
                            <button className="btn btn-primary btn-block" type="submit" disabled={forgot.processing}>
                                {forgot.processing ? <Spinner label="Sending reset link" /> : null}
                                {forgot.processing ? 'Sending…' : 'Send reset link'}
                            </button>
                        </form>

                        {flash.demoResetLink ? (
                            <div className="alert alert-info" role="note">
                                <span className="a-ico" aria-hidden="true">🛠</span>
                                <div className="a-body">
                                    <strong>Local demo reset link</strong>
                                    <p className="caption" style={{ wordBreak: 'break-all' }}>
                                        <a href={flash.demoResetLink}>{flash.demoResetLink}</a>
                                    </p>
                                </div>
                            </div>
                        ) : null}

                    </section>

                    <section className="c-12 md-c-6 card card-pad stack" aria-labelledby="rs">
                        <h2 className="h4" id="rs">2 · Choose a new password</h2>

                        {!token || !tokenValid ? (
                            <>
                                <div className={`alert ${token ? 'alert-danger' : 'alert-info'}`} role={token ? 'alert' : 'note'}>
                                    <span className="a-ico" aria-hidden="true">{token ? '⚠' : '✉'}</span>
                                    <div className="a-body">
                                        <strong className="a-title">
                                            {token ? 'This reset link expired or was already used.' : 'Open the one-time link from your email.'}
                                        </strong>
                                        <p className="caption">
                                            For your security we never reveal whether an address is registered.
                                        </p>
                                        {token ? <a className="btn btn-primary btn-sm mt-s" href="/forgot-password">Request a new link</a> : null}
                                    </div>
                                </div>
                                <p className="caption">
                                    On success the token is consumed and all other sessions are revoked. You will sign in
                                    again with the new password.
                                </p>
                            </>
                        ) : (
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    reset.post('/reset-password');
                                }}
                            >
                                <div className={`field ${reset.errors.password ? 'is-invalid' : ''}`}>
                                    <label className="label req" htmlFor={`${prefix}-pw`}>New password</label>
                                    <input
                                        id={`${prefix}-pw`}
                                        className="input"
                                        type="password"
                                        autoComplete="new-password"
                                        value={reset.data.password}
                                        onChange={(event) => reset.setData('password', event.target.value)}
                                        aria-invalid={Boolean(reset.errors.password)}
                                    />
                                    <ul className="password-rules">
                                        {rules.map((rule) => (
                                            <li key={rule.label} className={rule.ok ? 'ok' : undefined}>
                                                {rule.ok ? '✓' : '○'} {rule.label}
                                            </li>
                                        ))}
                                    </ul>
                                    <InlineError id={`${prefix}-pw-err`} message={reset.errors.password} />
                                </div>

                                <div className={`field ${reset.errors.password_confirmation ? 'is-invalid' : ''}`}>
                                    <label className="label req" htmlFor={`${prefix}-pw2`}>Confirm new password</label>
                                    <input
                                        id={`${prefix}-pw2`}
                                        className="input"
                                        type="password"
                                        autoComplete="new-password"
                                        value={reset.data.password_confirmation}
                                        onChange={(event) => reset.setData('password_confirmation', event.target.value)}
                                    />
                                    <InlineError id={`${prefix}-pw2-err`} message={reset.errors.password_confirmation} />
                                </div>

                                {reset.errors.token ? <InlineError id={`${prefix}-token-err`} message={reset.errors.token} /> : null}

                                <button className="btn btn-primary btn-block" type="submit" disabled={reset.processing}>
                                    {reset.processing ? <Spinner label="Updating password" /> : null}
                                    {reset.processing ? 'Updating…' : 'Update password & sign out other devices'}
                                </button>
                            </form>
                        )}

                        <p className="caption">
                            Need help? <Link href="/login">Back to sign in</Link>
                        </p>
                    </section>
                </div>
            </div>
        </GuestLayout>
    );
}