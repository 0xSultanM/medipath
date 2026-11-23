/**
 * StarRating + RatingDistribution — COMPONENT-SPEC §9
 * (Mode=Read|Input|Summary, half-star via clip, required input error).
 * Reproduces the rating summary on prototypes/guest/03-doctor-profile and the
 * review input on prototypes/patient/03-appointments.
 */
import { useId } from 'react';
import type { DataState } from '@/types';
import { cn } from '@/lib/format';

export function Stars({
    value,
    label,
    size = 'sm',
}: {
    value: number;
    label: string;
    size?: 'sm' | 'lg';
}) {
    const percent = Math.max(0, Math.min(100, (value / 5) * 100));

    return (
        <span
            className={cn('stars', size === 'lg' && 'stars-lg')}
            role="img"
            aria-label={label}
            title={label}
        >
            <span aria-hidden="true" style={{ position: 'relative', display: 'inline-block', letterSpacing: '1px' }}>
                <span className="off">★★★★★</span>
                <span
                    style={{
                        position: 'absolute',
                        inset: 0,
                        overflow: 'hidden',
                        width: `${percent}%`,
                        color: 'var(--mp-warning)',
                        whiteSpace: 'nowrap',
                    }}
                >
                    ★★★★★
                </span>
            </span>
        </span>
    );
}

export function StarRatingInput({
    value,
    onChange,
    error,
    legend = 'Your rating',
}: {
    value: number;
    onChange: (value: number) => void;
    error?: string | null;
    legend?: string;
}) {
    const id = useId();
    const errorId = `${id}-error`;

    return (
        <fieldset
            className={cn('field', error && 'is-invalid')}
            style={{ margin: 0, border: 0, padding: 0 }}
            aria-describedby={error ? errorId : undefined}
        >
            <legend className="label req">{legend}</legend>
            <div className="rate-input">
                {[5, 4, 3, 2, 1].map((star) => (
                    <span key={star} style={{ display: 'contents' }}>
                        <input
                            type="radio"
                            id={`${id}-${star}`}
                            name={id}
                            value={star}
                            checked={value === star}
                            onChange={() => onChange(star)}
                        />
                        <label htmlFor={`${id}-${star}`} aria-label={`${star} star${star === 1 ? '' : 's'}`}>
                            ★
                        </label>
                    </span>
                ))}
            </div>
            {error ? (
                <p className="field-error" id={errorId} role="alert">
                    <span aria-hidden="true">⚠</span> {error}
                </p>
            ) : null}
        </fieldset>
    );
}

export function RatingDistribution({
    distribution,
    total,
    state = 'success',
    onRetry,
}: {
    distribution: Record<number, number>;
    total: number;
    state?: DataState;
    onRetry?: () => void;
}) {
    if (state === 'skeleton') {
        return (
            <div className="dist" aria-hidden="true">
                {[5, 4, 3, 2, 1].map((star) => (
                    <div className="d-row" key={star}>
                        <span className="sk sk-line" style={{ width: 40 }} />
                        <span className="sk" style={{ height: 8, borderRadius: 999 }} />
                        <span className="sk sk-line" style={{ width: 30 }} />
                    </div>
                ))}
            </div>
        );
    }

    if (state === 'error') {
        return (
            <div className="alert alert-danger" role="alert">
                <span className="a-ico" aria-hidden="true">⚠</span>
                <div className="a-body">
                    <strong className="a-title">Ratings temporarily unavailable.</strong>
                    <button type="button" className="btn btn-ghost btn-sm" onClick={onRetry}>Retry</button>
                </div>
            </div>
        );
    }

    if (state === 'empty' || total === 0) {
        return (
            <p className="caption" role="status">
                No approved reviews yet — be the first to share your experience after a completed visit.
            </p>
        );
    }

    return (
        <div className="dist">
            {[5, 4, 3, 2, 1].map((star) => {
                const count = distribution[star] ?? 0;
                const percent = total > 0 ? Math.round((count / total) * 100) : 0;

                return (
                    <div className="d-row" key={star}>
                        <span>{star}★</span>
                        <span
                            className="d-bar"
                            role="img"
                            aria-label={`${star} star: ${count} reviews, ${percent} percent`}
                        >
                            <span className="d-fill" style={{ width: `${percent}%` }} />
                        </span>
                        <span className="tabular" style={{ textAlign: 'right' }}>{percent}%</span>
                    </div>
                );
            })}
        </div>
    );
}

export function RatingSummary({
    average,
    total,
    distribution,
    state = 'success',
    onRetry,
}: {
    average: number;
    total: number;
    distribution: Record<number, number>;
    state?: DataState;
    onRetry?: () => void;
}) {
    if (state === 'skeleton') {
        return (
            <div className="rating-summary" aria-busy="true">
                <span className="sk" style={{ width: 88, height: 56 }} aria-hidden="true" />
                <div style={{ flex: 1 }}>
                    <RatingDistribution distribution={distribution} total={total} state="skeleton" />
                </div>
            </div>
        );
    }

    return (
        <div className="rating-summary">
            <div className="rating-score">
                <span className="num">{average.toFixed(1)}</span>
                <Stars value={average} label={`Average rating ${average.toFixed(1)} out of 5`} />
                <span className="caption">{total} approved review{total === 1 ? '' : 's'}</span>
            </div>
            <div style={{ flex: 1, minWidth: 220 }}>
                <RatingDistribution
                    distribution={distribution}
                    total={total}
                    state={state}
                    onRetry={onRetry}
                />
            </div>
        </div>
    );
}