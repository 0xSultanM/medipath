/**
 * RetryBanner — COMPONENT-SPEC §15 / §17 (error contract: cause + Retry +
 * last-known time, keeping current filters). Used by every Error variant.
 */
import { formatDate } from '@/lib/format';
import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';

interface RetryBannerProps {
    message: string;
    onRetry: () => void;
    lastKnownAt?: string | null;
    retryLabel?: string;
    /** Keep the user's current page/filters (default true by contract). */
    preserveFilters?: boolean;
}

export function RetryBanner({
    message,
    onRetry,
    lastKnownAt,
    retryLabel = 'Retry',
    preserveFilters = true,
}: RetryBannerProps) {
    const { settings } = usePage<SharedProps>().props;

    return (
        <div className="retry-banner" role="alert">
            <span className="ico" aria-hidden="true">⚠</span>
            <span className="txt">
                <strong>{message}</strong>
                <span className="last-known">
                    {lastKnownAt
                        ? `Last known data: ${formatDate(lastKnownAt, { ...settings, withTime: true })}`
                        : 'No cached data available.'}
                    {preserveFilters ? ' Your filters were kept.' : ''}
                </span>
            </span>
            <button type="button" className="btn btn-primary" onClick={onRetry}>
                {retryLabel}
            </button>
        </div>
    );
}