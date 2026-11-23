/**
 * PdfButton — COMPONENT-SPEC §16 (Kind=Prescription|Invoice, State=Idle|
 * Loading|Ready|Disabled). Ownership is enforced server-side; the button only
 * surfaces readiness. Shared PDF builder across roles (PLAN §8).
 */
import { cn } from '@/lib/format';
import { Spinner } from '@/Components/Feedback/FeedbackKit';

interface PdfButtonProps {
    kind: 'prescription' | 'invoice';
    state?: 'idle' | 'loading' | 'ready' | 'disabled';
    href?: string;
    onGenerate?: () => void;
    disabledReason?: string;
    label?: string;
    className?: string;
    size?: 'md' | 'sm';
}

export function PdfButton({
    kind,
    state = 'idle',
    href,
    onGenerate,
    disabledReason,
    label,
    className,
    size = 'md',
}: PdfButtonProps) {
    const text = label ?? (kind === 'prescription' ? 'Rx PDF' : 'Invoice PDF');
    const disabled = state === 'disabled' || state === 'loading';

    const content = (
        <>
            <span className="sr-only">Download </span>
            {state === 'loading' ? <Spinner label={`Preparing ${text}`} /> : <span aria-hidden="true">⤓</span>}
            <span className="txt">{state === 'loading' ? 'Preparing…' : text}</span>
        </>
    );

    if (state === 'ready' && href) {
        return (
            <a
                className={cn('btn btn-outline pdf-btn', size === 'sm' && 'btn-sm', className)}
                href={href}
                download
            >
                {content}
            </a>
        );
    }

    return (
        <button
            type="button"
            className={cn(
                'btn btn-outline pdf-btn',
                size === 'sm' && 'btn-sm',
                state === 'loading' && 'is-loading',
                className,
            )}
            disabled={disabled}
            title={state === 'disabled' ? disabledReason ?? 'PDF unavailable — record still linked' : undefined}
            onClick={onGenerate}
        >
            {content}
        </button>
    );
}