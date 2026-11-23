/**
 * RoleSidebar — COMPONENT-SPEC §2 (Role=Admin|Doctor|Pharmacy|Patient,
 * ItemState, Responsive=Fixed ≥992 | Drawer <992, data-bound counts).
 * One DOM instance: the same <aside> is sticky on desktop and translates as
 * the drawer on mobile (no duplicated landmarks/focus order).
 * Reproduces the shells in prototypes/admin/01, doctor/01, pharmacist/01,
 * patient/01.
 */
import { usePage } from '@inertiajs/react';
import type { DataState, SharedProps } from '@/types';
import { cn } from '@/lib/format';
import type { NavItem } from '@/lib/nav';
import { useFocusTrap } from '@/hooks/useUi';
import { ThemeToggle } from '@/Components/Navigation/ThemeToggle';

interface RoleSidebarProps {
    roleValue: string;
    roleLabel: string;
    portal: string;
    items: NavItem[];
    activeHref?: string;
    open: boolean;
    onClose: () => void;
    countState?: DataState;
    unreadCount?: number;
    userName?: string;
    userInitials?: string;
    showLogout?: boolean;
}

export function RoleSidebar({
    roleValue,
    roleLabel,
    portal,
    items,
    activeHref,
    open,
    onClose,
    countState = 'success',
    unreadCount,
    userName,
    userInitials,
    showLogout = true,
}: RoleSidebarProps) {
    const { auth, csrfToken } = usePage<SharedProps>().props;
    const user = auth.user;
    const displayName = userName ?? user?.name ?? 'MediPath user';
    const displayInitials = userInitials ?? user?.initials ?? 'MP';
    const trapRef = useFocusTrap(open, onClose);

    return (
        <>
            {open ? <button className="backdrop is-open" aria-label="Close navigation" onClick={onClose} /> : null}

            <aside
                ref={trapRef}
                className={cn('side-nav', open && 'is-open')}
                aria-label={`${roleLabel} workspace`}
            >
                <a className="s-brand" href="/">
                    <span
                        aria-hidden="true"
                        style={{
                            width: 26,
                            height: 26,
                            borderRadius: 7,
                            display: 'grid',
                            placeItems: 'center',
                            background: 'var(--mp-primary)',
                            color: 'var(--mp-on-primary)',
                            fontWeight: 800,
                            fontSize: 13,
                        }}
                    >
                        M
                    </span>
                    MediPath
                </a>

                <div className="mp-row" style={{ padding: '0 var(--mp-space-sm) var(--mp-space-sm)' }}>
                    <span className="badge badge-primary" data-role={roleValue}>{roleLabel}</span>
                    <button type="button" className="s-close" data-autofocus onClick={onClose} aria-label="Close navigation">
                        ✕
                    </button>
                </div>

                <span className="s-role">{portal}</span>

                <nav aria-label="Sidebar">
                    {items.map((item) => {
                        const isActive = item.exact ? activeHref === item.href : Boolean(activeHref?.startsWith(item.href));
                        const badgeValue = item.href.includes('notifications') ? unreadCount : item.count;
                        const showCount = typeof badgeValue === 'number' && badgeValue > 0;

                        return (
                            <a
                                key={item.href}
                                className={cn('s-link', isActive && 'is-active')}
                                href={item.href}
                                aria-current={isActive ? 'page' : undefined}
                                onClick={onClose}
                            >
                                <span className="ico" aria-hidden="true">{item.icon}</span>
                                {item.label}

                                {countState === 'skeleton' ? (
                                    <span className="cnt" style={{ width: 24, height: 14, opacity: 0.5 }} aria-hidden="true" />
                                ) : countState === 'success' && showCount ? (
                                    <span className="cnt tabular" aria-label={`${badgeValue} unread`}>
                                        {badgeValue}
                                    </span>
                                ) : null}
                            </a>
                        );
                    })}
                </nav>

                {countState === 'error' ? (
                    <p className="caption" style={{ paddingInline: 'var(--mp-space-sm)' }} aria-live="polite">
                        Counts unavailable
                    </p>
                ) : null}

                <div className="s-footer">
                    <span className="s-role">Signed in as</span>
                    <a className="s-link" href={roleValue === 'admin' ? '/admin/security' : `/${roleValue}/dashboard`}>
                        <span className="avatar avatar-sm avatar-initials" aria-hidden="true" style={{ width: 26, height: 26, fontSize: 10 }}>
                            {displayInitials}
                        </span>
                        {displayName}
                    </a>
                    <div className="s-link" style={{ cursor: 'default' }}>
                        <ThemeToggle compact />
                        <span>Theme</span>
                    </div>
                    {showLogout ? (
                        <form method="post" action="/logout">
                            <input type="hidden" name="_token" value={csrfToken} />
                            <button type="submit" className="s-link" style={{ width: '100%', border: 0, background: 'transparent', cursor: 'pointer' }}>
                                <span className="ico" aria-hidden="true">↩</span>
                                Sign out
                            </button>
                        </form>
                    ) : null}
                </div>
            </aside>
        </>
    );
}