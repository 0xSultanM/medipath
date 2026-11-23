/**
 * GuestLayout — COMPONENT-SPEC §1 shell + §0 page order.
 * Skip-link, public navbar, main landmark, footer, flash toasts, chatbot slot.
 * Reproduces prototypes/guest/* shells at 360/768/1440.
 */
import type { ReactNode } from 'react';
import { useMemo } from 'react';
import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';
import { PublicNavbar } from '@/Components/Navigation/PublicNavbar';
import { FlashToasts, ToastStack } from '@/Components/Feedback/ToastStack';
import { ChatbotWidget } from '@/Components/AI/ChatbotWidget';
import { CHATBOT_OFFLINE_ANSWER, CHATBOT_SUGGESTIONS, createChatbotTransport } from '@/lib/chatbot';
import { useTheme } from '@/providers/ThemeProvider';

interface GuestLayoutProps {
    children: ReactNode;
    title?: string;
    activeHref?: string;
    /** Set false on focused auth screens where the FAB would distract. */
    chatbot?: false;
    /** Override the navbar auth cluster (pending users see guest links). */
    navAuthed?: boolean;
}

export function GuestLayout({ children, title, activeHref, chatbot, navAuthed }: GuestLayoutProps) {
    const { settings, hotlines } = usePage<SharedProps>().props;
    const { theme } = useTheme();
    const transport = useMemo(() => createChatbotTransport(), []);

    return (
        <>
            <a className="skip-link" href="#main">Skip to content</a>
            <PublicNavbar activeHref={activeHref} authed={navAuthed} />

            <main
                id="main"
                className="container"
                style={{ paddingBlock: 'var(--mp-space-xl) var(--mp-space-2xl)' }}
                aria-label={title ?? 'MediPath'}
            >
                {children}
            </main>

            <footer className="site-footer">
                <div className="container">
                    <div className="f-grid">
                        <div>
                            <a className="brand" href="/">
                                <span className="logo" aria-hidden="true">M</span>
                                MediPath
                            </a>
                            <p className="caption mt-s">
                                {settings.clinicName} · Verified appointments and medicine-safety checks.
                                AI assists; it never diagnoses.
                            </p>
                        </div>

                        <div>
                            <div className="f-title">Patients</div>
                            <a className="f-link" href="/doctors">Find doctors</a>
                            <a className="f-link" href="/register">Create an account</a>
                            <a className="f-link" href="/login">Sign in</a>
                        </div>

                        <div>
                            <div className="f-title">Emergency</div>
                            <a className="f-link" href="/emergency">Emergency information</a>
                            <span className="caption" style={{ display: 'block', paddingBlock: 6 }}>
                                Works offline once loaded
                            </span>
                        </div>

                        <div>
                            <div className="f-title">Clinic</div>
                            <span className="caption" style={{ display: 'block', paddingBlock: 6 }}>
                                Currency: {settings.currencyCode} · Timezone: {settings.timezone}
                            </span>
                            <span className="caption" style={{ display: 'block', paddingBlock: 6 }}>
                                Dates: {settings.dateFormat}
                            </span>
                        </div>
                    </div>

                    <div className="f-bottom">
                        <span className="caption">
                            © {new Date().getFullYear()} MediPath. Demonstrator for CSE-479.
                        </span>
                        <span className="caption" data-theme-label={theme}>
                            Theme: {theme}
                        </span>
                    </div>
                </div>
            </footer>

            <FlashToasts />
            <ToastStack />

            {chatbot === false ? null : (
                <ChatbotWidget
                    transport={transport}
                    suggestions={CHATBOT_SUGGESTIONS}
                    offlineAnswer={CHATBOT_OFFLINE_ANSWER}
                    contacts={hotlines}
                />
            )}
        </>
    );
}