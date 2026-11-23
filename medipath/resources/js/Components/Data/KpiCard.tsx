/**
 * KpiCard — COMPONENT-SPEC §3 (Tone/Trend/Sparkline + 4 states).
 * Reproduces the KPI row in prototypes/admin/01-dashboard, doctor/01,
 * patient/01, pharmacist/01. Money values arrive pre-formatted (G1).
 */
import type { ReactNode } from 'react';
import type { DataState } from '@/types';
import { cn } from '@/lib/format';
import { Money } from '@/Components/ui/Money';

export type KpiTone = 'neutral' | 'success' | 'warning' | 'danger';
export type KpiTrend = 'up' | 'down' | 'flat';

interface KpiCardProps {
    label: string;
    /** Plain value (counts, percentages). */
    value?: string | number | null;
    /** Server-formatted money string; mutually exclusive with value. */
    money?: string | null;
    tone?: KpiTone;
    trend?: KpiTrend;
    delta?: string | null;
    sparkline?: number[] | null;
    state?: DataState;
    onRetry?: () => void;
    lastKnownAt?: string | null;
    href?: string;
    footnote?: ReactNode;
}

const TONE_CLASS: Record<KpiTone, string> = {
    neutral: '',
    success: 'is-good',
    warning: 'is-alert',
    danger: 'is-bad',
};

const TREND_GLYPH: Record<KpiTrend, string> = { up: '▲', down: '▼', flat: '▬' };
const TREND_CLASS: Record<KpiTrend, string> = { up: 'up', down: 'down', flat: 'flat' };

export function KpiCard({
    label,
    value,
    money,
    tone = 'neutral',
    trend = 'flat',
    delta,
    sparkline,
    state = 'success',
    onRetry,
    lastKnownAt,
    href,
    footnote,
}: KpiCardProps) {
    if (state === 'skeleton') {
        return (
            <figure className={cn('kpi', TONE_CLASS[tone])} aria-busy="true">
                <span className="sk sk-line" style={{ width: '55%' }} aria-hidden="true" />
                <span className="sk" style={{ height: 34, marginBlock: 4 }} aria-hidden="true" />
                <span className="sk sk-line" style={{ width: '38%' }} aria-hidden="true" />
                <figcaption className="sr-only">Loading {label}</figcaption>
            </figure>
        );
    }

    if (state === 'error') {
        return (
            <figure className={cn('kpi', TONE_CLASS[tone])}>
                <figcaption className="k-label">{label}</figcaption>
                <span className="k-value" aria-hidden="true">—</span>
                <span className="caption">Couldn’t load KPI</span>
                <button type="button" className="btn btn-ghost btn-sm k-retry" onClick={onRetry}>
                    Retry
                </button>
            </figure>
        );
    }

    const isEmpty = state === 'empty' || (value === null && money === null);

    return (
        <figure className={cn('kpi', TONE_CLASS[tone])}>
            <figcaption className="k-label">{label}</figcaption>

            {isEmpty ? (
                <span className="k-value" aria-label={`${label}: no data for this range`}>—</span>
            ) : money ? (
                <Money formatted={money} size="kpi" className="k-value" ariaLabel={`${label}: ${money}`} />
            ) : (
                <span className="k-value">{value}</span>
            )}

            <div className="k-foot">
                <span className={cn('k-delta', TREND_CLASS[isEmpty ? 'flat' : trend])}>
                    {isEmpty ? (
                        'No data for range'
                    ) : delta ? (
                        <>
                            <span aria-hidden="true">{TREND_GLYPH[trend]}</span>
                            <span>{delta}</span>
                        </>
                    ) : (
                        footnote
                    )}
                </span>

                {sparkline && sparkline.length > 1 && !isEmpty ? (
                    <Sparkline points={sparkline} />
                ) : null}
            </div>

            {href && !isEmpty ? (
                <a href={href} className="btn btn-ghost btn-sm">
                    View details
                </a>
            ) : null}
        </figure>
    );
}

function Sparkline({ points }: { points: number[] }) {
    const max = Math.max(...points);
    const min = Math.min(...points);
    const range = max - min || 1;
    const step = 88 / (points.length - 1);

    const coordinates = points
        .map((point, index) => `${(index * step).toFixed(1)},${(30 - ((point - min) / range) * 26).toFixed(1)}`)
        .join(' ');

    return (
        <span className="k-spark" aria-hidden="true">
            <svg viewBox="0 0 88 32" fill="none">
                <polyline points={coordinates} stroke="currentColor" strokeWidth="2" />
            </svg>
        </span>
    );
}