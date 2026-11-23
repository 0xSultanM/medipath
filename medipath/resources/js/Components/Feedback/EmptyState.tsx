/**
 * EmptyState — COMPONENT-SPEC §17 (noun + why + way out). Shared atom used by
 * every data view's Empty variant.
 */
import type { ReactNode } from 'react';

interface EmptyStateProps {
    title: string;
    body: string;
    action?: ReactNode;
    glyph?: string;
}

export function EmptyState({ title, body, action, glyph = '🗂' }: EmptyStateProps) {
    return (
        <div className="empty">
            <span className="glyph" aria-hidden="true">{glyph}</span>
            <p className="empty-title">{title}</p>
            <p className="empty-body">{body}</p>
            {action}
        </div>
    );
}