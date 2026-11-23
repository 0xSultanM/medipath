/**
 * PublicNavbar — COMPONENT-SPEC §1.
 * Reproduces the guest shell in prototypes/guest/01-landing (brand | links |
 * auth cluster, emergency link scoped red, slide-over drawer <768, scrolled
 * shadow state). Links are props so later phases can add routes without
 * touching this component.
 */
import { router, usePage } from '@inertiajs/react';
import type { AuthUser, SharedProps } from '@/types';
import { cn } from '@/lib/format';
import { useDisclosure, useEscape, useFocusTrap, useScrolled } from '@/hooks/useUi';
import { PUBLIC_NAV } from '@/lib/nav';
import { ThemeToggle } from '@/Components/Navigation/ThemeToggle';

interface PublicNavbarProps {
    activeHref?: string;
    authed?: boolean;
    user?: AuthUser | null;
    links?: Array<{ label: string; href: string; emergency?: boolean }>;
}

export function PublicNavbar({ activeHref, authed, user, links = PUBLIC_NAV }: PublicNavbarProps) {
    const page = usePage<SharedProps>();
    const sharedUser = user ?? page.props.auth.user;
    const isAuthed = authed ?? Boolean(sharedUser);
    const isPending = Boolean(sharedUser && sharedUser.status !== 'approved');
    const csrfToken = page.props.csrfToken;
    const scrolled = useScrolled();
    const drawer = useDisclosure(false);
    const drawerRef = useFocusTrap(drawer.isOpen, drawer.close);

    useEscape(drawer.isOpen, drawer.close);

    return (
        <header className={cn('pub-nav', scrolled && 'is-scrolled')}>
            <div className="container inner">
                <a className="brand" href="/">
                    <span className="logo" aria-hidden="true">M</span>
                    MediPath
                </a>

                <nav className="nav-links" aria-label="Primary">
                    {links.map((link) => (
                        <a
                            key={link.href}
                            className={cn(
                                'nav-link',
                                link.emergency && 'is-emergency',
                                activeHref === link.href && !link.emergency && 'is-active',
                            )}
                            href={link.href}
                            aria-current={activeHref === link.href ? 'page' : undefined}
                        >
                            {link.emergency ? <span aria-hidden="true">🚨 </span> : null}
                            {link.label}
                        </a>
                    ))}
                </nav>

                <div className="mp-row mla auth-cluster" style={{ gap: 'var(--mp-space-sm)' }}>
                    <ThemeToggle compact />

                    {isPending ? (
                        <>
                            <span className="badge badge-warning hide-sm">{sharedUser?.statusLabel ?? 'Pending review'}</span>
                            <button className="btn btn-ghost hide-sm" type="button" onClick={() => router.post('/logout')}>Sign out</button>
                        </>
                    ) : isAuthed ? (
                        <>
                            <a className="btn btn-outline hide-sm" href={sharedUser?.dashboardUrl ?? '/patient/dashboard'}>
                                My dashboard
                            </a>
                            <span className="avatar avatar-sm avatar-initials hide-sm" aria-hidden="true">
                                {sharedUser?.initials}
                            </span>
                        </>
                    ) : (
                        <>
                            <a className="btn btn-ghost hide-sm" href="/login">Log in</a>
                            <a className="btn btn-primary hide-md" href="/register">Register</a>
                        </>
                    )}

                    <button
                        type="button"
                        className="btn btn-quiet btn-ico burger"
                        aria-label="Open menu"
                        aria-expanded={drawer.isOpen}
                        onClick={drawer.open}
                    >
                        ☰
                    </button>
                </div>
            </div>

            {drawer.isOpen ? (
                <>
                    <button className="backdrop is-open" aria-label="Close menu" onClick={drawer.close} />
                    <div
                        ref={drawerRef}
                        className="drawer is-open"
                        role="dialog"
                        aria-modal="true"
                        aria-label="Menu"
                    >
                        <div className="d-title">
                            <span className="h3">Menu</span>
                            <button type="button" className="x-close" data-autofocus onClick={drawer.close} aria-label="Close menu">
                                ✕
                            </button>
                        </div>

                        <nav aria-label="Mobile navigation">
                            {links.map((link) => (
                                <a
                                    key={link.href}
                                    className={cn('nav-link', link.emergency && 'is-emergency')}
                                    href={link.href}
                                    onClick={drawer.close}
                                >
                                    {link.label}
                                </a>
                            ))}
                        </nav>

                        <div className="divider" />

                        {isPending ? (
                            <>
                                <span className="badge badge-warning">{sharedUser?.statusLabel ?? 'Pending review'}</span>
                                <form method="post" action="/logout">
                                    <input type="hidden" name="_token" value={csrfToken} />
                                    <button type="submit" className="btn btn-quiet">Sign out</button>
                                </form>
                            </>
                        ) : isAuthed ? (
                            <>
                                <a className="btn btn-outline" href={sharedUser?.dashboardUrl ?? '/patient/dashboard'}>
                                    My dashboard
                                </a>
                                <form method="post" action="/logout">
                                    <input type="hidden" name="_token" value={csrfToken} />
                                    <button type="submit" className="btn btn-quiet">Sign out</button>
                                </form>
                            </>
                        ) : (
                            <>
                                <a className="btn btn-outline" href="/login">Log in</a>
                                <a className="btn btn-primary" href="/register">Register</a>
                            </>
                        )}
                    </div>
                </>
            ) : null}
        </header>
    );
}