/**
 * DataView — COMPONENT-SPEC §0 (data states) + §17 (cross-component patterns).
 * Wraps every data-bound region behind one State=Success|Skeleton|Empty|Error
 * variant. The state switcher (dev/QA) is optional and keyboard accessible.
 */
import type { ReactNode } from 'react';
import type { DataState } from '@/types';
import { cn } from '@/lib/format';
import { EmptyState } from '@/Components/Feedback/EmptyState';
import { RetryBanner } from '@/Components/Feedback/RetryBanner';

interface DataViewProps {
    /** e.g. "Data view · Featured doctors (server: doctors?featured=1)" */
    name: string;
    state: DataState;
    onStateChange?: (state: DataState) => void;
    showSwitch?: boolean;
    onRetry?: () => void;
    errorMessage?: string;
    lastKnownAt?: string | null;
    emptyTitle?: string;
    emptyBody?: string;
    emptyAction?: ReactNode;
    skeleton?: ReactNode;
    children: ReactNode;
    className?: string;
    /** Hide the dashed dev frame in production screens. */
    framed?: boolean;
}

const STATES: Array<{ value: DataState; label: string }> = [
    { value: 'success', label: 'Success' },
    { value: 'skeleton', label: 'Skeleton' },
    { value: 'empty', label: 'Empty' },
    { value: 'error', label: 'Error + Retry' },
];

export function DataView({
    name,
    state,
    onStateChange,
    showSwitch = false,
    onRetry,
    errorMessage,
    lastKnownAt,
    emptyTitle = 'Nothing here yet',
    emptyBody = 'There is no data for the current filters. Adjust the filters or wait for the first record.',
    emptyAction,
    skeleton,
    children,
    className,
    framed = true,
}: DataViewProps) {
    return (
        <section className={cn(framed && 'dataview', className)} aria-label={name}>
            {(framed || showSwitch) && (
                <div className="dv-bar">
                    <span className="dv-name">{name}</span>
                    {showSwitch && onStateChange && (
                        <div className="dv-switch" role="group" aria-label={`Preview ${name} data state`}>
                            {STATES.map((option) => (
                                <button
                                    key={option.value}
                                    type="button"
                                    aria-pressed={state === option.value}
                                    onClick={() => onStateChange(option.value)}
                                >
                                    {option.label}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            )}

            <div className="dv-host" data-state={state}>
                {state === 'skeleton' && (skeleton ?? <DefaultSkeleton />)}
                {state === 'empty' && (
                    <EmptyState title={emptyTitle} body={emptyBody} action={emptyAction} />
                )}
                {state === 'error' && (
                    <RetryBanner
                        message={errorMessage ?? 'We could not load this data.'}
                        lastKnownAt={lastKnownAt}
                        onRetry={onRetry ?? (() => undefined)}
                    />
                )}
                {state === 'success' && children}
            </div>
        </section>
    );
}

function DefaultSkeleton() {
    return (
        <div className="sk-table" aria-hidden="true">
            {[0, 1, 2].map((row) => (
                <div className="sk-row" key={row}>
                    <span className="sk sk-line" style={{ width: '82%' }} />
                    <span className="sk sk-line" style={{ width: '64%' }} />
                    <span className="sk sk-line" style={{ width: '48%' }} />
                    <span className="sk sk-line" style={{ width: '56%' }} />
                </div>
            ))}
        </div>
    );
}