/**
 * Admin · Profile & security — prototypes/admin/14-security.html.
 * Profile + idle window, password rotation and session revocation. Redis-backed
 * sessions are invalidated by the monotonic session_version bump; DB rows are
 * additionally removed where present.
 */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { DataState, SharedProps } from '@/types';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { DataTable, type DataColumn } from '@/Components/Data/DataTable';
import { formatDate } from '@/lib/format';

interface SessionRow {
    id: string;
    device: string;
    ip: string;
    lastActive: string;
    current: boolean;
}

interface SecurityProps {
    profile: { name: string; email: string; phone: string | null; roleLabel: string | null; lastLoginAt: string | null };
    security: { idleMinutes: number; sessionVersion: number; passwordChangedAt: string | null };
    sessions: SessionRow[];
    lastKnownAt: string;
}

export default function AdminSecurity({ profile, security, sessions, lastKnownAt }: SecurityProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>('success');
    const profileForm = useForm({ name: profile.name, email: profile.email, phone: profile.phone ?? '', idle_minutes: String(security.idleMinutes) });
    const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });

    useEffect(() => {
        profileForm.setData({ name: profile.name, email: profile.email, phone: profile.phone ?? '', idle_minutes: String(security.idleMinutes) });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [profile.name, profile.email, profile.phone, security.idleMinutes]);

    const sessionColumns: Array<DataColumn<SessionRow>> = [
        { key: 'device', label: 'Device', render: (row) => <div><strong>{row.device}</strong>{row.current ? <span className="badge badge-success" style={{ marginLeft: 8 }}><span className="dot" />you</span> : null}</div> },
        { key: 'ip', label: 'IP', render: (row) => <span className="mono">{row.ip}</span> },
        { key: 'active', label: 'Last active', render: (row) => <span className="tabular">{formatDate(row.lastActive, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat, withTime: true })}</span> },
        { key: 'id', label: 'Session', render: (row) => <span className="mono caption">{row.id}</span> },
    ];

    return (
        <RoleLayout
            role="admin"
            title="Admin profile & security"
            subtitle={`${profile.roleLabel} · session generation ${security.sessionVersion}`}
            activeHref="/admin/security"
        >
            <Head title="Admin profile & security — MediPath" />

            <div className="grid">
                <section className="c-12 md-c-6 card card-pad" aria-labelledby="profile-title">
                    <h2 className="h4" id="profile-title">Profile</h2>
                    <form className="stack-sm" onSubmit={(event) => { event.preventDefault(); profileForm.put('/admin/security/profile', { preserveScroll: true }); }}>
                        <div className="field"><label className="label req" htmlFor="sec-name">Name</label><input id="sec-name" className="input" value={profileForm.data.name} onChange={(event) => profileForm.setData('name', event.target.value)} />{profileForm.errors.name ? <span className="field-error">{profileForm.errors.name}</span> : null}</div>
                        <div className="field"><label className="label req" htmlFor="sec-email">Email</label><input id="sec-email" className="input" type="email" value={profileForm.data.email} onChange={(event) => profileForm.setData('email', event.target.value)} />{profileForm.errors.email ? <span className="field-error">{profileForm.errors.email}</span> : null}</div>
                        <div className="field"><label className="label" htmlFor="sec-phone">Phone</label><input id="sec-phone" className="input mono" value={profileForm.data.phone} onChange={(event) => profileForm.setData('phone', event.target.value)} /></div>
                        <div className="field"><label className="label req" htmlFor="sec-idle">Idle timeout (minutes)</label><input id="sec-idle" className="input" type="number" min={5} max={120} value={profileForm.data.idle_minutes} onChange={(event) => profileForm.setData('idle_minutes', event.target.value)} /><span className="hint">Server middleware logs admins out after inactivity; 5–120 minutes.</span></div>
                        <div className="mp-row"><button type="submit" className="btn btn-primary" disabled={profileForm.processing}>{profileForm.processing ? 'Saving…' : 'Update profile'}</button></div>
                    </form>
                </section>

                <section className="c-12 md-c-6 card card-pad" aria-labelledby="password-title">
                    <h2 className="h4" id="password-title">Change password</h2>
                    <p className="caption">Rotating the password increments the auth generation and revokes other sessions.</p>
                    <form className="stack-sm mt-s" onSubmit={(event) => { event.preventDefault(); passwordForm.put('/admin/security/password', { preserveScroll: true, onSuccess: () => passwordForm.reset() }); }}>
                        <div className="field"><label className="label req" htmlFor="sec-current">Current password</label><input id="sec-current" className="input" type="password" value={passwordForm.data.current_password} onChange={(event) => passwordForm.setData('current_password', event.target.value)} />{passwordForm.errors.current_password ? <span className="field-error">{passwordForm.errors.current_password}</span> : null}</div>
                        <div className="field"><label className="label req" htmlFor="sec-new">New password</label><input id="sec-new" className="input" type="password" value={passwordForm.data.password} onChange={(event) => passwordForm.setData('password', event.target.value)} />{passwordForm.errors.password ? <span className="field-error">{passwordForm.errors.password}</span> : null}</div>
                        <div className="field"><label className="label req" htmlFor="sec-new2">Confirm new password</label><input id="sec-new2" className="input" type="password" value={passwordForm.data.password_confirmation} onChange={(event) => passwordForm.setData('password_confirmation', event.target.value)} /></div>
                        <div className="mp-row"><button type="submit" className="btn btn-primary" disabled={passwordForm.processing}>{passwordForm.processing ? 'Updating…' : 'Update password'}</button><button type="button" className="btn btn-danger-outline" onClick={() => router.post('/admin/security/sessions/revoke-others', {}, { preserveScroll: true })}>Revoke all other sessions</button></div>
                    </form>
                    <p className="caption mt-m">Password changed: {security.passwordChangedAt ? formatDate(security.passwordChangedAt, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat, withTime: true }) : 'never'}.</p>
                </section>

                <section className="c-12">
                    <DataView
                        name="Data view · Active sessions"
                        state={state}
                        onStateChange={setState}
                        showSwitch
                        lastKnownAt={lastKnownAt}
                        emptyTitle="Only this session is active"
                        emptyBody="Redis session enumeration is intentionally disabled; revoking all others bumps the auth generation."
                        errorMessage="Sessions list failed to load; revocation remains server-side and safe."
                        skeleton={<div className="sk-table" aria-hidden="true"><div className="sk sk-row" /><div className="sk sk-row" /></div>}
                    >
                        <DataTable
                            className="tbl-cards-md"
                            caption="Active admin sessions"
                            columns={sessionColumns}
                            rows={sessions}
                            getRowKey={(row) => row.id}
                            state="success"
                        />
                        <p className="caption mt-m">The current session stays valid; every other session is invalidated on the next request. Redis-backed sessions are covered by the version marker even though they are not enumerable.</p>
                    </DataView>
                </section>
            </div>
        </RoleLayout>
    );
}