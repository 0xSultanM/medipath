import {
    createContext,
    useCallback,
    useContext,
    useMemo,
    useRef,
    useState,
    type ReactNode,
} from 'react';

export type ToastTone = 'success' | 'danger' | 'info' | 'emergency';

export interface ToastItem {
    id: number;
    tone: ToastTone;
    message: string;
    actionLabel?: string;
    onAction?: () => void;
    /** Danger toasts persist until dismissed (§15). */
    persistent?: boolean;
}

interface ToastContextValue {
    toasts: ToastItem[];
    push: (toast: Omit<ToastItem, 'id'>) => number;
    dismiss: (id: number) => void;
}

const ToastContext = createContext<ToastContextValue | null>(null);

export function ToastProvider({ children }: { children: ReactNode }) {
    const [toasts, setToasts] = useState<ToastItem[]>([]);
    const nextId = useRef(1);

    const dismiss = useCallback((id: number) => {
        setToasts((current) => current.filter((toast) => toast.id !== id));
    }, []);

    const push = useCallback(
        (toast: Omit<ToastItem, 'id'>) => {
            const id = nextId.current++;
            setToasts((current) => [...current, { ...toast, id }]);

            if (!toast.persistent && toast.tone !== 'danger') {
                window.setTimeout(() => dismiss(id), 6000);
            }

            return id;
        },
        [dismiss],
    );

    const value = useMemo(() => ({ toasts, push, dismiss }), [toasts, push, dismiss]);

    return <ToastContext.Provider value={value}>{children}</ToastContext.Provider>;
}

export function useToast(): ToastContextValue {
    const ctx = useContext(ToastContext);
    if (!ctx) throw new Error('useToast must be used inside ToastProvider');
    return ctx;
}