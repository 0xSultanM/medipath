/**
 * Foundation status page — Part 0 only.
 * Guest shell (§1), seed inventory, role-shell entry points and the local
 * design-system gallery. Phase 1 replaces "/" with the approved Landing page.
 */
import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';
import { GuestLayout } from '@/Layouts/GuestLayout';
import { KpiCard } from '@/Components/Data/KpiCard';
import { useTheme } from '@/providers/ThemeProvider';

interface FoundationProps {
    stats: {
        specialties: number;
        medicines: number;
        faqs: number;
        verifiedDoctors: number;
        users: number;
    };
    demoAccounts: Array<{
        name: string;
        email: string;
        role: string;
        roleLabel: string;
        status: string;
        statusLabel: string;
    }>;
    roles: Array<{ value: string; label: string; portal: string; url: string }>;
    demoPassword: string | null;
}

export default function FoundationIndex({ stats, demoAccounts, roles, demoPassword }: FoundationProps) {
    const { settings } = usePage<SharedProps>().props;
    const { theme } = useTheme();

    return (
        <GuestLayout title="MediPath foundation">
            <section className="grid" style={{ alignItems: 'start' }}>
                <div className="c-12 md-c-8">
                    <span className="badge badge-primary">
                        <span className="dot" aria-hidden="true" />
                        Part 0 · Foundation complete
                    </span>

                    <h1 className="display mt-m">
                        MediPath runs on a <span style={{ color: 'var(--mp-primary-text)' }}>token-driven</span> design
                        system, a hardened role model, and a seeded clinical database.
                    </h1>

                    <p className="lead mt-m">
                        This page confirms the foundation: Laravel 12 monolith, React 18 + TypeScript library,
                        Bootstrap 5.3 bridge, MySQL schema with CHECK constraints, Redis sessions/queues, and a
                        four-state component contract. Screens arrive phase by phase.
                    </p>

                    <div className="mp-row mt-l">
                        <a className="btn btn-primary" href="/dev/ui">
                            Open component gallery
                        </a>
                        <a className="btn btn-outline" href="/dev/layouts/admin">
                            Preview role shells
                        </a>
                        <a className="cta-emergency" href="#foundation-emergency">
                            🚨 Emergency affordance demo
                        </a>
                    </div>
                </div>

                <aside className="c-12 md-c-4 card card-pad stack-sm" aria-label="Environment">
                    <h2 className="h4">Environment</h2>
                    <dl className="meta-grid">
                        <div>
                            <dt>Currency</dt>
                            <dd className="tabular">{settings.currencyCode} ({settings.currencySymbol})</dd>
                        </div>
                        <div>
                            <dt>Timezone</dt>
                            <dd>{settings.timezone}</dd>
                        </div>
                        <div>
                            <dt>Locale</dt>
                            <dd>{settings.locale}</dd>
                        </div>
                        <div>
                            <dt>Date format</dt>
                            <dd>{settings.dateFormat}</dd>
                        </div>
                        <div>
                            <dt>Theme</dt>
                            <dd>{theme}</dd>
                        </div>
                        <div>
                            <dt>Revenue %</dt>
                            <dd>{settings.revenueConfigured ? `${settings.revenuePercentage}%` : 'Not configured'}</dd>
                        </div>
                    </dl>
                    <p className="caption">
                        Every value above is read through SettingsService (cached) — no UI literals.
                    </p>
                </aside>
            </section>

            <section className="mt-xl" aria-labelledby="seed-title">
                <div className="spread">
                    <h2 id="seed-title">Seeded data</h2>
                    <span className="caption">Deterministic demo graph — no lorem ipsum</span>
                </div>

                <div className="grid mt-b">
                    <div className="c-6 md-c-3">
                        <KpiCard label="Specialties" value={stats.specialties} delta="consultant categories" trend="flat" />
                    </div>
                    <div className="c-6 md-c-3">
                        <KpiCard label="Medicines" value={stats.medicines} delta="with batch + expiry" trend="flat" />
                    </div>
                    <div className="c-6 md-c-3">
                        <KpiCard
                            label="Verified doctors"
                            value={stats.verifiedDoctors}
                            tone="success"
                            delta="published to directory"
                            trend="up"
                        />
                    </div>
                    <div className="c-6 md-c-3">
                        <KpiCard label="FAQs" value={stats.faqs} delta="chatbot fallback knowledge" trend="flat" />
                    </div>
                </div>
            </section>

            <section className="mt-xl" aria-labelledby="roles-title">
                <h2 id="roles-title">Role shells</h2>
                <p className="caption">
                    Four authenticated shells share one sidebar contract. Sign in with a demo account (POST /login
                    works; the designed Login screen ships in Phase 1).
                </p>

                <div className="grid mt-b">
                    {roles.map((role) => (
                        <div className="c-12 sm-c-6 lg-c-3" key={role.value}>
                            <article className="card card-pad stack-sm">
                                <h3 className="h4">{role.label}</h3>
                                <p className="caption">{role.portal}</p>
                                <div className="mp-row" style={{ marginTop: 'auto' }}>
                                    <a className="btn btn-outline btn-sm" href={role.url}>
                                        Open shell
                                    </a>
                                    <a className="btn btn-ghost btn-sm" href={`/dev/layouts/${role.value}`}>
                                        Preview 360/768/1440
                                    </a>
                                </div>
                            </article>
                        </div>
                    ))}
                </div>
            </section>

            <section className="card mt-xl" aria-labelledby="creds-title">
                <div className="card-head">
                    <h2 className="h4" id="creds-title">Demo accounts</h2>
                    <span className="badge badge-warning">Local only</span>
                </div>
                <div style={{ overflowX: 'auto' }}>
                    <table className="tbl">
                        <caption className="sr-only">Demo users seeded for each role</caption>
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Role</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {demoAccounts.map((account) => (
                                <tr key={account.email}>
                                    <td data-label="Name" className="cell-main">{account.name}</td>
                                    <td data-label="Email" className="mono">{account.email}</td>
                                    <td data-label="Role">
                                        <span className="badge badge-primary">{account.roleLabel}</span>
                                    </td>
                                    <td data-label="Status">
                                        <span className="badge badge-neutral">{account.statusLabel}</span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="card-foot">
                    <span className="caption">
                        {demoPassword
                            ? <>Shared demo password: <code className="mono">{demoPassword}</code> · use only on a local instance.</>
                            : 'Demo credentials are hidden outside the local environment.'}
                    </span>
                </div>
            </section>

            <section className="card card-pad mt-xl" id="foundation-emergency" aria-labelledby="emg-title">
                <h2 className="h4" id="emg-title">Emergency affordance placeholder</h2>
                <p className="caption">
                    The emergency red token is scoped to emergency UI. The full Emergency page kit (§12) is previewed
                    in the gallery and ships in Phase 1.
                </p>
                <div className="mp-row mt-s">
                    <a className="cta-emergency" href="/dev/ui#safety">🚨 Emergency components</a>
                    <span className="caption">Hotline data is seeded in <code className="mono">emergency_contacts</code>.</span>
                </div>
            </section>
        </GuestLayout>
    );
}