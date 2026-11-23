import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ThemeProvider } from '@/providers/ThemeProvider';
import { ToastProvider } from '@/providers/ToastProvider';
import { AppErrorBoundary } from '@/Components/Feedback/AppErrorBoundary';

const appName = import.meta.env.VITE_APP_NAME || 'MediPath';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const pageProps = props.initialPage.props as Record<string, any>;
        const ui = pageProps.ui ?? {};
        const auth = pageProps.auth ?? {};

        createRoot(el).render(
            <AppErrorBoundary>
                <ThemeProvider
                    initialTheme={ui.theme ?? 'light'}
                    initialPreference={ui.themePreference ?? 'system'}
                    authenticated={Boolean(auth.user)}
                    csrfToken={pageProps.csrfToken ?? ''}
                >
                    <ToastProvider>
                        <App {...props} />
                    </ToastProvider>
                </ThemeProvider>
            </AppErrorBoundary>,
        );
    },
    progress: {
        color: 'var(--mp-primary)',
        showSpinner: false,
    },
});