export function cn(...values: Array<string | false | null | undefined>): string {
    return values.filter(Boolean).join(' ');
}

/** Locale-aware date formatting driven by system_settings (never money). */
export function formatDate(
    value: string | Date | null | undefined,
    options: { locale?: string; timezone?: string; dateFormat?: string; withTime?: boolean } = {},
): string {
    if (!value) return '—';

    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return '—';

    const { locale = 'en', timezone = 'Asia/Dhaka', dateFormat = 'd M Y', withTime = false } = options;

    const patterns: Record<string, Intl.DateTimeFormatOptions> = {
        'd M Y': { day: '2-digit', month: 'short', year: 'numeric' },
        'd/m/Y': { day: '2-digit', month: '2-digit', year: 'numeric' },
        'Y-m-d': { year: 'numeric', month: '2-digit', day: '2-digit' },
        'M j, Y': { month: 'short', day: 'numeric', year: 'numeric' },
    };

    const base: Intl.DateTimeFormatOptions = {
        ...(patterns[dateFormat] ?? patterns['d M Y']),
        timeZone: timezone,
    };

    if (withTime) {
        base.hour = 'numeric';
        base.minute = '2-digit';
    }

    return new Intl.DateTimeFormat(locale, base).format(date);
}

export function relativeTime(value: string | null | undefined): string {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';

    const diffSeconds = Math.round((date.getTime() - Date.now()) / 1000);
    const formatter = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
    const units: Array<[Intl.RelativeTimeFormatUnit, number]> = [
        ['year', 60 * 60 * 24 * 365],
        ['month', 60 * 60 * 24 * 30],
        ['day', 60 * 60 * 24],
        ['hour', 60 * 60],
        ['minute', 60],
    ];

    for (const [unit, seconds] of units) {
        if (Math.abs(diffSeconds) >= seconds) {
            return formatter.format(Math.round(diffSeconds / seconds), unit);
        }
    }

    return formatter.format(diffSeconds, 'second');
}

export function initialsOf(name: string): string {
    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

/** Client only ever receives a pre-formatted money string (G1). */
export function assertFormattedMoney(value: string, currencySymbol: string): string {
    if (import.meta.env.DEV && value.includes('$') && !currencySymbol.includes('$')) {
        // eslint-disable-next-line no-console
        console.warn('Money strings must come from CurrencyFormatter.', value);
    }
    return value;
}