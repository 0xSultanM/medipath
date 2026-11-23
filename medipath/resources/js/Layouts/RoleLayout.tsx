/**
 * RoleLayout — COMPONENT-SPEC §2 shell (role sidebar + topbar + bell + theme).
 * One nav DOM instance; drawer <992. Reproduces the four role shells in
 * prototypes/admin/01, doctor/01, pharmacist/01, patient/01.
 */
import { useEffect, useState, type ReactNode } from 'react';
import { usePage } from '@inertiajs/react';
import type { AuthUser, DataState, SharedProps } from '@/types';
import { ROLE_NAV } from '@/lib/nav';
import { RoleSidebar } from '@/Components/Navigation/RoleSidebar';
import { NotificationBell } from '@/Components/Feedback/NotificationBell';
import { ThemeToggle } from '@/Components/Navigation/ThemeToggle';
import { FlashToasts, ToastStack } from '@/Components/Feedback/ToastStack';

interface RoleLayoutProps {
    role?: string;
    title: string;
    subtitle?: string;
    activeHref?: string;
    children: ReactNode;
    topbarActions?: ReactNode;
    /** Dev shell preview: no authenticated session required. */
    preview?: {
        role: string;
        roleLabel: string;
        portal: string;
        name: string;
        initials: string;
        dashboardUrl: string;
    };
    notificationState?: DataState;
}

export function RoleLayout({
    role,
    title,
    subtitle,
    activeHref,
    children,
    topbarActions,
    preview,
    notificationState = 'success',
}: RoleLayoutProps) {
    const { auth, ui } = usePage<SharedProps>().props;
    const user: AuthUser | null = auth.user;
    const [drawerOpen, setDrawerOpen] = useState(false);

    const roleValue = preview?.role ?? role ?? user?.role ?? 'patient';
    const roleLabel = preview?.roleLabel ?? user?.roleLabel ?? '';
    const portal = preview?.portal ?? user?.portal ?? '';
    const name = preview?.name ?? user?.name ?? 'MediPath user';
    const initials = preview?.initials ?? user?.initials ?? 'MP';
    const nav = ROLE_NAV[roleValue] ?? ROLE_NAV.patient;

    useEffect(() => {
        document.body.classList.toggle('mp-reduced-motion', Boolean(ui.reduceMotion));
        return () => document.body.classList.remove('mp-reduced-motion');
    }, [ui.reduceMotion]);

    // Close the drawer when the viewport grows past the fixed-sidebar breakpoint.
    useEffect(() => {
        const media = window.matchMedia('(min-width: 992px)');
        const onChange = () => {
            if (media.matches) setDrawerOpen(false);
        };
        media.addEventListener('change', onChange);
        return () => media.removeEventListener('change', onChange);
    }, []);

    return (
        <>
            <a className="skip-link" href="#main">Skip to content</a>

            <header className="pub-nav">
                <div className="container inner" style={{ maxWidth: 'none' }}>
                    <button
                        type="button"
                        className="btn btn-quiet btn-ico burger b-role"
                        aria-label="Open navigation"
                        aria-expanded={drawerOpen}
                        onClick={() => setDrawerOpen(true)}
                    >
                        ☰
                    </button>

                    <a className="brand" href="/">
                        <span className="logo" aria-hidden="true">M</span>
                        MediPath
                    </a>

                    <span className="badge badge-primary hide-sm" data-role={roleValue}>
                        {roleLabel}
                    </span>

                    {user?.verificationStatus === 'verified' && roleValue === 'doctor' ? (
                        <span className="badge badge-verified hide-sm">✓ Verified</span>
                    ) : null}

                    <div className="mp-row mla auth-cluster" style={{ gap: 'var(--mp-space-sm)' }}>
                        <ThemeToggle compact />
                        <NotificationBell
                            state={preview ? 'empty' : notificationState}
                            live={!preview}
                            notificationsHref={roleValue === 'doctor' ? '/doctor/notifications' : roleValue === 'pharmacist' ? '/pharmacist/notifications' : '/patient/notifications'}
                            items={preview ? [] : undefined}
                            unreadCount={preview ? 0 : undefined}
                        />
                        <span className="avatar avatar-sm avatar-initials hide-xs" aria-hidden="true">
                            {initials}
                        </span>
                        <span className="sr-only">Signed in as {name}</span>
                    </div>
                </div>
            </header>

            <div className="app-shell">
                <RoleSidebar
                    roleValue={roleValue}
                    roleLabel={roleLabel}
                    portal={portal}
                    items={nav.items}
                    activeHref={activeHref}
                    open={drawerOpen}
                    onClose={() => setDrawerOpen(false)}
                    unreadCount={preview ? 0 : user?.unreadNotifications ?? 0}
                    userName={name}
                    userInitials={initials}
                    showLogout={!preview}
                />

                <main id="main" className="main">
                    <div className="topbar">
                        <h1>{title}</h1>
                        {subtitle ? <span className="caption mla">{subtitle}</span> : null}
                        {topbarActions ? <div className="mp-row mla">{topbarActions}</div> : null}
                    </div>

                    {children}
                </main>
            </div>

            <FlashToasts />
            <ToastStack />
        </>
    );
}