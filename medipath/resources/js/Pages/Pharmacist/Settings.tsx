/** Pharmacist · Profile & settings — PLAN Sprint 8.1. */
import { Head, useForm, usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { formatDate } from '@/lib/format';

interface SettingsProps {
    profile: { name: string; email: string; phone: string | null; pharmacistCode: string; licenseNumber: string; licenseAuthority: string; pharmacyName: string | null; approvedAt: string | null };
    preferences: { theme: 'system' | 'light' | 'dark'; language: string; timezone: string; dateFormat: string; reduceMotion: boolean };
    options: { locales: string[]; timezones: string[]; dateFormats: string[] };
    security: { sessionVersion: number };
}

export default function PharmacistSettings({ profile, preferences, options, security }: SettingsProps) {
    const { settings } = usePage<SharedProps>().props;
    const profileForm = useForm({
        name: profile.name,
        email: profile.email,
        phone: profile.phone ?? '',
        pharmacy_name: profile.pharmacyName ?? '',
    });
    const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });
    const prefForm = useForm({
        theme: preferences.theme,
        language: preferences.language,
        timezone: preferences.timezone,
        date_format: preferences.dateFormat,
        reduce_motion: preferences.reduceMotion,
    });

    return (
        <RoleLayout role="pharmacist" title="Profile & settings" subtitle={`${profile.pharmacistCode} · session generation ${security.sessionVersion}`} activeHref="/pharmacist/settings">
            <Head title="Pharmacist settings — MediPath" />

            <div className="grid">
                <section className="c-12 md-c-6 card card-pad">
                    <h2 className="h4">Approved credentials</h2>
                    <dl className="mt-s">
                        <div className="def-row"><dt>Status</dt><dd><StatusBadge family="verification" status="verified" label="Approved pharmacist" /></dd></div>
                        <div className="def-row"><dt>License</dt><dd className="mono">{profile.licenseNumber}</dd></div>
                        <div className="def-row"><dt>Authority</dt><dd>{profile.licenseAuthority}</dd></div>
                        <div className="def-row"><dt>Approved</dt><dd>{formatDate(profile.approvedAt, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })}</dd></div>
                    </dl>
                    <p className="caption">Credentials are admin-verified; license changes go through the approval workflow.</p>
                </section>

                <section className="c-12 md-c-6 card card-pad">
                    <h2 className="h4">Profile</h2>
                    <form className="stack-sm mt-s" onSubmit={(event) => { event.preventDefault(); profileForm.put('/pharmacist/settings/profile', { preserveScroll: true }); }}>
                        <div className="field"><label className="label req" htmlFor="ph-name">Name</label><input id="ph-name" className="input" value={profileForm.data.name} onChange={(event) => profileForm.setData('name', event.target.value)} />{profileForm.errors.name ? <span className="field-error">{profileForm.errors.name}</span> : null}</div>
                        <div className="field"><label className="label req" htmlFor="ph-email">Email</label><input id="ph-email" className="input" type="email" value={profileForm.data.email} onChange={(event) => profileForm.setData('email', event.target.value)} />{profileForm.errors.email ? <span className="field-error">{profileForm.errors.email}</span> : null}</div>
                        <div className="field"><label className="label" htmlFor="ph-phone">Phone</label><input id="ph-phone" className="input mono" value={profileForm.data.phone} onChange={(event) => profileForm.setData('phone', event.target.value)} /></div>
                        <div className="field"><label className="label" htmlFor="ph-pharmacy">Pharmacy name</label><input id="ph-pharmacy" className="input" value={profileForm.data.pharmacy_name} onChange={(event) => profileForm.setData('pharmacy_name', event.target.value)} /></div>
                        <button type="submit" className="btn btn-primary" disabled={profileForm.processing}>{profileForm.processing ? 'Saving…' : 'Save profile'}</button>
                    </form>
                </section>

                <section className="c-12 md-c-6 card card-pad">
                    <h2 className="h4">Change password</h2>
                    <form className="stack-sm mt-s" onSubmit={(event) => { event.preventDefault(); passwordForm.put('/pharmacist/settings/password', { preserveScroll: true, onSuccess: () => passwordForm.reset() }); }}>
                        <div className="field"><label className="label req" htmlFor="ph-current">Current password</label><input id="ph-current" className="input" type="password" value={passwordForm.data.current_password} onChange={(event) => passwordForm.setData('current_password', event.target.value)} />{passwordForm.errors.current_password ? <span className="field-error">{passwordForm.errors.current_password}</span> : null}</div>
                        <div className="field"><label className="label req" htmlFor="ph-new">New password</label><input id="ph-new" className="input" type="password" value={passwordForm.data.password} onChange={(event) => passwordForm.setData('password', event.target.value)} />{passwordForm.errors.password ? <span className="field-error">{passwordForm.errors.password}</span> : null}</div>
                        <div className="field"><label className="label req" htmlFor="ph-confirm">Confirm new password</label><input id="ph-confirm" className="input" type="password" value={passwordForm.data.password_confirmation} onChange={(event) => passwordForm.setData('password_confirmation', event.target.value)} /></div>
                        <button type="submit" className="btn btn-primary" disabled={passwordForm.processing}>{passwordForm.processing ? 'Updating…' : 'Update password'}</button>
                    </form>
                </section>

                <section className="c-12 md-c-6 card card-pad">
                    <h2 className="h4">Preferences</h2>
                    <form className="stack-sm mt-s" onSubmit={(event) => { event.preventDefault(); prefForm.put('/pharmacist/settings/preferences', { preserveScroll: true }); }}>
                        <div className="field"><label className="label" htmlFor="ph-theme">Theme</label><select id="ph-theme" className="select" value={prefForm.data.theme} onChange={(event) => prefForm.setData('theme', event.target.value as 'system' | 'light' | 'dark')}><option value="system">System</option><option value="light">Light</option><option value="dark">Dark</option></select></div>
                        <div className="field"><label className="label" htmlFor="ph-lang">Language</label><select id="ph-lang" className="select" value={prefForm.data.language} onChange={(event) => prefForm.setData('language', event.target.value)}>{options.locales.map((locale) => <option key={locale} value={locale}>{locale}</option>)}</select></div>
                        <div className="field"><label className="label" htmlFor="ph-tz">Timezone</label><select id="ph-tz" className="select" value={prefForm.data.timezone} onChange={(event) => prefForm.setData('timezone', event.target.value)}>{options.timezones.map((tz) => <option key={tz} value={tz}>{tz}</option>)}</select></div>
                        <div className="field"><label className="label" htmlFor="ph-date">Date format</label><select id="ph-date" className="select" value={prefForm.data.date_format} onChange={(event) => prefForm.setData('date_format', event.target.value)}>{options.dateFormats.map((format) => <option key={format} value={format}>{format}</option>)}</select></div>
                        <label className="check"><input type="checkbox" checked={prefForm.data.reduce_motion} onChange={(event) => prefForm.setData('reduce_motion', event.target.checked)} /><span>Reduce motion</span></label>
                        <button type="submit" className="btn btn-primary" disabled={prefForm.processing}>{prefForm.processing ? 'Saving…' : 'Save preferences'}</button>
                    </form>
                    <p className="caption mt-m">Currency is clinic-wide ({settings.currencyCode}); pharmacists never set money symbols.</p>
                </section>
            </div>
        </RoleLayout>
    );
}