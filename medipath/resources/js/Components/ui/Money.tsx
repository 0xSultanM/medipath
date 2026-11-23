/**
 * Money — COMPONENT-SPEC §17 (Money contract) + tokens.json meta.currency (G1).
 * The ONLY money display primitive. It accepts a string that was already
 * formatted server-side by App\Services\CurrencyFormatter; the client never
 * formats amounts or invents a symbol.
 */
import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';
import { assertFormattedMoney, cn } from '@/lib/format';

interface MoneyProps {
    /** Pre-formatted value, e.g. "{{CURRENCY}} 1,200.00" resolved at runtime. */
    formatted: string;
    /** Visual size; tabular numerals are always on. */
    size?: 'sm' | 'md' | 'lg' | 'kpi';
    className?: string;
    /** Optional accessible label when context is not adjacent text. */
    ariaLabel?: string;
}

const SIZE_CLASS: Record<NonNullable<MoneyProps['size']>, string> = {
    sm: 'money-sm',
    md: '',
    lg: 'money-lg',
    kpi: 'money-kpi',
};

export function Money({ formatted, size = 'md', className, ariaLabel }: MoneyProps) {
    const { settings } = usePage<SharedProps>().props;
    const safe = assertFormattedMoney(formatted, settings.currencySymbol);

    return (
        <span
            className={cn('money tabular', SIZE_CLASS[size], className)}
            aria-label={ariaLabel}
            title={ariaLabel}
        >
            {safe}
        </span>
    );
}