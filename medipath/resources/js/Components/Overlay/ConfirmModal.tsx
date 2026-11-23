/**
 * ConfirmModal — COMPONENT-SPEC §13 (ConfirmMode=Click|Typed|ReasonRequired,
 * dependency counts, aria-modal dialog with focus trap, mobile bottom sheet).
 * Emergency tone is reserved for red-flag escalation (role="alertdialog",
 * backdrop never dismisses) — routine destructive actions use danger.
 */
import { useEffect, useId, useMemo, useState, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import type { DataState } from '@/types';
import { cn } from '@/lib/format';
import { useFocusTrap } from '@/hooks/useUi';
import { Spinner } from '@/Components/Feedback/FeedbackKit';

export interface DependencyCount {
    label: string;
    count: number;
}

interface ConfirmModalProps {
    open: boolean;
    title: string;
    message: ReactNode;
    tone?: 'neutral' | 'danger' | 'emergency';
    confirmMode?: 'click' | 'typed' | 'reason';
    confirmLabel?: string;
    cancelLabel?: string;
    /** Required exact text for typed mode (e.g. the doctor's name). */
    expectedText?: string;
    reasonLabel?: string;
    reasonPlaceholder?: string;
    reasonMinLength?: number;
    dependencies?: DependencyCount[];
    dependenciesState?: DataState;
    onRetryDependencies?: () => void;
    onConfirm: (payload: { reason?: string }) => void;
    onClose: () => void;
    loading?: boolean;
    error?: string | null;
    icon?: string;
    /** Wide variant (840) for record previews. */
    wide?: boolean;
    children?: ReactNode;
}

export function ConfirmModal({
    open,
    title,
    message,
    tone = 'neutral',
    confirmMode = 'click',
    confirmLabel = 'Confirm',
    cancelLabel = 'Cancel',
    expectedText,
    reasonLabel = 'Reason',
    reasonPlaceholder = 'Explain the decision (visible to the affected user)…',
    reasonMinLength = 10,
    dependencies = [],
    dependenciesState = 'success',
    onRetryDependencies,
    onConfirm,
    onClose,
    loading = false,
    error,
    icon,
    wide = false,
    children,
}: ConfirmModalProps) {
    const titleId = useId();
    const descriptionId = useId();
    const [typed, setTyped] = useState('');
    const [reason, setReason] = useState('');
    const trapRef = useFocusTrap(open, onClose);

    useEffect(() => {
        if (!open) {
            setTyped('');
            setReason('');
        }
    }, [open]);

    const typedOk = confirmMode !== 'typed' || typed === (expectedText ?? '');
    const reasonOk = confirmMode !== 'reason' || reason.trim().length >= reasonMinLength;
    const canConfirm = typedOk && reasonOk && !loading;

    const dialogRole = tone === 'emergency' ? 'alertdialog' : 'dialog';
    const accent = useMemo(
        () => (tone === 'emergency' ? 'modal--emergency' : tone === 'danger' ? 'modal--danger' : ''),
        [tone],
    );

    if (!open || typeof document === 'undefined') return null;

    const requestClose = () => {
        // Typed & emergency dialogs require an explicit cancel/close choice.
        if (confirmMode === 'typed' || tone === 'emergency') return;
        onClose();
    };

    return createPortal(
        <div
            className="modal-stage"
            onMouseDown={(event) => {
                if (event.target === event.currentTarget) requestClose();
            }}
        >
            <div
                className={cn('modal', wide && 'modal-wide', accent)}
                role={dialogRole}
                aria-modal="true"
                aria-labelledby={titleId}
                aria-describedby={descriptionId}
                ref={trapRef}
            >
                <div className="modal-head">
                    <span className="m-icon" aria-hidden="true">
                        {icon ?? (tone === 'emergency' ? '🚨' : tone === 'danger' ? '⚠' : 'ℹ')}
                    </span>
                    <div style={{ minWidth: 0 }}>
                        <h2 className="modal-title" id={titleId}>{title}</h2>
                    </div>
                    <button type="button" className="modal-x" onClick={onClose} aria-label="Close dialog">
                        ✕
                    </button>
                </div>

                <div className="modal-body" id={descriptionId}>
                    <div>{message}</div>

                    {dependenciesState === 'skeleton' ? (
                        <div className="dep-counts" aria-busy="true">
                            <span className="sk" style={{ width: 120, height: 32, borderRadius: 999 }} />
                            <span className="sk" style={{ width: 150, height: 32, borderRadius: 999 }} />
                        </div>
                    ) : dependenciesState === 'error' ? (
                        <div className="alert alert-danger" role="alert">
                            <span className="a-ico" aria-hidden="true">⚠</span>
                            <div className="a-body">
                                <strong className="a-title">Couldn’t load linked records.</strong>
                                <span className="caption">Do not proceed until the counts are known.</span>
                                <button type="button" className="btn btn-ghost btn-sm" onClick={onRetryDependencies}>
                                    Retry
                                </button>
                            </div>
                        </div>
                    ) : dependencies.length === 0 ? (
                        <p className="caption">0 linked records — this action has no downstream impact.</p>
                    ) : (
                        <div className="dep-counts" aria-label="Linked records">
                            {dependencies.map((item) => (
                                <span className="dep" key={item.label}>
                                    <strong className="tabular">{item.count}</strong> {item.label}
                                </span>
                            ))}
                        </div>
                    )}

                    {confirmMode === 'typed' ? (
                        <div className="field" style={{ margin: 0 }}>
                            <label className="label" htmlFor={`${titleId}-typed`}>
                                Type <code>{expectedText}</code> to confirm
                            </label>
                            <input
                                id={`${titleId}-typed`}
                                className="input"
                                value={typed}
                                autoComplete="off"
                                onChange={(event) => setTyped(event.target.value)}
                            />
                            <p className="typed-confirm-hint">
                                Must match exactly. The confirm button stays disabled until it does.
                            </p>
                        </div>
                    ) : null}

                    {confirmMode === 'reason' ? (
                        <div className="field" style={{ margin: 0 }}>
                            <label className="label req" htmlFor={`${titleId}-reason`}>{reasonLabel}</label>
                            <textarea
                                id={`${titleId}-reason`}
                                className="textarea"
                                value={reason}
                                placeholder={reasonPlaceholder}
                                onChange={(event) => setReason(event.target.value)}
                                aria-describedby={`${titleId}-reason-hint`}
                            />
                            <p className="hint" id={`${titleId}-reason-hint`}>
                                {reason.trim().length}/{reasonMinLength} characters minimum.
                            </p>
                        </div>
                    ) : null}

                    {children}

                    {error ? (
                        <div className="alert alert-danger" role="alert">
                            <span className="a-ico" aria-hidden="true">⚠</span>
                            <div className="a-body">
                                <strong className="a-title">The action did not complete.</strong>
                                <p>{error}</p>
                                <p className="caption">Nothing was saved. Retry is safe — the action is idempotent.</p>
                            </div>
                        </div>
                    ) : null}
                </div>

                <div className="modal-foot">
                    <button type="button" className="btn btn-quiet" onClick={onClose} disabled={loading}>
                        {cancelLabel}
                    </button>
                    <button
                        type="button"
                        className={cn(
                            'btn',
                            tone === 'emergency'
                                ? 'btn-emergency'
                                : tone === 'danger'
                                  ? 'btn-danger'
                                  : 'btn-primary',
                        )}
                        disabled={!canConfirm}
                        onClick={() => onConfirm({ reason: confirmMode === 'reason' ? reason.trim() : undefined })}
                    >
                        {loading ? <Spinner label="Submitting" /> : null}
                        {confirmLabel}
                    </button>
                </div>
            </div>
        </div>,
        document.body,
    );
}