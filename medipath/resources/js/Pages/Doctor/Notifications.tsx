/**
 * Doctor · Notifications (PLAN Sprint 7.4).
 * Server-owned feed; read state is owned by the shared notification API.
 */
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { DataState, PageMeta, SharedProps } from '@/types';
import type { PatientNotification } from '@/types/patient';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { Pager } from '@/Components/Data/DataTable';
import { cn, formatDate } from '@/lib/format';

interface NotificationsProps {
    notifications: PatientNotification[];
    meta: PageMeta;
    filter: 'all' | 'unread';
    counts: { all: number; unread: number };
    dataState: DataState;
    lastKnownAt: string;
}

export default function DoctorNotifications({ notifications, meta, filter, counts, dataState, lastKnownAt }: NotificationsProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(dataState);

    return (
        <RoleLayout
            role="doctor"
            title="Notifications"
            subtitle={`${counts.unread} unread of ${counts.all}`}
            activeHref="/doctor/notifications"
            topbarActions={<button type="button" className="btn btn-outline" onClick={() => router.post('/notifications/read-all', {}, { preserveScroll: true })}>Mark all read</button>}
        >
            <Head title="Notifications — MediPath" />

            <DataView
                name="Data view · Notifications"
                state={state}
                onStateChange={setState}
                showSwitch
                onRetry={() => router.reload()}
                lastKnownAt={lastKnownAt}
                emptyTitle="No notifications"
                emptyBody="New requests, cancellations and patient activity appear here."
                errorMessage="Notifications failed to load. Your schedule and records are unaffected."
            >
                <div className="tabs" role="tablist" aria-label="Notification filter">
                    <button type="button" role="tab" aria-selected={filter === 'all'} onClick={() => router.get('/doctor/notifications', {}, { preserveState: true, preserveScroll: true, replace: true })}>All <span className="cnt tabular">{counts.all}</span></button>
                    <button type="button" role="tab" aria-selected={filter === 'unread'} onClick={() => router.get('/doctor/notifications', { filter: 'unread' }, { preserveState: true, preserveScroll: true, replace: true })}>Unread <span className="cnt tabular">{counts.unread}</span></button>
                </div>

                <div className="stack mt-m">
                    {notifications.map((notification) => (
                        <article className={cn('card card-pad', !notification.readAt && 'is-unread')} key={notification.id}>
                            <div className="spread" style={{ flexWrap: 'wrap' }}>
                                <div>
                                    <strong>{notification.title}</strong>
                                    <span className="caption"> · {notification.category}</span>
                                    <p className="caption mt-s">{notification.message}</p>
                                </div>
                                <time className="caption tabular" dateTime={notification.createdAt ?? undefined}>
                                    {formatDate(notification.createdAt, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat, withTime: true })}
                                </time>
                            </div>
                            <div className="mp-row mt-m">
                                {notification.actionUrl ? <a className="btn btn-outline btn-sm" href={notification.actionUrl}>Open</a> : null}
                                {!notification.readAt ? <button type="button" className="btn btn-ghost btn-sm" onClick={() => router.post(`/notifications/${notification.id}/read`, {}, { preserveScroll: true })}>Mark read</button> : <span className="caption">Read</span>}
                            </div>
                        </article>
                    ))}
                </div>
                <div className="mt-m"><Pager page={meta} onPageChange={(page) => router.get('/doctor/notifications', { filter, page }, { preserveState: true, preserveScroll: true })} /></div>
            </DataView>
        </RoleLayout>
    );
}