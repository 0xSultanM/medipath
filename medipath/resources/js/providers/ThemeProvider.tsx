import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useRef,
    useState,
    type ReactNode,
} from 'react';

export type ThemeMode = 'light' | 'dark';
export type ThemePreference = ThemeMode | 'system';

interface ThemeContextValue {
    theme: ThemeMode;
    preference: ThemePreference;
    setPreference: (preference: ThemePreference) => void;
    toggle: () => void;
}

const ThemeContext = createContext<ThemeContextValue | null>(null);

interface ThemeProviderProps {
    initialTheme: ThemeMode;
    initialPreference: ThemePreference;
    authenticated: boolean;
    csrfToken: string;
    children: ReactNode;
}

function systemTheme(): ThemeMode {
    if (typeof window === 'undefined') return 'light';
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function applyTheme(theme: ThemeMode): void {
    const root = document.documentElement;
    root.setAttribute('data-theme', theme);
    // Bootstrap 5.3 dark mode stays in lockstep with --mp-* (G7).
    root.setAttribute('data-bs-theme', theme);
}

export function ThemeProvider({
    initialTheme,
    initialPreference,
    authenticated,
    csrfToken,
    children,
}: ThemeProviderProps) {
    const [preference, setPreferenceState] = useState<ThemePreference>(initialPreference);
    const [theme, setTheme] = useState<ThemeMode>(initialTheme);
    const mediaRef = useRef<MediaQueryList | null>(null);

    const persist = useCallback(
        (next: ThemePreference) => {
            if (next === 'system') {
                localStorage.removeItem('mp-theme');
            } else {
                localStorage.setItem('mp-theme', next);
            }
            document.cookie = `mp-theme=${next}; path=/; max-age=31536000; samesite=lax`;

            if (authenticated) {
                void fetch('/preferences/theme', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ theme: next }),
                }).catch(() => {
                    /* Preference is cached locally; a failed write is retried on next change. */
                });
            }
        },
        [authenticated, csrfToken],
    );

    const setPreference = useCallback(
        (next: ThemePreference) => {
            setPreferenceState(next);
            const resolved = next === 'system' ? systemTheme() : next;
            setTheme(resolved);
            applyTheme(resolved);
            persist(next);
        },
        [persist],
    );

    const toggle = useCallback(() => {
        setPreference(theme === 'dark' ? 'light' : 'dark');
    }, [theme, setPreference]);

    // Reconcile with the system preference while in System mode.
    useEffect(() => {
        if (preference !== 'system') return;

        const media = window.matchMedia('(prefers-color-scheme: dark)');
        mediaRef.current = media;
        const resolved = media.matches ? 'dark' : 'light';
        setTheme(resolved);
        applyTheme(resolved);

        const onChange = (event: MediaQueryListEvent) => {
            const next = event.matches ? 'dark' : 'light';
            setTheme(next);
            applyTheme(next);
        };
        media.addEventListener('change', onChange);
        return () => media.removeEventListener('change', onChange);
    }, [preference]);

    useEffect(() => {
        applyTheme(theme);
    }, [theme]);

    const value = useMemo(
        () => ({ theme, preference, setPreference, toggle }),
        [theme, preference, setPreference, toggle],
    );

    return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

export function useTheme(): ThemeContextValue {
    const ctx = useContext(ThemeContext);
    if (!ctx) throw new Error('useTheme must be used inside ThemeProvider');
    return ctx;
}