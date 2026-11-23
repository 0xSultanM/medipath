/**
 * EmergencyBlocks — COMPONENT-SPEC §12 (SCOPED RED ZONE).
 * Call cards (tel: ≥72px), red-flag accordion grouped by category, time-bounded
 * banner with role=alert, offline cached note, and safe-default fallback when
 * the rules/contacts fetch fails. Reproduces prototypes/guest/04-emergency.
 * Emergency red is intentional here and nowhere else (G2).
 */
import type { DataState, EmergencyContactItem } from '@/types';
import { formatDate } from '@/lib/format';
import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';

export function EmergencyCallCard({ contact }: { contact: EmergencyContactItem }) {
    return (
        <a className="call-card" href={contact.tel}>
            <span className="sr-only">Call </span>
            <span className="cc-ico" aria-hidden="true">{contact.icon ?? '☎'}</span>
            <span className="cc-body">
                <span className="cc-cat">{contact.label}</span>
                <span className="cc-num">{contact.number}</span>
            </span>
        </a>
    );
}

export function EmergencyBanner({
    title,
    message,
    startsAt,
    endsAt,
    live = true,
}: {
    title: string;
    message: string;
    startsAt?: string | null;
    endsAt?: string | null;
    live?: boolean;
}) {
    const { settings } = usePage<SharedProps>().props;

    return (
        <div className="emg-banner" role={live ? 'alert' : undefined}>
            <span aria-hidden="true" style={{ fontSize: 20 }}>🚨</span>
            <div style={{ flex: 1, minWidth: 200 }}>
                <strong>{title}</strong>
                <div>{message}</div>
                {startsAt || endsAt ? (
                    <div className="win">
                        Active {startsAt ? formatDate(startsAt, { ...settings, withTime: true }) : 'now'}
                        {' – '}
                        {endsAt ? formatDate(endsAt, { ...settings, withTime: true }) : 'until further notice'}
                    </div>
                ) : null}
            </div>
        </div>
    );
}

export function RedFlagAccordion({
    groups,
}: {
    groups: Array<{ category: string; label?: string; items: Array<{ id: number; title: string; directive: string }> }>;
}) {
    return (
        <div className="stack-sm">
            {groups.map((group) => (
                <details className="flag-acc" key={group.category}>
                    <summary>
                        <span aria-hidden="true" style={{ color: 'var(--mp-emergency)' }}>⚠</span>
                        {group.label ?? group.category}
                    </summary>
                    <ul className="f-body">
                        {group.items.map((item) => (
                            <li key={item.id}>
                                <strong>{item.title}</strong>
                                <div className="caption">{item.directive}</div>
                            </li>
                        ))}
                    </ul>
                </details>
            ))}
        </div>
    );
}

export function OfflineNote({ cachedAt }: { cachedAt?: string | null }) {
    const { settings } = usePage<SharedProps>().props;

    return (
        <div className="offline-note" role="note">
            <span aria-hidden="true">📶</span>
            <span>
                Can’t connect right now; these numbers stay available on this device.
                {cachedAt ? ` Cached: ${formatDate(cachedAt, { ...settings, withTime: true })}.` : ''}
            </span>
        </div>
    );
}

interface EmergencyKitProps {
    contacts: EmergencyContactItem[];
    /** Last-known contacts used if the live fetch fails (never empty). */
    fallbackContacts?: EmergencyContactItem[];
    groups: Array<{ category: string; label?: string; items: Array<{ id: number; title: string; directive: string }> }>;
    banner?: { title: string; message: string; startsAt?: string | null; endsAt?: string | null } | null;
    state?: DataState;
    onRetry?: () => void;
    cachedAt?: string | null;
}

export function EmergencyKit({
    contacts,
    fallbackContacts = [],
    groups,
    banner,
    state = 'success',
    onRetry,
    cachedAt,
}: EmergencyKitProps) {
    if (state === 'skeleton') {
        return (
            <div className="stack" aria-busy="true">
                <div className="call-grid">
                    {[0, 1, 2].map((card) => (
                        <span className="sk" style={{ height: 72 }} key={card} aria-hidden="true" />
                    ))}
                </div>
                <div className="stack-sm">
                    {[0, 1, 2].map((row) => (
                        <span className="sk" style={{ height: 48 }} key={row} aria-hidden="true" />
                    ))}
                </div>
            </div>
        );
    }

    const shownContacts = state === 'error' || state === 'empty'
        ? fallbackContacts
        : contacts;

    return (
        <div className="stack" role="region" aria-label="Emergency">
            {banner ? (
                <EmergencyBanner
                    title={banner.title}
                    message={banner.message}
                    startsAt={banner.startsAt}
                    endsAt={banner.endsAt}
                />
            ) : null}

            {state === 'error' ? (
                <div className="alert alert-emergency" role="alert">
                    <span className="a-ico" aria-hidden="true">⚠</span>
                    <div className="a-body">
                        <strong className="a-title">Live emergency details could not refresh.</strong>
                        <p>Showing the last verified contacts. Call the national hotline immediately if life is at risk.</p>
                        <button type="button" className="btn btn-quiet btn-sm" onClick={onRetry}>
                            Retry
                        </button>
                    </div>
                </div>
            ) : null}

            {state === 'empty' ? (
                <div className="alert alert-warning" role="status">
                    <span className="a-ico" aria-hidden="true">⚠</span>
                    <div className="a-body">
                        <strong className="a-title">Custom contacts unavailable — showing safe defaults.</strong>
                        <p>The national emergency number remains tappable. Admin-managed contacts reappear after the next successful fetch.</p>
                    </div>
                </div>
            ) : null}

            {shownContacts.length === 0 ? (
                <div className="alert alert-emergency" role="alert">
                    <span className="a-ico" aria-hidden="true">🚨</span>
                    <div className="a-body">
                        <strong className="a-title">Emergency numbers are temporarily unavailable.</strong>
                        <p>Call your local emergency number now. Do not wait for this page to load.</p>
                        <button type="button" className="btn btn-quiet btn-sm" onClick={onRetry}>
                            Retry
                        </button>
                    </div>
                </div>
            ) : (
                <div className="call-grid">
                    {shownContacts.map((contact) => (
                        <EmergencyCallCard key={contact.key} contact={contact} />
                    ))}
                </div>
            )}

            {groups.length > 0 ? (
                <section className="stack-sm" aria-labelledby="red-flags-title">
                    <h2 className="h3" id="red-flags-title">Red flags — call immediately</h2>
                    <RedFlagAccordion groups={groups} />
                </section>
            ) : null}

            <OfflineNote cachedAt={cachedAt} />
        </div>
    );
}