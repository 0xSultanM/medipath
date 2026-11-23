/**
 * ToastStack + FlashToasts — COMPONENT-SPEC §15 (Toasts).
 * role=status except danger (role=alert); danger persists until dismissed.
 * Emergency tone is reserved for in-app red-flag notices.
 */
import { useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';
import { useToast, type ToastTone } from '@/providers/ToastProvider';
import { cn } from '@/lib/format';

const ICON: Record<ToastTone, string> = {
    success: '✓',
    danger: '!',
    info: 'i',
    emergency: '🚨',
};

const TONE_CLASS: Record<ToastTone, string> = {
    success: 'toast-success',
    danger: 'toast-danger',
    info: 'toast-info',
    emergency: 'toast-emergency',
};

export function ToastStack() {
    const { toasts, dismiss } = useToast();

    if (toasts.length === 0) return null;

    return (
        <div className="toast-stack">
            {toasts.map((toast) => (
                <div
                    key={toast.id}
                    className={cn('toast', TONE_CLASS[toast.tone])}
                    role={toast.tone === 'danger' ? 'alert' : 'status'}
                >
                    <span className="t-ico" aria-hidden="true">{ICON[toast.tone]}</span>
                    <span className="t-msg">{toast.message}</span>
                    {toast.actionLabel && toast.onAction ? (
                        <button type="button" className="btn btn-ghost btn-sm t-action" onClick={toast.onAction}>
                            {toast.actionLabel}
                        </button>
                    ) : null}
                    <button
                        type="button"
                        className="t-close"
                        onClick={() => dismiss(toast.id)}
                        aria-label="Dismiss notification"
                    >
                        ✕
                    </button>
                </div>
            ))}
        </div>
    );
}

/** Mirrors Laravel flash messages into the toast stack on every Inertia visit. */
export function FlashToasts() {
    const { flash } = usePage<SharedProps>().props;
    const { push } = useToast();

    useEffect(() => {
        if (flash?.success) push({ tone: 'success', message: flash.success });
        if (flash?.error) push({ tone: 'danger', message: flash.error, persistent: true });
        if (flash?.info) push({ tone: 'info', message: flash.info });
        // Flash values are consumed once per page response.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [flash?.success, flash?.error, flash?.info]);

    return null;
}