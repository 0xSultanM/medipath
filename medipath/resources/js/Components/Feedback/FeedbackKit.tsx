/**
 * Feedback kit atoms — COMPONENT-SPEC §15:
 * Spinner, InlineError, UnsavedBar. (Toast lives in ToastStack; RetryBanner has
 * its own file because it composes the error contract.)
 */
import { cn } from '@/lib/format';

export function Spinner({
    size = 'sm',
    className,
    label,
}: {
    size?: 'sm' | 'lg';
    className?: string;
    label?: string;
}) {
    return (
        <>
            <span className={cn('spinner', size === 'lg' && 'spinner-lg', className)} aria-hidden="true" />
            {label ? <span className="live-region" role="status">{label}</span> : null}
        </>
    );
}

export function InlineError({
    id,
    message,
    className,
}: {
    id: string;
    message?: string | null;
    className?: string;
}) {
    if (!message) return null;
    return (
        <p className={cn('field-error', className)} id={id} role="alert">
            <span aria-hidden="true">⚠</span>
            <span>{message}</span>
        </p>
    );
}

export function UnsavedBar({
    message = 'You have unsaved changes.',
    onDiscard,
    onStay,
    saving = false,
}: {
    message?: string;
    onDiscard: () => void;
    onStay: () => void;
    saving?: boolean;
}) {
    return (
        <div className="unsaved-bar" role="status">
            <span aria-hidden="true">●</span>
            <span>{message}</span>
            <div className="ub-actions">
                <button type="button" className="btn btn-quiet" onClick={onDiscard} disabled={saving}>
                    Discard
                </button>
                <button type="button" className="btn btn-primary" onClick={onStay} disabled={saving}>
                    {saving ? <Spinner label="Saving" /> : null}
                    Stay
                </button>
            </div>
        </div>
    );
}