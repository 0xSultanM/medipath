/**
 * FilterBar / FilterDrawer — COMPONENT-SPEC §5 (URL-synced filters, removable
 * tags, Clear All, responsive drawer <768, facet skeleton/error states).
 * Reproduces the directory filter bar in prototypes/guest/02-directory and the
 * list filters across admin/pharmacist screens.
 *
 * The component is controlled: the page owns URL state and passes values back,
 * so filters remain deep-linkable and server-side (G8).
 */
import { useId, useState, type ReactNode } from 'react';
import type { DataState } from '@/types';
import { cn } from '@/lib/format';
import { useDisclosure, useFocusTrap, useMediaQuery } from '@/hooks/useUi';
import { Spinner } from '@/Components/Feedback/FeedbackKit';

export interface FilterOption {
    value: string;
    label: string;
}

export type FilterField =
    | { key: string; label: string; type: 'select'; options: FilterOption[]; placeholder?: string; secondary?: boolean }
    | { key: string; label: string; type: 'sort'; options: FilterOption[]; secondary?: boolean }
    | { key: string; label: string; type: 'toggle'; hint?: string; secondary?: boolean }
    | { key: string; label: string; type: 'range'; min: number; max: number; step?: number; unit?: string; secondary?: boolean }
    | { key: string; label: string; type: 'weekday'; secondary?: boolean };

export interface FilterTag {
    key: string;
    label: string;
    valueLabel?: string;
}

interface FilterBarProps {
    searchValue: string;
    onSearchChange: (value: string) => void;
    searchPlaceholder?: string;
    fields?: FilterField[];
    values: Record<string, unknown>;
    onChange: (key: string, value: unknown) => void;
    onClear?: () => void;
    tags?: FilterTag[];
    onRemoveTag?: (key: string) => void;
    facetState?: DataState;
    onFacetRetry?: () => void;
    resultSummary?: ReactNode;
    /** Extra controls rendered in the inline grid (e.g. weekday picker). */
    children?: ReactNode;
    busy?: boolean;
    className?: string;
}

const WEEKDAYS = [
    { value: '1', short: 'Mon' },
    { value: '2', short: 'Tue' },
    { value: '3', short: 'Wed' },
    { value: '4', short: 'Thu' },
    { value: '5', short: 'Fri' },
    { value: '6', short: 'Sat' },
    { value: '0', short: 'Sun' },
];

export function FilterBar({
    searchValue,
    onSearchChange,
    searchPlaceholder = 'Search by name, specialty, or clinic…',
    fields = [],
    values,
    onChange,
    onClear,
    tags = [],
    onRemoveTag,
    facetState = 'success',
    onFacetRetry,
    resultSummary,
    children,
    busy = false,
    className,
}: FilterBarProps) {
    const isDesktop = useMediaQuery('(min-width: 768px)', true);
    const drawer = useDisclosure(false);
    const drawerRef = useFocusTrap(drawer.isOpen, drawer.close);
    const inputId = useId();
    const [appliedCount, setAppliedCount] = useState(0);

    const activeCount = tags.length;

    const controls = (
        <>
            <div className="field" style={{ margin: 0 }}>
                <label className="label" htmlFor={`${inputId}-search`}>Search</label>
                <div className="input-icon">
                    <span className="ico" aria-hidden="true">🔍</span>
                    <input
                        id={`${inputId}-search`}
                        type="search"
                        role="searchbox"
                        className="input"
                        value={searchValue}
                        placeholder={searchPlaceholder}
                        onChange={(event) => onSearchChange(event.target.value)}
                    />
                </div>
            </div>

            {fields.map((field) => (
                <FilterControl
                    key={field.key}
                    field={field}
                    value={values[field.key]}
                    onChange={(value) => onChange(field.key, value)}
                    facetState={facetState}
                />
            ))}

            {children}
        </>
    );

    if (isDesktop) {
        return (
            <div className={cn('stack', className)}>
                <div className="filter-bar">
                    {controls}
                    <div className="f-actions f-full">
                        {resultSummary ? <span className="caption">{resultSummary}</span> : <span />}
                        <span className="mla" />
                        {busy ? <span className="saving-inline"><Spinner label="Updating results" /> Updating…</span> : null}
                        {onClear && activeCount > 0 ? (
                            <button type="button" className="btn btn-ghost" onClick={onClear}>
                                Clear all filters
                            </button>
                        ) : null}
                    </div>
                </div>

                {facetState === 'error' ? (
                    <div className="alert alert-danger" role="alert">
                        <span className="a-ico" aria-hidden="true">⚠</span>
                        <div className="a-body">
                            <strong className="a-title">Filter options could not be refreshed.</strong>
                            <span className="caption">Your current filters still apply. </span>
                            <button type="button" className="btn btn-ghost btn-sm" onClick={onFacetRetry}>
                                Retry
                            </button>
                        </div>
                    </div>
                ) : null}

                {tags.length > 0 ? (
                    <div className="active-filters" aria-live="polite">
                        <span className="caption">{tags.length} filter{tags.length === 1 ? '' : 's'} applied</span>
                        {tags.map((tag) => (
                            <span className="tag-filter" key={tag.key}>
                                <span>{tag.label}{tag.valueLabel ? `: ${tag.valueLabel}` : ''}</span>
                                {onRemoveTag ? (
                                    <button
                                        type="button"
                                        onClick={() => onRemoveTag(tag.key)}
                                        aria-label={`Remove ${tag.label} filter`}
                                    >
                                        ✕
                                    </button>
                                ) : null}
                            </span>
                        ))}
                        {onClear ? (
                            <button type="button" className="btn btn-ghost btn-sm" onClick={onClear}>
                                Clear all
                            </button>
                        ) : null}
                    </div>
                ) : null}
            </div>
        );
    }

    return (
        <div className={cn('stack-sm', className)}>
            <div className="mp-row">
                <button
                    type="button"
                    className="btn btn-outline"
                    aria-expanded={drawer.isOpen}
                    onClick={drawer.open}
                >
                    <span aria-hidden="true">⛃</span>
                    Filters{activeCount > 0 ? ` (${appliedCount || activeCount})` : ''}
                </button>
                <button
                    type="button"
                    className="btn btn-quiet"
                    onClick={() => {
                        onClear?.();
                        setAppliedCount(0);
                    }}
                    disabled={!onClear || activeCount === 0}
                >
                    Clear all
                </button>
            </div>

            {resultSummary ? <span className="caption">{resultSummary}</span> : null}

            {drawer.isOpen ? (
                <>
                    <button className="backdrop is-open" aria-label="Close filters" onClick={drawer.close} />
                    <div
                        ref={drawerRef}
                        className="drawer drawer--right drawer--filters is-open"
                        role="dialog"
                        aria-modal="true"
                        aria-label="Filters"
                    >
                        <div className="d-title">
                            <span className="h3">Filters</span>
                            <button type="button" className="x-close" data-autofocus onClick={drawer.close} aria-label="Close filters">
                                ✕
                            </button>
                        </div>

                        <div className="filter-drawer-body">
                            {controls}
                            {facetState === 'error' ? (
                                <div className="alert alert-danger" role="alert">
                                    <span className="a-ico" aria-hidden="true">⚠</span>
                                    <div className="a-body">
                                        <strong className="a-title">Options unavailable.</strong>
                                        <button type="button" className="btn btn-ghost btn-sm" onClick={onFacetRetry}>
                                            Retry
                                        </button>
                                    </div>
                                </div>
                            ) : null}
                        </div>

                        <div className="fv-drawer-foot">
                            <button
                                type="button"
                                className="btn btn-primary btn-block"
                                onClick={() => {
                                    setAppliedCount(activeCount);
                                    drawer.close();
                                }}
                            >
                                Apply ({activeCount})
                            </button>
                        </div>
                    </div>
                </>
            ) : null}
        </div>
    );
}

function FilterControl({
    field,
    value,
    onChange,
    facetState,
}: {
    field: FilterField;
    value: unknown;
    onChange: (value: unknown) => void;
    facetState: DataState;
}) {
    const id = useId();

    if (field.type === 'toggle') {
        return (
            <label className="switch" htmlFor={id} style={{ alignSelf: 'end' }}>
                <input
                    id={id}
                    type="checkbox"
                    checked={Boolean(value)}
                    onChange={(event) => onChange(event.target.checked)}
                />
                <span className="label" style={{ margin: 0 }}>{field.label}</span>
            </label>
        );
    }

    if (field.type === 'range') {
        const numeric = typeof value === 'number' ? value : field.min;

        return (
            <div className="field" style={{ margin: 0 }}>
                <label className="label" htmlFor={id}>
                    {field.label} <span className="caption tabular">{numeric}{field.unit ?? ''}</span>
                </label>
                <input
                    id={id}
                    type="range"
                    min={field.min}
                    max={field.max}
                    step={field.step ?? 1}
                    value={numeric}
                    aria-valuetext={`${numeric}${field.unit ?? ''}`}
                    onChange={(event) => onChange(Number(event.target.value))}
                />
            </div>
        );
    }

    if (field.type === 'weekday') {
        const selected = Array.isArray(value) ? (value as string[]) : [];

        return (
            <div className="field" style={{ margin: 0 }}>
                <span className="label" id={id}>Available on</span>
                <div className="weekdays" role="group" aria-labelledby={id}>
                    {WEEKDAYS.map((day) => (
                        <label key={day.value}>
                            <input
                                type="checkbox"
                                checked={selected.includes(day.value)}
                                onChange={(event) => {
                                    const next = event.target.checked
                                        ? [...selected, day.value]
                                        : selected.filter((item) => item !== day.value);
                                    onChange(next);
                                }}
                            />
                            <span>{day.short}</span>
                        </label>
                    ))}
                </div>
            </div>
        );
    }

    const options = facetState === 'skeleton' ? [] : field.options;

    return (
        <div className="field" style={{ margin: 0 }}>
            <label className="label" htmlFor={id}>{field.label}</label>
            {facetState === 'skeleton' ? (
                <span className="sk" style={{ height: 'var(--mp-touch)' }} aria-hidden="true" />
            ) : (
                <select
                    id={id}
                    className="select"
                    value={typeof value === 'string' ? value : ''}
                    onChange={(event) => onChange(event.target.value)}
                >
                    <option value="">
                        {'placeholder' in field && field.placeholder ? field.placeholder : `All ${field.label.toLowerCase()}`}
                    </option>
                    {options.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
            )}
        </div>
    );
}