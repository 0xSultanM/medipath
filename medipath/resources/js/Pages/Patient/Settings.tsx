/**
 * Patient · Profile & settings — prototypes/patient/08-profile-settings.html.
 * Immutable patient identifiers, personal chart details, password rotation with
 * session revocation, and preferences. Currency remains clinic-wide.
 */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { formatDate } from '@/lib/format';

interface SettingsProps {
    profile: {
        name: string;
        email: string;
        phone: string | null;
        age: number | null;
        gender: string | null;
        bloodGroup: string | null;
        condition: string | null;
        address: string | null;
        patientCode: string;
        walkInCode: string | null;
        emailVerified: boolean;
        lastLoginAt: string | null;
    };
    preferences: {
        theme: 'system' | 'light' | 'dark';
        language: string;
        timezone: string;
        dateFormat: string;
        notificationPrefs: { inapp?: boolean; email?: boolean; sms?: boolean };
        reduceMotion: boolean;
    };
    options: { genders: string[]; locales: string[]; timezones: string[]; dateFormats: string[] };
    security: { sessionVersion: number; passwordChangedAt: string | null };
}

export default function PatientSettings({ profile, preferences, options, security }: SettingsProps) {
    const { settings } = usePage<SharedProps>().props;
    const profileForm = useForm({
        name: profile.name,
        email: profile.email,
        phone: profile.phone ?? '',
        age: profile.age ? String(profile.age) : '',
        gender: profile.gender ?? '',
        blood_group: profile.bloodGroup ?? '',
        condition: profile.condition ?? '',
        address: profile.address ?? '',
    });
    const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });
    const prefForm = useForm({
        theme: preferences.theme,
        language: preferences.language,
        timezone: preferences.timezone,
        date_format: preferences.dateFormat,
        inapp: Boolean(preferences.notificationPrefs.inapp),
        email: Boolean(preferences.notificationPrefs.email),
        sms: Boolean(preferences.notificationPrefs.sms),
        reduce_motion: preferences.reduceMotion,
    });

    return (
        <RoleLayout role="patient" title="Profile & settings" subtitle={`Patient ${profile.patientCode} · immutable identifiers`} activeHref="/patient/settings">
            <Head title="Profile & settings — MediPath" />

            <div className="grid">
                <section className="c-12 sm-c-6 card card-pad">
                    <h2 className="h4">Immutable identifiers</h2>
                    <dl className="mt-s">
                        <div className="def-row"><dt>Patient ID</dt><dd className="mono">{profile.patientCode} — records key on it</dd></div>
                        {profile.walkInCode ? <div className="def-row"><dt>Walk-in claim code</dt><dd className="mono">{profile.walkInCode}</dd></div> : null}
                    </dl>
                    <p className="caption">Bring this code when visiting without an appointment; staff link the visit to you.</p>
                </section>
                <section className="c-12 sm-c-6 card card-pad">
                    <h2 className="h4">Account</h2>
                    <dl className="mt-s">
                        <div className="def-row"><dt>Email verified</dt><dd><StatusBadge family="account" status={profile.emailVerified ? 'approved' : 'pending'} label={profile.emailVerified ? 'Verified' : 'Pending'} /></dd></div>
                        <div className="def-row"><dt>Last sign-in</dt><dd className="tabular">{profile.lastLoginAt ? formatDate(profile.lastLoginAt, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat, withTime: true }) : '—'}</dd></div>
                        <div className="def-row"><dt>Session generation</dt><dd className="tabular">{security.sessionVersion}</dd></div>
                    </dl>
                    <button type="button" className="btn btn-danger-outline mt-m" onClick={() => router.post('/patient/settings/sessions/revoke-others', {}, { preserveScroll: true })}>Sign out all other sessions</button>
                </section>
            </div>

            <section className="card card-pad mt-l">
                <h2 className="h4">Personal details</h2>
                <form className="stack-sm mt-s" onSubmit={(event) => { event.preventDefault(); profileForm.put('/patient/settings/profile', { preserveScroll: true }); }}>
                    <div className="grid tight">
                        <div className="c-12 sm-c-6 field"><label className="label req" htmlFor="pat-name">Full name</label><input id="pat-name" className="input" value={profileForm.data.name} onChange={(event) => profileForm.setData('name', event.target.value)} />{profileForm.errors.name ? <span className="field-error">{profileForm.errors.name}</span> : null}</div>
                        <div className="c-12 sm-c-6 field"><label className="label req" htmlFor="pat-email">Email</label><input id="pat-email" className="input" type="email" value={profileForm.data.email} onChange={(event) => profileForm.setData('email', event.target.value)} />{profileForm.errors.email ? <span className="field-error">{profileForm.errors.email}</span> : null}</div>
                        <div className="c-12 sm-c-6 field"><label className="label" htmlFor="pat-phone">Phone</label><input id="pat-phone" className="input" type="tel" value={profileForm.data.phone} onChange={(event) => profileForm.setData('phone', event.target.value)} /></div>
                        <div className="c-12 sm-c-2 field"><label className="label" htmlFor="pat-age">Age</label><input id="pat-age" className="input" type="number" min={0} max={120} value={profileForm.data.age} onChange={(event) => profileForm.setData('age', event.target.value)} /></div>
                        <div className="c-12 sm-c-2 field"><label className="label" htmlFor="pat-gender">Gender</label><select id="pat-gender" className="select" value={profileForm.data.gender} onChange={(event) => profileForm.setData('gender', event.target.value)}><option value="">Not recorded</option>{options.genders.map((gender) => <option key={gender} value={gender}>{gender}</option>)}</select></div>
                        <div className="c-12 sm-c-2 field"><label className="label" htmlFor="pat-blood">Blood group</label><input id="pat-blood" className="input" maxLength={8} value={profileForm.data.blood_group} onChange={(event) => profileForm.setData('blood_group', event.target.value)} /></div>
                        <div className="c-12 field"><label className="label" htmlFor="pat-condition">Primary condition</label><input id="pat-condition" className="input" value={profileForm.data.condition} onChange={(event) => profileForm.setData('condition', event.target.value)} /></div>
                        <div className="c-12 field"><label className="label" htmlFor="pat-address">Address</label><input id="pat-address" className="input" value={profileForm.data.address} onChange={(event) => profileForm.setData('address', event.target.value)} /></div>
                    </div>
                    <div className="mp-row"><button type="submit" className="btn btn-primary" disabled={profileForm.processing}>{profileForm.processing ? 'Saving…' : 'Save changes'}</button><button type="button" className="btn btn-ghost" onClick={() => profileForm.reset()}>Discard</button></div>
                </form>
            </section>

            <section className="card card-pad mt-l">
                <h2 className="h4">Change password</h2>
                <form className="stack-sm mt-s" onSubmit={(event) => { event.preventDefault(); passwordForm.put('/patient/settings/password', { preserveScroll: true, onSuccess: () => passwordForm.reset() }); }}>
                    <div className="grid tight">
                        <div className="c-12 sm-c-4 field"><label className="label req" htmlFor="pat-current">Current password</label><input id="pat-current" className="input" type="password" value={passwordForm.data.current_password} onChange={(event) => passwordForm.setData('current_password', event.target.value)} />{passwordForm.errors.current_password ? <span className="field-error">{passwordForm.errors.current_password}</span> : null}</div>
                        <div className="c-12 sm-c-4 field"><label className="label req" htmlFor="pat-new">New password</label><input id="pat-new" className="input" type="password" value={passwordForm.data.password} onChange={(event) => passwordForm.setData('password', event.target.value)} />{passwordForm.errors.password ? <span className="field-error">{passwordForm.errors.password}</span> : null}</div>
                        <div className="c-12 sm-c-4 field"><label className="label req" htmlFor="pat-confirm">Confirm new</label><input id="pat-confirm" className="input" type="password" value={passwordForm.data.password_confirmation} onChange={(event) => passwordForm.setData('password_confirmation', event.target.value)} /></div>
                    </div>
                    <p className="caption">On success every other session is revoked and the change is recorded in the activity log.</p>
                    <div><button type="submit" className="btn btn-primary" disabled={passwordForm.processing}>{passwordForm.processing ? 'Updating…' : 'Update password'}</button></div>
                </form>
            </section>

            <section className="card card-pad mt-l">
                <h2 className="h4">Preferences</h2>
                <form className="stack-sm mt-s" onSubmit={(event) => { event.preventDefault(); prefForm.put('/patient/settings/preferences', { preserveScroll: true }); }}>
                    <div className="grid">
                        <div className="c-12 md-c-6 stack-sm">
                            <span className="label">Notifications</span>
                            <label className="check"><input type="checkbox" checked={prefForm.data.inapp} onChange={(event) => prefForm.setData('inapp', event.target.checked)} /><span>In-app</span></label>
                            <label className="check"><input type="checkbox" checked={prefForm.data.email} onChange={(event) => prefForm.setData('email', event.target.checked)} /><span>Email reminders and prescription status</span></label>
                            <label className="check"><input type="checkbox" checked={prefForm.data.sms} onChange={(event) => prefForm.setData('sms', event.target.checked)} /><span>SMS for appointments and billing only</span></label>
                            <label className="check"><input type="checkbox" checked={prefForm.data.reduce_motion} onChange={(event) => prefForm.setData('reduce_motion', event.target.checked)} /><span>Reduce motion</span></label>
                        </div>
                        <div className="c-12 md-c-6 stack-sm">
                            <span className="label">Appearance &amp; locale</span>
                            <div className="field"><label className="label" htmlFor="pref-theme">Theme</label><select id="pref-theme" className="select" value={prefForm.data.theme} onChange={(event) => prefForm.setData('theme', event.target.value as 'system' | 'light' | 'dark')}><option value="system">System</option><option value="light">Light</option><option value="dark">Dark</option></select></div>
                            <div className="field"><label className="label" htmlFor="pref-lang">Language</label><select id="pref-lang" className="select" value={prefForm.data.language} onChange={(event) => prefForm.setData('language', event.target.value)}>{options.locales.map((locale) => <option key={locale} value={locale}>{locale === 'bn' ? 'বাংলা (Bangla)' : 'English'}</option>)}</select></div>
                            <div className="field"><label className="label" htmlFor="pref-tz">Timezone</label><select id="pref-tz" className="select" value={prefForm.data.timezone} onChange={(event) => prefForm.setData('timezone', event.target.value)}>{options.timezones.map((tz) => <option key={tz} value={tz}>{tz}</option>)}</select></div>
                            <div className="field"><label className="label" htmlFor="pref-date">Date format</label><select id="pref-date" className="select" value={prefForm.data.date_format} onChange={(event) => prefForm.setData('date_format', event.target.value)}>{options.dateFormats.map((format) => <option key={format} value={format}>{format}</option>)}</select></div>
                        </div>
                    </div>
                    <div className="mp-row"><button type="submit" className="btn btn-primary" disabled={prefForm.processing}>{prefForm.processing ? 'Saving…' : 'Save preferences'}</button></div>
                </form>
                <p className="caption mt-m">Currency is clinic-wide ({settings.currencyCode}); patients never set a symbol. Password changed: {security.passwordChangedAt ? formatDate(security.passwordChangedAt, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat, withTime: true }) : 'never'}.</p>
            </section>
        </RoleLayout>
    );
}