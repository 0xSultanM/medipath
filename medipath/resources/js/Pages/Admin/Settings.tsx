/**
 * Admin · Global system settings — prototypes/admin/12-settings.html.
 * Settings are versioned, cached and broadcast; revenue % is effective-dated
 * so existing invoices are never re-priced. Preview uses paid invoices only.
 */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { DataState, SharedProps } from '@/types';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { Money } from '@/Components/ui/Money';
import { formatDate } from '@/lib/format';

interface SettingsProps {
    settings: {
        clinicName: string;
        clinicAddress: string;
        clinicPhone: string;
        pdfHeader: string;
        pdfFooter: string;
        currencyCode: string;
        locale: string;
        timezone: string;
        dateFormat: string;
        patientAutoApprove: boolean;
        cancellationCutoffHours: number;
        aiEnabled: boolean;
        aiModel: string;
        revenuePercentage: number;
        revenueConfigured: boolean;
    };
    preview: { paidFormatted: string; commissionFormatted: string; percentage: number; configured: boolean; invoiceCount: number };
    revenueHistory: Array<{ id: number; percentage: number; effectiveFrom: string | null; note: string | null; createdBy: string | null; createdAt: string | null }>;
    settingRows: Array<{ key: string; label: string; value: unknown; group: string; type: string; updatedBy: string | null; updatedAt: string | null }>;
    sampleMoney: string;
    options: { locales: string[]; timezones: string[]; dateFormats: string[]; currencies: string[] };
}

export default function AdminSettings({ settings, preview, revenueHistory, settingRows, options, sampleMoney }: SettingsProps) {
    const { settings: shared } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>('success');
    const form = useForm({
        clinic_name: settings.clinicName,
        clinic_address: settings.clinicAddress,
        clinic_phone: settings.clinicPhone,
        pdf_header: settings.pdfHeader,
        pdf_footer: settings.pdfFooter,
        currency_code: settings.currencyCode,
        locale: settings.locale,
        timezone: settings.timezone,
        date_format: settings.dateFormat,
        patient_auto_approve: settings.patientAutoApprove,
        cancellation_cutoff_hours: String(settings.cancellationCutoffHours),
        ai_enabled: settings.aiEnabled,
        ai_model: settings.aiModel,
        revenue_percentage: settings.revenueConfigured ? String(settings.revenuePercentage) : '',
        revenue_effective_from: new Date().toISOString().slice(0, 10),
        revenue_note: '',
    });

    const submit = () => {
        form.put('/admin/settings', { preserveScroll: true });
    };

    return (
        <RoleLayout
            role="admin"
            title="Global system settings"
            subtitle="changes are versioned (effective_from) and broadcast to every role on save"
            activeHref="/admin/settings"
        >
            <Head title="System settings — MediPath" />

            <div className="grid">
                <section className="c-12 xl-c-7 card card-pad">
                    <h2 className="h4">Clinic identity</h2>
                    <div className="grid tight">
                        <div className="c-12 sm-c-6 field"><label className="label req" htmlFor="set-name">Clinic name</label><input id="set-name" className="input" value={form.data.clinic_name} onChange={(event) => form.setData('clinic_name', event.target.value)} />{form.errors.clinic_name ? <span className="field-error">{form.errors.clinic_name}</span> : null}</div>
                        <div className="c-12 sm-c-6 field"><label className="label" htmlFor="set-address">Clinic address</label><input id="set-address" className="input" value={form.data.clinic_address} onChange={(event) => form.setData('clinic_address', event.target.value)} /></div>
                        <div className="c-12 sm-c-6 field"><label className="label" htmlFor="set-phone">Clinic phone</label><input id="set-phone" className="input mono" value={form.data.clinic_phone} onChange={(event) => form.setData('clinic_phone', event.target.value)} /></div>
                        <div className="c-12 sm-c-6 field"><label className="label" htmlFor="set-pdf">PDF header</label><input id="set-pdf" className="input" value={form.data.pdf_header} onChange={(event) => form.setData('pdf_header', event.target.value)} /></div>
                        <div className="c-12 field"><label className="label" htmlFor="set-footer">PDF footer</label><input id="set-footer" className="input" value={form.data.pdf_footer} onChange={(event) => form.setData('pdf_footer', event.target.value)} /></div>
                    </div>

                    <h2 className="h4 mt-l">Money &amp; locale</h2>
                    <div className="grid tight">
                        <div className="c-12 sm-c-4 field"><label className="label req" htmlFor="set-cur">Currency code (drives the symbol)</label><select id="set-cur" className="select" value={form.data.currency_code} onChange={(event) => form.setData('currency_code', event.target.value)}>{options.currencies.map((code) => <option key={code} value={code}>{code}</option>)}</select></div>
                        <div className="c-12 sm-c-4 field"><label className="label" htmlFor="set-locale">Locale</label><select id="set-locale" className="select" value={form.data.locale} onChange={(event) => form.setData('locale', event.target.value)}>{options.locales.map((locale) => <option key={locale} value={locale}>{locale}</option>)}</select></div>
                        <div className="c-12 sm-c-4 field"><label className="label" htmlFor="set-tz">Timezone</label><select id="set-tz" className="select" value={form.data.timezone} onChange={(event) => form.setData('timezone', event.target.value)}>{options.timezones.map((tz) => <option key={tz} value={tz}>{tz}</option>)}</select></div>
                        <div className="c-12 sm-c-4 field"><label className="label" htmlFor="set-date">Date format</label><select id="set-date" className="select" value={form.data.date_format} onChange={(event) => form.setData('date_format', event.target.value)}>{options.dateFormats.map((format) => <option key={format} value={format}>{format}</option>)}</select></div>
                        <div className="c-12 sm-c-8" style={{ alignSelf: 'end' }}><p className="caption" role="status">Preview: <Money formatted={sampleMoney} /> — runtime symbol resolves from the setting, never a literal dollar sign.</p></div>
                    </div>

                    <h2 className="h4 mt-l">Booking &amp; assistant</h2>
                    <div className="grid tight">
                        <div className="c-12 sm-c-6 field"><label className="label" htmlFor="set-cutoff">Cancellation cutoff (hours)</label><input id="set-cutoff" className="input" type="number" min={0} max={72} value={form.data.cancellation_cutoff_hours} onChange={(event) => form.setData('cancellation_cutoff_hours', event.target.value)} /><span className="hint">shown to patients and enforced server-side.</span></div>
                        <label className="check c-12 sm-c-6" style={{ alignSelf: 'end' }}><input type="checkbox" checked={form.data.patient_auto_approve} onChange={(event) => form.setData('patient_auto_approve', event.target.checked)} /><span>Patient accounts auto-approve on registration</span></label>
                        <label className="check c-12 sm-c-6"><input type="checkbox" checked={form.data.ai_enabled} onChange={(event) => form.setData('ai_enabled', event.target.checked)} /><span>AI assistance enabled (deterministic paths always active)</span></label>
                        <div className="c-12 sm-c-6 field"><label className="label" htmlFor="set-model">AI model label</label><input id="set-model" className="input" value={form.data.ai_model} onChange={(event) => form.setData('ai_model', event.target.value)} /></div>
                    </div>

                    <h2 className="h4 mt-l">Revenue commission %</h2>
                    <div className="mp-row-lg mt-s">
                        <input
                            id="set-rev"
                            type="range"
                            min={0}
                            max={100}
                            step={0.5}
                            value={form.data.revenue_percentage || '0'}
                            onChange={(event) => form.setData('revenue_percentage', event.target.value)}
                            style={{ flex: 1 }}
                            aria-describedby="rev-preview"
                            aria-label="Revenue commission percentage"
                        />
                        <output className="money" id="rev-preview">{form.data.revenue_percentage || '0'}%</output>
                    </div>
                    <div className="grid tight mt-s">
                        <div className="c-12 sm-c-5 field"><label className="label" htmlFor="set-rev-date">Effective from</label><input id="set-rev-date" className="input" type="date" value={form.data.revenue_effective_from} onChange={(event) => form.setData('revenue_effective_from', event.target.value)} /></div>
                        <div className="c-12 sm-c-7 field"><label className="label" htmlFor="set-rev-note">Change note</label><input id="set-rev-note" className="input" value={form.data.revenue_note} onChange={(event) => form.setData('revenue_note', event.target.value)} placeholder="board decision, contract renewal…" /></div>
                    </div>
                    <p className="caption">CHECK 0–100 enforced at DB. Only future invoices use the new commission; history is never re-priced.</p>

                    <div className="mp-row mt-b">
                        <button type="button" className="btn btn-primary" onClick={submit} disabled={form.processing}>{form.processing ? 'Saving…' : 'Save settings'}</button>
                        <a className="btn btn-ghost" href="/admin/activity?q=settings.updated">See change history in activity log</a>
                        {form.recentlySuccessful ? <span className="caption" role="status">Saved.</span> : null}
                    </div>
                </section>

                <aside className="c-12 xl-c-5" style={{ alignSelf: 'start' }}>
                    <DataView
                        name="Data view · Impact preview"
                        state={state}
                        onStateChange={setState}
                        showSwitch
                        lastKnownAt={new Date().toISOString()}
                        emptyTitle="No completed invoices yet in preview window"
                        emptyBody="The preview uses paid invoices only; save is still allowed."
                        errorMessage="Preview failed — Save remains allowed, but show the warning."
                        skeleton={<div className="sk" style={{ height: 160, borderRadius: 12 }} />}
                    >
                        <div className="card card-pad">
                            <h3 className="h4">If saved now ({preview.percentage.toFixed(1)}% current)</h3>
                            <dl className="mt-s">
                                <div className="def-row"><dt>Paid invoices (30d)</dt><dd><Money formatted={preview.paidFormatted} size="sm" /> · {preview.invoiceCount}</dd></div>
                                <div className="def-row"><dt>Commission at current %</dt><dd><Money formatted={preview.commissionFormatted} size="sm" /></dd></div>
                                <div className="def-row" style={{ borderBottom: 0 }}><dt>New commission</dt><dd><Money formatted={preview.paidFormatted} size="sm" /> × {form.data.revenue_percentage || '0'}%</dd></div>
                            </dl>
                            <p className="caption">Preview uses completed invoices only; the confirmation applies only from the effective date.</p>
                        </div>
                    </DataView>

                    <section className="card card-pad mt-l">
                        <h3 className="h4">Revenue history</h3>
                        <ul className="list-quiet mt-s" style={{ fontSize: 13 }}>
                            {revenueHistory.length === 0 ? <li>No revenue configuration yet.</li> : revenueHistory.map((row) => (
                                <li key={row.id}>
                                    <strong>{row.percentage.toFixed(2)}%</strong>
                                    <span className="caption"> from {formatDate(row.effectiveFrom, { locale: shared.locale, timezone: shared.timezone, dateFormat: shared.dateFormat })} · {row.createdBy ?? 'system'}</span>
                                    {row.note ? <div className="caption">{row.note}</div> : null}
                                </li>
                            ))}
                        </ul>
                    </section>

                    <section className="card card-pad mt-l">
                        <h3 className="h4">Effective settings</h3>
                        <p className="caption">Reference only; edits happen above and are stamped with the acting admin.</p>
                        <ul className="list-quiet mt-s" style={{ fontSize: 12 }}>
                            {settingRows.slice(0, 12).map((row) => (
                                <li key={row.key}><span className="mono">{row.key}</span><span className="mla caption">{String(row.value)}</span></li>
                            ))}
                        </ul>
                    </section>
                </aside>
            </div>
        </RoleLayout>
    );
}