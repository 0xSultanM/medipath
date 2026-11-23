/**
 * StatusBadge — COMPONENT-SPEC §6 (Family=Appointment|Stock|Expiry|Payment|
 * Account|Clinical). Labels and tones come from the server's DB enum mapping
 * (G6); the component never hardcodes role/status text. Emergency red is
 * limited to Family=Clinical Status=emergency (scoped red, G2).
 */
import { usePage } from '@inertiajs/react';
import type { SharedProps, Tone } from '@/types';
import { cn } from '@/lib/format';

type BadgeFamily =
    | 'appointment'
    | 'prescription'
    | 'payment'
    | 'account'
    | 'verification'
    | 'moderation'
    | 'stock'
    | 'expiry'
    | 'interaction'
    | 'allergy'
    | 'clinical'
    | 'ai';

interface StatusBadgeProps {
    family: BadgeFamily;
    status: string;
    /** Optional server-supplied label override (e.g. a moderated reason). */
    label?: string;
    size?: 'sm' | 'md';
    variant?: 'soft' | 'solid' | 'outline';
    state?: 'success' | 'skeleton' | 'empty' | 'error';
    className?: string;
}

const TONE_CLASS: Record<Tone, string> = {
    neutral: 'badge-neutral',
    primary: 'badge-primary',
    success: 'badge-success',
    warning: 'badge-warning',
    danger: 'badge-danger',
    'danger-outline': 'badge-danger is-outline',
    info: 'badge-info',
    emergency: 'badge-emergency',
    'neutral-strong': 'badge-neutral is-outline',
};

export function StatusBadge({
    family,
    status,
    label,
    size = 'md',
    variant = 'soft',
    state = 'success',
    className,
}: StatusBadgeProps) {
    const { labels, tones } = usePage<SharedProps>().props;

    if (state === 'skeleton') {
        return <span className={cn('badge is-skeleton', className)} aria-hidden="true" />;
    }

    if (state === 'empty') {
        return (
            <span className={cn('badge is-empty', className)}>
                <span aria-hidden="true">—</span>
                <span className="sr-only">No status</span>
            </span>
        );
    }

    if (state === 'error') {
        return (
            <span className={cn('badge is-unknown', className)} title="Status temporarily unavailable">
                Unknown status
            </span>
        );
    }

    const tone: Tone = (tones?.[family]?.[status] as Tone) ?? 'neutral';
    const text = label ?? labels?.[family]?.[status] ?? status.replaceAll('_', ' ');
    const variantClass =
        variant === 'solid' ? 'is-solid' : variant === 'outline' ? 'is-outline' : '';

    return (
        <span
            className={cn('badge', TONE_CLASS[tone], variantClass, size === 'sm' && 'badge-sm', className)}
            title={text}
        >
            <span className="dot" aria-hidden="true" />
            {text}
        </span>
    );
}