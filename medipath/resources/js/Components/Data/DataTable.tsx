/**
 * DataTable — COMPONENT-SPEC §4 (sortable headers, server pager, <640 card
 * conversion via data-label pseudo-headers) + the four data states.
 * Reproduces the canonical list tables in prototypes/admin/02-users,
 * 09-activity, pharmacist/05-inventory, patient/03-appointments, etc.
 */
import type { ReactNode } from 'react';
import type { DataState, PageMeta } from '@/types';
import { cn } from '@/lib/format';
import { EmptyState } from '@/Components/Feedback/EmptyState';
import { RetryBanner } from '@/Components/Feedback/RetryBanner';

export interface DataColumn<T> {
    key: string;
    label: string;
    sortable?: boolean;
    align?: 'left' | 'right' | 'center';
    /** Mobile pseudo-header text; defaults to label. */
    dataLabel?: string;
    render: (row: T) => ReactNode;
    className?: string;
    /** Hide on the card view (e.g. redundant ID). */
    hideOnCard?: boolean;
}

interface DataTableProps<T> {
    /** Visible table title. */
    title?: string;
    /** Required accessible caption (role="table" description). */
    caption: string;
    columns: Array<DataColumn<T>>;
    rows: T[];
    getRowKey: (row: T) => string | number;
    state?: DataState;
    onRetry?: () => void;
    lastKnownAt?: string | null;
    errorMessage?: string;
    sort?: { key: string; direction: 'asc' | 'desc' } | null;
    onSort?: (key: string) => void;
    page?: PageMeta | null;
    onPageChange?: (page: number) => void;
    emptyTitle?: string;
    emptyBody?: string;
    emptyAction?: ReactNode;
    /** Per-row action cluster rendered as the last cell (44px targets). */
    actions?: (row: T) => ReactNode;
    actionsLabel?: string;
    skeletonRows?: number;
    footer?: ReactNode;
    className?: string;
}

export function DataTable<T>({
    title,
    caption,
    columns,
    rows,
    getRowKey,
    state = 'success',
    onRetry,
    lastKnownAt,
    errorMessage,
    sort,
    onSort,
    page,
    onPageChange,
    emptyTitle = 'No records match these filters',
    emptyBody = 'Try widening the date range or clearing one of the filters. Nothing has been deleted.',
    emptyAction,
    actions,
    actionsLabel = 'Actions',
    skeletonRows = 5,
    footer,
    className,
}: DataTableProps<T>) {
    return (
        <div className={cn('tbl-wrap', className)}>
            {(title || footer) && (
                <div className="tbl-head">
                    {title ? <span className="tbl-title">{title}</span> : <span />}
                    {footer}
                </div>
            )}

            {state === 'error' ? (
                <div style={{ padding: 'var(--mp-space-base)' }}>
                    <RetryBanner
                        message={errorMessage ?? 'This list could not be loaded.'}
                        lastKnownAt={lastKnownAt}
                        onRetry={onRetry ?? (() => undefined)}
                    />
                </div>
            ) : state === 'empty' ? (
                <EmptyState title={emptyTitle} body={emptyBody} action={emptyAction} />
            ) : state === 'skeleton' ? (
                <SkeletonTable columns={Math.min(columns.length, 5)} rows={skeletonRows} />
            ) : (
                <>
                    <table className="tbl">
                        <caption className="sr-only">{caption}</caption>
                        <thead>
                            <tr>
                                {columns.map((column) => (
                                    <th
                                        key={column.key}
                                        scope="col"
                                        className={cn(column.align === 'right' && 'num', column.className)}
                                        aria-sort={
                                            sort?.key === column.key
                                                ? sort.direction === 'asc'
                                                    ? 'ascending'
                                                    : 'descending'
                                                : undefined
                                        }
                                    >
                                        {column.sortable && onSort ? (
                                            <button
                                                type="button"
                                                className={cn(
                                                    'sort',
                                                    sort?.key === column.key && sort.direction,
                                                )}
                                                onClick={() => onSort(column.key)}
                                                aria-label={`Sort by ${column.label}`}
                                            >
                                                {column.label}
                                            </button>
                                        ) : (
                                            column.label
                                        )}
                                    </th>
                                ))}
                                {actions ? <th scope="col"><span className="sr-only">{actionsLabel}</span></th> : null}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={getRowKey(row)}>
                                    {columns.map((column) => (
                                        <td
                                            key={column.key}
                                            data-label={column.dataLabel ?? column.label}
                                            className={cn(
                                                column.align === 'right' && 'num',
                                                column.hideOnCard && 'hide-xs',
                                                column.className,
                                            )}
                                        >
                                            {column.render(row)}
                                        </td>
                                    ))}
                                    {actions ? (
                                        <td data-label={actionsLabel} data-actions="">
                                            <div className="actions-cell">{actions(row)}</div>
                                        </td>
                                    ) : null}
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    {page && page.last_page > 1 && onPageChange ? (
                        <Pager page={page} onPageChange={onPageChange} />
                    ) : null}
                </>
            )}
        </div>
    );
}

export function Pager({
    page,
    onPageChange,
}: {
    page: PageMeta;
    onPageChange: (page: number) => void;
}) {
    const pages = pageWindow(page.current_page, page.last_page);

    return (
        <nav className="pager" aria-label="Pagination">
            <span className="caption tabular">
                {page.from ?? 0}–{page.to ?? 0} of {page.total}
            </span>

            <div className="pgs">
                <button
                    type="button"
                    className="pg"
                    onClick={() => onPageChange(page.current_page - 1)}
                    disabled={page.current_page <= 1}
                    aria-label="Previous page"
                >
                    ‹
                </button>

                {pages.map((item, index) =>
                    item === '…' ? (
                        <span key={`gap-${index}`} className="pg is-off" aria-hidden="true">…</span>
                    ) : (
                        <button
                            key={item}
                            type="button"
                            className={cn('pg', item === page.current_page && 'is-on')}
                            onClick={() => onPageChange(item)}
                            aria-current={item === page.current_page ? 'page' : undefined}
                            aria-label={`Page ${item}`}
                        >
                            {item}
                        </button>
                    ),
                )}

                <button
                    type="button"
                    className="pg"
                    onClick={() => onPageChange(page.current_page + 1)}
                    disabled={page.current_page >= page.last_page}
                    aria-label="Next page"
                >
                    ›
                </button>
            </div>
        </nav>
    );
}

function pageWindow(current: number, last: number): Array<number | '…'> {
    if (last <= 7) return Array.from({ length: last }, (_, index) => index + 1);

    const pages: Array<number | '…'> = [1];
    const start = Math.max(2, current - 1);
    const end = Math.min(last - 1, current + 1);

    if (start > 2) pages.push('…');
    for (let page = start; page <= end; page += 1) pages.push(page);
    if (end < last - 1) pages.push('…');
    pages.push(last);

    return pages;
}

function SkeletonTable({ columns, rows }: { columns: number; rows: number }) {
    const widths = ['82%', '64%', '48%', '56%', '40%'];

    return (
        <div className="sk-table" aria-hidden="true">
            {Array.from({ length: rows }).map((_, row) => (
                <div
                    className="sk-row"
                    key={row}
                    style={{ gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` }}
                >
                    {Array.from({ length: columns }).map((__, column) => (
                        <span
                            key={column}
                            className="sk sk-line"
                            style={{ width: widths[(row + column) % widths.length] }}
                        />
                    ))}
                </div>
            ))}
        </div>
    );
}