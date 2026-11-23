/**
 * AdminModal — COMPONENT-SPEC §13 dialog chrome, thin shell for admin CRUD.
 * Reuses the COMPONENT-SPEC §13 dialog chrome (modal-stage / modal-head /
 * focus trap / Escape). Destructive confirmations continue to use ConfirmModal.
 */
import { useEffect, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { useFocusTrap } from '@/hooks/useUi';
import { cn } from '@/lib/format';

interface AdminModalProps {
    open: boolean;
    title: string;
    subtitle?: ReactNode;
    icon?: string;
    children: ReactNode;
    footer?: ReactNode;
    onClose: () => void;
    wide?: boolean;
    danger?: boolean;
    tone?: 'neutral' | 'danger' | 'emergency';
}

export function AdminModal({
    open,
    title,
    subtitle,
    icon,
    children,
    footer,
    onClose,
    wide = false,
    danger = false,
    tone = 'neutral',
}: AdminModalProps) {
    const trapRef = useFocusTrap(open, onClose);

    useEffect(() => {
        if (!open) return;
        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.body.style.overflow = previous;
        };
    }, [open]);

    if (!open || typeof document === 'undefined') return null;

    return createPortal(
        <div
            className="modal-stage"
            onMouseDown={(event) => {
                if (event.target === event.currentTarget) onClose();
            }}
        >
            <div
                className={cn('modal', wide && 'modal-wide', (danger || tone === 'danger') && 'modal--danger', tone === 'emergency' && 'modal--emergency')}
                role={tone === 'emergency' ? 'alertdialog' : 'dialog'}
                aria-modal="true"
                aria-labelledby="admin-modal-title"
                ref={trapRef}
            >
                <div className="modal-head">
                    <span className="m-icon" aria-hidden="true">{icon ?? '🗂'}</span>
                    <div style={{ minWidth: 0 }}>
                        <h2 className="modal-title" id="admin-modal-title">{title}</h2>
                        {subtitle ? <p className="caption" style={{ margin: 0 }}>{subtitle}</p> : null}
                    </div>
                    <button type="button" className="modal-x" onClick={onClose} aria-label="Close dialog">✕</button>
                </div>
                <div className="modal-body">{children}</div>
                {footer ? <div className="modal-foot">{footer}</div> : null}
            </div>
        </div>,
        document.body,
    );
}