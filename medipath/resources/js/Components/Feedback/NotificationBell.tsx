/**
 * NotificationBell + Panel — COMPONENT-SPEC §10.
 * Bell with count (9+ cap), popover ≥768 / right slide-over drawer <768,
 * aria-live polite list, skeleton/empty/error+Retry/success, mark-read actions.
 */
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { router, usePage } from '@inertiajs/react';
import type { DataState, NotificationItem, SharedProps } from '@/types';
import { cn, relativeTime } from '@/lib/format';
import { useDisclosure, useEscape, useFocusTrap, useMediaQuery } from '@/hooks/useUi';
import { getEcho } from '@/lib/echo';
import { EmptyState } from '@/Components/Feedback/EmptyState';
import { RetryBanner } from '@/Components/Feedback/RetryBanner';

interface NotificationBellProps {
    items?: NotificationItem[];
    unreadCount?: number;
    state?: DataState;
    onRetry?: () => void;
    onMarkAllRead?: () => void;
    onItemRead?: (id: number) => void;
    notificationsHref?: string;
    label?: string;
    /** Enable the Reverb subscription (product shells). Mock galleries pass false. */
    live?: boolean;
}

const CATEGORY_ICON: Record<string, string> = {
    appointment: '📅',
    prescription: '💊',
    billing: '🧾',
    account: '👤',
    safety: '⚠️',
    system: '🔔',
};

export function NotificationBell({
    items,
    unreadCount,
    state = 'success',
    onRetry,
    onMarkAllRead,
    onItemRead,
    notificationsHref = '/patient/notifications',
    label = 'Notifications',
    live = true,
}: NotificationBellProps) {
    const page = usePage<SharedProps>();
    const serverFeed = items ?? page.props.notificationsFeed ?? [];
    const serverUnread = unreadCount ?? page.props.auth.user?.unreadNotifications ?? 0;
    const isDesktop = useMediaQuery('(min-width: 768px)', true);
    const panel = useDisclosure(false);
    const drawerRef = useFocusTrap(!isDesktop && panel.isOpen, panel.close);
    const popoverRef = useRef<HTMLDivElement | null>(null);
    const [liveFeed, setLiveFeed] = useState<NotificationItem[]>(serverFeed);
    const [liveUnread, setLiveUnread] = useState(serverUnread);

    useEffect(() => {
        setLiveFeed(serverFeed);
        setLiveUnread(serverUnread);
    }, [serverFeed, serverUnread]);

    // Reverb subscription: prepend new notifications and increment the badge.
    useEffect(() => {
        const userId = page.props.auth.user?.id;
        if (!userId || !live) return;

        const echo = getEcho();
        if (!echo) return;

        const channelName = `App.Models.User.${userId}`;
        const channel = echo.private(channelName);

        channel.listen('.notification.created', (payload: NotificationItem) => {
            setLiveFeed((current) => [payload, ...current].slice(0, 8));
            setLiveUnread((current) => current + 1);
        });

        return () => {
            channel.stopListening('.notification.created');
            echo.leave(channelName);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [page.props.auth.user?.id, live]);

    const feed = items ?? liveFeed;
    const unread = unreadCount ?? liveUnread;

    useEscape(panel.isOpen, panel.close);

    useEffect(() => {
        if (!isDesktop || !panel.isOpen) return;
        const onClick = (event: MouseEvent) => {
            if (!popoverRef.current?.contains(event.target as Node)) panel.close();
        };
        document.addEventListener('mousedown', onClick);
        return () => document.removeEventListener('mousedown', onClick);
    }, [isDesktop, panel.isOpen, panel.close]);

    const markAll = () => {
        if (onMarkAllRead) {
            onMarkAllRead();
            return;
        }
        router.post('/notifications/read-all', {}, { preserveScroll: true, preserveState: true });
    };

    const markOne = (id: number) => {
        if (onItemRead) {
            onItemRead(id);
            return;
        }
        router.post(`/notifications/${id}/read`, {}, { preserveScroll: true, preserveState: true });
    };

    const panelBody = (
        <>
            <div className="notif-panel-head">
                <span className="h4" style={{ margin: 0 }}>{label}</span>
                <button type="button" className="btn btn-ghost btn-sm" onClick={markAll} disabled={unread === 0}>
                    Mark all read
                </button>
            </div>

            <div className="notif-list" aria-live="polite" aria-relevant="additions text">
                {state === 'skeleton' ? (
                    <div style={{ padding: 'var(--mp-space-base)' }} aria-busy="true">
                        {[0, 1, 2].map((row) => (
                            <div className="mp-row" key={row} style={{ marginBottom: 'var(--mp-space-md)' }}>
                                <span className="sk sk-circle" style={{ width: 32, height: 32 }} aria-hidden="true" />
                                <div style={{ flex: 1 }}>
                                    <span className="sk sk-line" style={{ width: '70%' }} aria-hidden="true" />
                                    <span className="sk sk-line" style={{ width: '45%' }} aria-hidden="true" />
                                </div>
                            </div>
                        ))}
                    </div>
                ) : state === 'error' ? (
                    <div style={{ padding: 'var(--mp-space-base)' }}>
                        <RetryBanner message="Couldn’t load notifications." onRetry={onRetry ?? (() => undefined)} />
                    </div>
                ) : state === 'empty' || feed.length === 0 ? (
                    <EmptyState
                        glyph="✓"
                        title="You’re all caught up"
                        body="New appointment, prescription and billing updates will appear here."
                    />
                ) : (
                    feed.map((item) => {
                        const read = Boolean(item.readAt ?? item.read_at);
                        const href = item.actionUrl ?? item.action_url ?? undefined;
                        const created = item.createdAt ?? item.created_at ?? null;

                        return (
                            <div className={cn('notif-item', !read && 'is-unread')} key={item.id}>
                                <span className="n-cat" aria-hidden="true">
                                    {CATEGORY_ICON[item.category] ?? '🔔'}
                                </span>
                                <div className="n-body">
                                    <div className="n-title">
                                        {href ? <a href={href}>{item.title}</a> : item.title}
                                    </div>
                                    <div className="n-text">{item.message}</div>
                                    {created ? (
                                        <time className="n-time" dateTime={created}>{relativeTime(created)}</time>
                                    ) : null}
                                </div>
                                {!read ? (
                                    <button
                                        type="button"
                                        className="btn btn-ghost btn-sm"
                                        onClick={() => markOne(item.id)}
                                        aria-label={`Mark "${item.title}" as read`}
                                    >
                                        Mark read
                                    </button>
                                ) : null}
                            </div>
                        );
                    })
                )}
            </div>

            <div className="notif-panel-foot">
                <a className="btn btn-outline btn-block btn-sm" href={notificationsHref}>
                    View all notifications
                </a>
            </div>
        </>
    );

    return (
        <div className="notif-fab">
            <button
                type="button"
                className="bell"
                aria-label={`${label}${unread > 0 ? `, ${unread} unread` : ''}`}
                aria-expanded={panel.isOpen}
                onClick={panel.toggle}
            >
                <span aria-hidden="true">🔔</span>
                {unread > 0 ? (
                    <span className="b-dot tabular" aria-hidden="true">
                        {unread > 9 ? '9+' : unread}
                    </span>
                ) : null}
            </button>

            {isDesktop && panel.isOpen ? (
                <div className="notif-panel" role="dialog" aria-label={label} ref={popoverRef}>
                    {panelBody}
                </div>
            ) : null}

            {!isDesktop && panel.isOpen ? (
                <>
                    <button className="backdrop is-open" aria-label="Close notifications" onClick={panel.close} />
                    <div
                        ref={drawerRef}
                        className="drawer drawer--right drawer--notif is-open"
                        role="dialog"
                        aria-modal="true"
                        aria-label={label}
                    >
                        <div className="notif-drawer-body" style={{ display: 'flex', flexDirection: 'column', height: '100%', gap: 0 }}>
                            {panelBody}
                        </div>
                    </div>
                </>
            ) : null}
        </div>
    );
}

export function roleBadge(roleLabel: string): ReactNode {
    return <span className="badge badge-primary">{roleLabel}</span>;
}