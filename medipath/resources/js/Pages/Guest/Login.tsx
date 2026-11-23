/** Login screen — prototypes/guest/05-login.html. */
import { Head, Link, useForm } from '@inertiajs/react';
import { useId, useState } from 'react';
import type { DemoAccount } from '@/types';
import { GuestLayout } from '@/Layouts/GuestLayout';
import { InlineError, Spinner } from '@/Components/Feedback/FeedbackKit';

interface LoginProps {
    redirect: string | null;
    demoAccounts: DemoAccount[];
    demoPassword: string | null;
}

export default function Login({ redirect, demoAccounts, demoPassword }: LoginProps) {
    const emailId = useId();
    const passwordId = useId();
    const [reveal, setReveal] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
        redirect: redirect ?? '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        post('/login', {
            onFinish: () => reset('password'),
        });
    };

    const fillDemo = (account: DemoAccount) => {
        setData('email', account.email);
        setData('password', demoPassword ?? '');
    };

    return (
        <GuestLayout title="Sign in" activeHref="/login" chatbot={false}>
            <Head title="Sign in — MediPath">
                <meta name="description" content="Sign in to your MediPath account." />
            </Head>

            <div className="grid auth-shell">
                <section className="c-12 md-c-6 card auth-card" aria-labelledby="login-title">
                    <h1 className="auth-title" id="login-title">Welcome back</h1>
                    <p className="muted mt-s" style={{ fontSize: 14 }}>
                        Sign in with the account you registered. Your workspace is chosen from your database role —
                        never a guessed destination.
                    </p>

                    {demoAccounts.length > 0 && demoPassword ? (
                        <div className="demo-accounts mt-m" role="note">
                            <strong>Local demo accounts</strong>
                            <p className="caption">Click one to fill the form. Password: <code className="mono">{demoPassword}</code></p>
                            {demoAccounts.map((account) => (
                                <div className="demo-row" key={account.email}>
                                    <button type="button" className="btn btn-ghost btn-sm" onClick={() => fillDemo(account)}>
                                        {account.roleLabel}
                                    </button>
                                    <span className="mono">{account.email}</span>
                                    <span className="badge badge-neutral badge-sm">{account.statusLabel}</span>
                                </div>
                            ))}
                        </div>
                    ) : null}

                    <form className="mt-b" onSubmit={submit} noValidate aria-describedby="login-note">
                        <div className={`field ${errors.email ? 'is-invalid' : ''}`}>
                            <label className="label req" htmlFor={emailId}>Email</label>
                            <input
                                id={emailId}
                                className="input"
                                type="email"
                                autoComplete="email"
                                value={data.email}
                                onChange={(event) => setData('email', event.target.value)}
                                aria-invalid={Boolean(errors.email)}
                                aria-describedby={errors.email ? `${emailId}-err` : undefined}
                            />
                            <InlineError id={`${emailId}-err`} message={errors.email} />
                        </div>

                        <div className={`field ${errors.password ? 'is-invalid' : ''}`}>
                            <label className="label req" htmlFor={passwordId}>Password</label>
                            <div className="input-reveal">
                                <input
                                    id={passwordId}
                                    className="input"
                                    type={reveal ? 'text' : 'password'}
                                    autoComplete="current-password"
                                    value={data.password}
                                    onChange={(event) => setData('password', event.target.value)}
                                    aria-invalid={Boolean(errors.password)}
                                    aria-describedby={errors.password ? `${passwordId}-err` : 'login-pw-hint'}
                                />
                                <button
                                    type="button"
                                    className="reveal"
                                    aria-pressed={reveal}
                                    onClick={() => setReveal((value) => !value)}
                                >
                                    {reveal ? 'Hide' : 'Show'}
                                </button>
                            </div>
                            <InlineError id={`${passwordId}-err`} message={errors.password} />
                            <span className="hint" id="login-pw-hint">
                                Session-based auth with HTTP-only cookies. Repeated failures are rate-limited.
                            </span>
                        </div>

                        <div className="spread">
                            <label className="switch">
                                <input
                                    type="checkbox"
                                    checked={data.remember}
                                    onChange={(event) => setData('remember', event.target.checked)}
                                />
                                <span style={{ fontSize: 14 }}>Remember me</span>
                            </label>
                            <Link href="/forgot-password" style={{ fontSize: 14, fontWeight: 600 }}>Forgot password?</Link>
                        </div>

                        <button className="btn btn-primary btn-block mt-b" type="submit" disabled={processing}>
                            {processing ? <Spinner label="Signing in" /> : null}
                            {processing ? 'Signing in…' : 'Sign in'}
                        </button>

                        <p className="caption mt-m" id="login-note">
                            Pending accounts go to the approval status screen automatically. Suspended or archived
                            accounts cannot sign in.
                        </p>
                        <div className="divider" />
                        <p className="mp-row" style={{ fontSize: 14 }}>
                            No account? <Link href="/register" style={{ fontWeight: 600 }}>Register as patient, doctor or pharmacist</Link>
                        </p>
                    </form>
                </section>

                <aside className="c-12 md-c-6 auth-trust hide-sm" aria-label="Why MediPath is safe">
                    <div className="card card-pad trust-card">
                        <span className="trust-ico" aria-hidden="true">🔒</span>
                        <div className="trust-body">
                            <strong>Doctor-verified only.</strong>
                            <p className="caption mt-s">Providers appear in booking after the admin reviews their license and authority.</p>
                        </div>
                    </div>
                    <div className="card card-pad trust-card">
                        <span className="trust-ico" aria-hidden="true">🧪</span>
                        <div className="trust-body">
                            <strong>Safety gates on every prescription.</strong>
                            <p className="caption mt-s">Allergy and interaction rules run at prescribing and again before dispensing.</p>
                        </div>
                    </div>
                    <div className="card card-pad trust-card">
                        <span className="trust-ico" aria-hidden="true">🚨</span>
                        <div className="trust-body">
                            <strong>Emergency first, AI second.</strong>
                            <p className="caption mt-s">Red-flag keywords trigger deterministic escalation instantly.</p>
                        </div>
                    </div>
                    <a className="btn btn-outline" href="/emergency">Emergency info — no login needed</a>
                </aside>
            </div>
        </GuestLayout>
    );
}