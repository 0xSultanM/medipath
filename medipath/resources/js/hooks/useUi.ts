import { useCallback, useEffect, useRef, useState } from 'react';

/** Responsive helper used for drawer/popover differences (G4). */
export function useMediaQuery(query: string, fallback = false): boolean {
    const [matches, setMatches] = useState<boolean>(() => {
        if (typeof window === 'undefined') return fallback;
        return window.matchMedia(query).matches;
    });

    useEffect(() => {
        const media = window.matchMedia(query);
        const onChange = () => setMatches(media.matches);
        onChange();
        media.addEventListener('change', onChange);
        return () => media.removeEventListener('change', onChange);
    }, [query]);

    return matches;
}

export function useDisclosure(initial = false) {
    const [isOpen, setIsOpen] = useState(initial);
    const open = useCallback(() => setIsOpen(true), []);
    const close = useCallback(() => setIsOpen(false), []);
    const toggle = useCallback(() => setIsOpen((value) => !value), []);

    return { isOpen, open, close, toggle, setIsOpen };
}

/**
 * Focus trap for modal/drawer dialogs (§13). Returns focus to the trigger on
 * close and keeps Tab cycling inside the container.
 */
export function useFocusTrap(active: boolean, onClose?: () => void) {
    const containerRef = useRef<HTMLDivElement | null>(null);
    const restoreRef = useRef<HTMLElement | null>(null);

    useEffect(() => {
        if (!active) return;

        restoreRef.current = document.activeElement as HTMLElement | null;
        const container = containerRef.current;
        if (!container) return;

        const selector =
            'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

        const focusables = () => Array.from(container.querySelectorAll<HTMLElement>(selector)).filter(
            (element) => element.offsetParent !== null || element === document.activeElement,
        );

        const first = focusables()[0];
        const preferred = container.querySelector<HTMLElement>('[data-autofocus]');
        (preferred ?? first)?.focus();

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.stopPropagation();
                onClose?.();
                return;
            }

            if (event.key !== 'Tab') return;

            const items = focusables();
            if (items.length === 0) return;

            const firstItem = items[0];
            const lastItem = items[items.length - 1];

            if (event.shiftKey && document.activeElement === firstItem) {
                event.preventDefault();
                lastItem.focus();
            } else if (!event.shiftKey && document.activeElement === lastItem) {
                event.preventDefault();
                firstItem.focus();
            }
        };

        document.addEventListener('keydown', onKeyDown, true);
        document.body.style.overflow = 'hidden';

        return () => {
            document.removeEventListener('keydown', onKeyDown, true);
            document.body.style.overflow = '';
            restoreRef.current?.focus?.();
        };
    }, [active, onClose]);

    return containerRef;
}

export function useEscape(active: boolean, onEscape: () => void) {
    useEffect(() => {
        if (!active) return;
        const handler = (event: KeyboardEvent) => {
            if (event.key === 'Escape') onEscape();
        };
        document.addEventListener('keydown', handler);
        return () => document.removeEventListener('keydown', handler);
    }, [active, onEscape]);
}

export function useDebouncedValue<T>(value: T, delay = 350): T {
    const [debounced, setDebounced] = useState(value);

    useEffect(() => {
        const timeout = window.setTimeout(() => setDebounced(value), delay);
        return () => window.clearTimeout(timeout);
    }, [value, delay]);

    return debounced;
}

/** Scroll shadow for the sticky navbar (§1 State=Scrolled). */
export function useScrolled(threshold = 4): boolean {
    const [scrolled, setScrolled] = useState(false);

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > threshold);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, [threshold]);

    return scrolled;
}