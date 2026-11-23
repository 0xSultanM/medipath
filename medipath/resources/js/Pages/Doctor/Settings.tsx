/**
 * Doctor · Profile & settings — PLAN Sprint 6.1.
 */
import { Head, useForm, usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { StatusBadge } from '@/Components/Data/StatusBadge';

interface SettingsProps {
    profile: { name: string; email: string; phone: string | null; doctorCode: string; specialty: string | null; licenseNumber: string; licenseAuthority: string; experienceYears: number; fee: string; followUpFee: string; bio: string | null; clinicName: string | null; clinicAddress: string | null; room: string | null; verified: boolean };
    preferences: { theme: 'system' | 'light' | 'dark'; language: string; timezone: string; dateFormat: string; reduceMotion: boolean };
    options: { locales: string[]; timezones: string[]; dateFormats: string[] };
    security: { sessionVersion: number };
}

export default function DoctorSettings({ profile, preferences, options, security }: SettingsProps) {
    const { settings } = usePage<SharedProps>().props;
    const profileForm = useForm({
        name: profile.name, email: profile.email, phone: profile.phone ?? '', fee: profile.fee, follow_up_fee: profile.followUpFee,
        bio: profile.bio ?? '', clinic_name: profile.clinicName ?? '', clinic_address: profile.clinicAddress ?? '', room: profile.room ?? '',
    });
    const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });
    const prefForm = useForm({
        theme: preferences.theme, language: preferences.language, timezone: preferences.timezone,
        date_format: preferences.dateFormat, reduce_motion: preferences.reduceMotion,
    });

    return (
        <RoleLayout role="doctor" title="Profile & settings" subtitle={`${profile.doctorCode} · session generation ${security.sessionVersion}`} activeHref="/doctor/settings">
            <Head title="Doctor settings — MediPath" />

            <div className="grid">
                <section className="c-12 md-c-6 card card-pad">
                    <h2 className="h4">Verified credentials</h2>
                    <dl className="mt-s">
                        <div className="def-row"><dt>Status</dt><dd><StatusBadge family="verification" status={profile.verified ? 'verified' : 'pending'} label={profile.verified ? 'Verified' : 'Under review'} /></dd></div>
                        <div className="def-row"><dt>Specialty</dt><dd>{profile.specialty}</dd></div>
                        <div className="def-row"><dt>License</dt><dd className="mono">{profile.licenseNumber}</dd></div>
                        <div className="def-row"><dt>Authority</dt><dd>{profile.licenseAuthority}</dd></div>
                        <div className="def-row"><dt>Experience</dt><dd>{profile.experienceYears} years</dd></div>
                    </dl>
                    <p className="caption">Credentials are admin-verified and can only be changed through the verification workflow.</p>
                </section>

                <section className="c-12 md-c-6 card card-pad">
                    <h2 className="h4">Profile</h2>
                    <form className="stack-sm mt-s" onSubmit={(event) => { event.preventDefault(); profileForm.put('/doctor/settings/profile', { preserveScroll: true }); }}>
                        <div className="field"><label className="label req" htmlFor="doc-name">Name</label><input id="doc-name" className="input" value={profileForm.data.name} onChange={(event) => profileForm.setData('name', event.target.value)} /></div>
                        <div className="field"><label className="label req" htmlFor="doc-email">Email</label><input id="doc-email" className="input" type="email" value={profileForm.data.email} onChange={(event) => profileForm.setData('email', event.target.value)} /></div>
                        <div className="field"><label className="label" htmlFor="doc-phone">Phone</label><input id="doc-phone" className="input mono" value={profileForm.data.phone} onChange={(event) => profileForm.setData('phone', event.target.value)} /></div>
                        <div className="grid tight">
                            <div className="c-6 field"><label className="label" htmlFor="doc-fee">Consultation fee</label><input id="doc-fee" className="input" type="number" min={0} step="0.01" value={profileForm.data.fee} onChange={(event) => profileForm.setData('fee', event.target.value)} /></div>
                            <div className="c-6 field"><label className="label" htmlFor="doc-follow">Follow-up fee</label><input id="doc-follow" className="input" type="number" min={0} step="0.01" value={profileForm.data.follow_up_fee} onChange={(event) => profileForm.setData('follow_up_fee', event.target.value)} /></div>
                            <div className="c-12 field"><label className="label" htmlFor="doc-clinic">Clinic name</label><input id="doc-clinic" className="input" value={profileForm.data.clinic_name} onChange={(event) => profileForm.setData('clinic_name', event.target.value)} /></div>
                            <div className="c-8 field"><label className="label" htmlFor="doc-address">Clinic address</label><input id="doc-address" className="input" value={profileForm.data.clinic_address} onChange={(event) => profileForm.setData('clinic_address', event.target.value)} /></div>
                            <div className="c-4 field"><label className="label" htmlFor="doc-room">Room</label><input id="doc-room" className="input" value={profileForm.data.room} onChange={(event) => profileForm.setData('room', event.target.value)} /></div>
                        </div>
                        <div className="field"><label className="label" htmlFor="doc-bio">Bio</label><textarea id="doc-bio" className="textarea" rows={3} value={profileForm.data.bio} onChange={(event) => profileForm.setData('bio', event.target.value)} /></div>
                        <button type="submit" className="btn btn-primary" disabled={profileForm.processing}>{profileForm.processing ? 'Saving…' : 'Save profile'}</button>
                    </form>
                </section>

                <section className="c-12 md-c-6 card card-pad">
                    <h2 className="h4">Change password</h2>
                    <form className="stack-sm mt-s" onSubmit={(event) => { event.preventDefault(); passwordForm.put('/doctor/settings/password', { preserveScroll: true, onSuccess: () => passwordForm.reset() }); }}>
                        <div className="field"><label className="label req" htmlFor="doc-current">Current password</label><input id="doc-current" className="input" type="password" value={passwordForm.data.current_password} onChange={(event) => passwordForm.setData('current_password', event.target.value)} />{passwordForm.errors.current_password ? <span className="field-error">{passwordForm.errors.current_password}</span> : null}</div>
                        <div className="field"><label className="label req" htmlFor="doc-new">New password</label><input id="doc-new" className="input" type="password" value={passwordForm.data.password} onChange={(event) => passwordForm.setData('password', event.target.value)} />{passwordForm.errors.password ? <span className="field-error">{passwordForm.errors.password}</span> : null}</div>
                        <div className="field"><label className="label req" htmlFor="doc-confirm">Confirm new password</label><input id="doc-confirm" className="input" type="password" value={passwordForm.data.password_confirmation} onChange={(event) => passwordForm.setData('password_confirmation', event.target.value)} /></div>
                        <button type="submit" className="btn btn-primary" disabled={passwordForm.processing}>{passwordForm.processing ? 'Updating…' : 'Update password'}</button>
                    </form>
                </section>

                <section className="c-12 md-c-6 card card-pad">
                    <h2 className="h4">Preferences</h2>
                    <form className="stack-sm mt-s" onSubmit={(event) => { event.preventDefault(); prefForm.put('/doctor/settings/preferences', { preserveScroll: true }); }}>
                        <div className="field"><label className="label" htmlFor="doc-theme">Theme</label><select id="doc-theme" className="select" value={prefForm.data.theme} onChange={(event) => prefForm.setData('theme', event.target.value as 'system' | 'light' | 'dark')}><option value="system">System</option><option value="light">Light</option><option value="dark">Dark</option></select></div>
                        <div className="field"><label className="label" htmlFor="doc-lang">Language</label><select id="doc-lang" className="select" value={prefForm.data.language} onChange={(event) => prefForm.setData('language', event.target.value)}>{options.locales.map((locale) => <option key={locale} value={locale}>{locale}</option>)}</select></div>
                        <div className="field"><label className="label" htmlFor="doc-tz">Timezone</label><select id="doc-tz" className="select" value={prefForm.data.timezone} onChange={(event) => prefForm.setData('timezone', event.target.value)}>{options.timezones.map((tz) => <option key={tz} value={tz}>{tz}</option>)}</select></div>
                        <div className="field"><label className="label" htmlFor="doc-date">Date format</label><select id="doc-date" className="select" value={prefForm.data.date_format} onChange={(event) => prefForm.setData('date_format', event.target.value)}>{options.dateFormats.map((format) => <option key={format} value={format}>{format}</option>)}</select></div>
                        <label className="check"><input type="checkbox" checked={prefForm.data.reduce_motion} onChange={(event) => prefForm.setData('reduce_motion', event.target.checked)} /><span>Reduce motion</span></label>
                        <button type="submit" className="btn btn-primary" disabled={prefForm.processing}>{prefForm.processing ? 'Saving…' : 'Save preferences'}</button>
                    </form>
                    <p className="caption mt-m">Currency is clinic-wide ({settings.currencyCode}); doctors do not set money symbols.</p>
                </section>
            </div>
        </RoleLayout>
    );
}