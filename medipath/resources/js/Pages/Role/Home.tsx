/**
 * Role/Home — Part 0 shell verification page (replaced by each role's designed
 * dashboard in later phases). Exercises §2 shell, §3 KPI, §10 bell and the
 * four-state contract at 360/768/1440.
 */
import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { DataState, SharedProps } from '@/types';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { KpiCard } from '@/Components/Data/KpiCard';
import { DataView } from '@/Components/Data/DataView';
import { Money } from '@/Components/ui/Money';
import { StatusBadge } from '@/Components/Data/StatusBadge';

interface RoleHomeProps {
    role: { value: string; label: string; portal: string };
    checklist: string[];
    notificationPreview: Array<{
        id: number;
        title: string;
        message: string;
        category: string;
        createdAt: string | null;
        readAt: string | null;
    }>;
    currencyPreview: { standard: string; compact: string; negative: string; currencyCode: string };
}

export default function RoleHome({ role, checklist, notificationPreview, currencyPreview }: RoleHomeProps) {
    const { ui, settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(ui.initialState ?? 'success');
    const [tick, setTick] = useState(0);

    return (
        <RoleLayout
            role={role.value}
            title={`${role.label} workspace`}
            subtitle={`Part 0 shell · ${settings.clinicName}`}
            activeHref={`/${role.value}/dashboard`}
            notificationState={state === 'skeleton' ? 'skeleton' : state === 'error' ? 'error' : notificationPreview.length ? 'success' : 'empty'}
        >
            <DataView
                name={`Data view · Shell KPIs — ?state=${state}`}
                state={state}
                onStateChange={setState}
                showSwitch
                onRetry={() => setTick((value) => value + 1)}
                lastKnownAt={new Date(Date.now() - 4 * 60 * 1000).toISOString()}
                emptyTitle="No metrics for the selected range"
                emptyBody="This shell has no live metrics yet; role dashboards arrive in later phases."
                skeleton={
                    <div className="grid" aria-hidden="true">
                        {[0, 1, 2, 3].map((item) => (
                            <div className="c-6 md-c-3" key={item}>
                                <KpiCard label="Loading metric" state="skeleton" />
                            </div>
                        ))}
                    </div>
                }
            >
                <div className="grid" key={tick}>
                    <div className="c-6 md-c-3">
                        <KpiCard
                            label="Shell readiness"
                            value="100%"
                            tone="success"
                            trend="up"
                            delta="tokens + layouts"
                            sparkline={[2, 4, 3, 6, 7, 9]}
                        />
                    </div>
                    <div className="c-6 md-c-3">
                        <KpiCard
                            label="Responsive checks"
                            value="3/3"
                            delta="360 · 768 · 1440"
                            trend="flat"
                        />
                    </div>
                    <div className="c-6 md-c-3">
                        <KpiCard
                            label="Currency sample (compact)"
                            money={currencyPreview.compact}
                            delta={`${currencyPreview.currencyCode} from system_settings`}
                            trend="flat"
                        />
                    </div>
                    <div className="c-6 md-c-3">
                        <KpiCard
                            label="Negative money rule"
                            money={currencyPreview.negative}
                            tone="warning"
                            delta="parentheses, danger tone"
                            trend="down"
                        />
                    </div>
                </div>
            </DataView>

            <div className="grid mt-l" style={{ alignItems: 'start' }}>
                <section className="c-12 md-c-6 card card-pad" aria-labelledby="chk-title">
                    <h2 className="h4" id="chk-title">Shell checklist</h2>
                    <ul className="list-quiet mt-s" style={{ fontSize: 14 }}>
                        {checklist.map((item) => (
                            <li key={item}>
                                <span className="badge badge-success" aria-hidden="true">✓</span>
                                <span>{item}</span>
                            </li>
                        ))}
                    </ul>
                </section>

                <section className="c-12 md-c-6 card card-pad" aria-labelledby="money-title">
                    <h2 className="h4" id="money-title">Currency contract</h2>
                    <p className="caption">
                        All amounts below come from <code className="mono">CurrencyFormatter</code>; the UI never
                        formats money or hardcodes a symbol.
                    </p>
                    <dl className="mt-s">
                        <div className="def-row">
                            <dt>Standard</dt>
                            <dd><Money formatted={currencyPreview.standard} /></dd>
                        </div>
                        <div className="def-row">
                            <dt>Compact (KPI)</dt>
                            <dd><Money formatted={currencyPreview.compact} /></dd>
                        </div>
                        <div className="def-row">
                            <dt>Negative</dt>
                            <dd style={{ color: 'var(--mp-danger-text)' }}>
                                <Money formatted={currencyPreview.negative} />
                            </dd>
                        </div>
                    </dl>

                    <div className="mp-row mt-b">
                        <StatusBadge family="appointment" status="pending" />
                        <StatusBadge family="appointment" status="accepted" />
                        <StatusBadge family="payment" status="paid" />
                        <StatusBadge family="stock" status="critical" />
                        <StatusBadge family="clinical" status="emergency" />
                    </div>
                    <p className="caption mt-s">
                        Status labels/tones come from the server mapping; the emergency badge is scoped to red-flag
                        rows only.
                    </p>
                </section>
            </div>

            <section className="card card-pad mt-l" aria-labelledby="next-title">
                <h2 className="h4" id="next-title">Foundation links</h2>
                <div className="mp-row mt-s">
                    <a className="btn btn-primary" href="/dev/ui">Component gallery</a>
                    <a className="btn btn-outline" href={`/dev/layouts/${role.value}`}>This shell at 360/768/1440</a>
                    <a className="btn btn-quiet" href="/">Foundation home</a>
                </div>
                <p className="caption mt-s">
                    Data views expose <code className="mono">?state=skeleton|empty|error|success</code> so every
                    state is demonstrable without a code change.
                </p>
            </section>
        </RoleLayout>
    );
}