/**
 * Admin · Clinical safety console — prototypes/admin/11-safety-console.html.
 * Emergency contacts, deterministic red-flag rules and symptom→specialty maps.
 * Green/red/blue badges here are clinical safety semantics, not decoration.
 */
import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { DataState } from '@/types';
import type { AdminEmergencyContact, AdminEmergencyRule, AdminSymptomMapping } from '@/types/admin';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { DataTable, type DataColumn } from '@/Components/Data/DataTable';
import { AdminModal } from '@/Components/Admin/AdminModal';

interface SafetyProps {
    tab: 'rules' | 'mapping';
    contacts: AdminEmergencyContact[];
    rules: AdminEmergencyRule[];
    mappings: AdminSymptomMapping[];
    specialties: Array<{ id: number; name: string }>;
    hotlines: Array<{ key: string; label: string; number: string }>;
    counts: { contacts: number; rules: number; mappings: number };
    dataState: DataState;
    lastKnownAt: string;
}

export default function AdminSafety({ tab, contacts, rules, mappings, specialties, hotlines, counts, dataState, lastKnownAt }: SafetyProps) {
    const [state, setState] = useState<DataState>(dataState);
    const [contactEditor, setContactEditor] = useState<AdminEmergencyContact | 'new' | null>(null);
    const [ruleEditor, setRuleEditor] = useState<AdminEmergencyRule | null>(null);
    const [mappingEditor, setMappingEditor] = useState<AdminSymptomMapping | 'new' | null>(null);

    const switchTab = (next: 'rules' | 'mapping') => {
        router.get('/admin/safety', next === 'mapping' ? { tab: 'mapping' } : {}, { preserveState: true, preserveScroll: true, replace: true });
    };

    const mappingColumns: Array<DataColumn<AdminSymptomMapping>> = [
        { key: 'symptom', label: 'Symptom keyword / category', render: (row) => <div><strong>{row.symptomCategory}</strong><div className="caption">{row.keywordsText}</div></div> },
        { key: 'specialty', label: 'Mapped specialty', render: (row) => row.specialty ?? '—' },
        { key: 'priority', label: 'Priority', align: 'right', render: (row) => <span className="num tabular">{row.priority}</span> },
        { key: 'active', label: 'Active', render: (row) => <span className={row.isActive ? 'badge badge-success' : 'badge badge-neutral'}><span className="dot" />{row.isActive ? 'active' : 'off'}</span> },
    ];

    return (
        <RoleLayout
            role="admin"
            title="Clinical safety console"
            subtitle="rule edits are logged and take effect on the next deterministic request"
            activeHref="/admin/safety"
            topbarActions={
                <button type="button" className="btn btn-primary" onClick={() => tab === 'rules' ? setContactEditor('new') : setMappingEditor('new')}>
                    {tab === 'rules' ? '+ Add contact' : '+ Add mapping'}
                </button>
            }
        >
            <Head title="Clinical safety console — MediPath" />

            <DataView
                name="Data view · Safety tables"
                state={state}
                onStateChange={setState}
                showSwitch
                onRetry={() => router.reload()}
                lastKnownAt={lastKnownAt}
                emptyTitle="No custom rules — defaults active"
                emptyBody="The engine ships with critical categories; add clinic-specific phrases to widen coverage."
                errorMessage="Rules table failed to load — the chatbot still runs cached rules; editing is disabled until refresh."
            >
                <div className="tabs" role="tablist" aria-label="Safety tables">
                    <button type="button" role="tab" aria-selected={tab === 'rules'} onClick={() => switchTab('rules')}>
                        Emergency contacts &amp; red-flag rules <span className="cnt tabular">{counts.contacts + counts.rules}</span>
                    </button>
                    <button type="button" role="tab" aria-selected={tab === 'mapping'} onClick={() => switchTab('mapping')}>
                        Symptom → specialty mapping <span className="cnt tabular">{counts.mappings}</span>
                    </button>
                </div>

                {tab === 'rules' ? (
                    <div className="grid mt-m" style={{ alignItems: 'start' }}>
                        <section className="c-12 lg-c-5 card card-pad" aria-label="Emergency contacts">
                            <h2 className="h4">Emergency contacts</h2>
                            <ul className="list-quiet mt-s" style={{ fontSize: 13 }}>
                                {contacts.map((contact) => (
                                    <li key={contact.id}>
                                        <span aria-hidden="true">{contact.icon ?? '🚨'}</span>
                                        <strong>{contact.label}</strong>
                                        <span className="mono">{contact.number}</span>
                                        <label className="switch mla">
                                            <input type="checkbox" checked={contact.isActive} onChange={() => router.post(`/admin/safety/contacts/${contact.id}/toggle`, {}, { preserveScroll: true })} />
                                            <span className="sr-only">Active</span>
                                        </label>
                                        <button type="button" className="btn btn-ghost btn-sm" onClick={() => setContactEditor(contact)}>Edit</button>
                                    </li>
                                ))}
                            </ul>
                            <p className="caption mt-m">These are the one-tap numbers on the guest emergency page.</p>
                        </section>

                        <section className="c-12 lg-c-7 card card-pad" aria-label="Red flag rules">
                            <h2 className="h4">Red-flag rules (deterministic engine)</h2>
                            <div className="tbl-wrap mt-s" style={{ border: 0 }}>
                                <table className="tbl">
                                    <caption className="sr-only">Emergency red-flag rules</caption>
                                    <thead><tr><th>Rule</th><th>Category</th><th>Severity</th><th>Active</th><th><span className="sr-only">Actions</span></th></tr></thead>
                                    <tbody>
                                        {rules.map((rule) => (
                                            <tr key={rule.id}>
                                                <td data-label="Rule"><strong>{rule.title}</strong><div className="caption">{rule.keywordsText}</div></td>
                                                <td data-label="Category"><span className={rule.severity === 'critical' ? 'badge badge-emergency' : 'badge badge-warning'}><span className="dot" />{rule.category}</span></td>
                                                <td data-label="Severity">{rule.severity}</td>
                                                <td data-label="Active">
                                                    <label className="switch">
                                                        <input type="checkbox" checked={rule.isActive} onChange={() => router.post(`/admin/safety/rules/${rule.id}/toggle`, {}, { preserveScroll: true })} />
                                                        <span className="sr-only">Active</span>
                                                    </label>
                                                </td>
                                                <td data-label="Actions"><button type="button" className="btn btn-ghost btn-sm" onClick={() => setRuleEditor(rule)}>Edit</button></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <div className="alert alert-warning mt-s" style={{ fontSize: 13 }}>
                                <span className="a-ico" aria-hidden="true">ℹ</span>
                                <span>Red is correct here: these are emergency surfaces. Rule text is matched deterministically before any AI/FAQ path.</span>
                            </div>
                        </section>
                    </div>
                ) : (
                    <div className="mt-m">
                        <DataTable
                            className="tbl-cards-md"
                            caption="Symptom to specialty mappings"
                            columns={mappingColumns}
                            rows={mappings}
                            getRowKey={(row) => row.id}
                            actions={(row) => (
                                <div className="mp-row">
                                    <button type="button" className="btn btn-ghost btn-sm" onClick={() => setMappingEditor(row)}>Edit</button>
                                    <button type="button" className="btn btn-ghost btn-sm" onClick={() => router.post(`/admin/safety/mappings/${row.id}/toggle`, {}, { preserveScroll: true })}>{row.isActive ? 'Disable' : 'Enable'}</button>
                                </div>
                            )}
                        />
                        <p className="caption mt-m">Unique mapping enforced; AI specialty suggestions are cross-validated against this table before doctors are shown.</p>
                    </div>
                )}
            </DataView>

            <ContactEditor contact={contactEditor} onClose={() => setContactEditor(null)} />
            <RuleEditor rule={ruleEditor} hotlines={hotlines} onClose={() => setRuleEditor(null)} />
            <MappingEditor mapping={mappingEditor} specialties={specialties} onClose={() => setMappingEditor(null)} />
        </RoleLayout>
    );
}

function ContactEditor({ contact, onClose }: { contact: AdminEmergencyContact | 'new' | null; onClose: () => void }) {
    const form = useForm({ key: '', label: '', number: '', icon: '', description: '', sort_order: '0', is_active: true });

    useEffect(() => {
        if (contact === 'new') form.setData({ key: '', label: '', number: '', icon: '', description: '', sort_order: '0', is_active: true });
        else if (contact) form.setData({ key: contact.key, label: contact.label, number: contact.number, icon: contact.icon ?? '', description: contact.description ?? '', sort_order: String(contact.sortOrder), is_active: contact.isActive });
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [contact]);

    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };
        if (contact && contact !== 'new') form.put(`/admin/safety/contacts/${contact.id}`, options);
        else form.post('/admin/safety/contacts', options);
    };

    return (
        <AdminModal open={Boolean(contact)} title={contact === 'new' ? 'Add emergency contact' : 'Edit emergency contact'} icon="🚨" onClose={onClose}
            footer={<><button type="button" className="btn btn-quiet" onClick={onClose}>Cancel</button><button type="submit" form="contact-form" className="btn btn-primary" disabled={form.processing}>Save contact</button></>}>
            <form id="contact-form" className="stack-sm" onSubmit={(event) => { event.preventDefault(); submit(); }}>
                <div className="grid">
                    <div className="c-12 sm-c-6 field"><label className="label req" htmlFor="contact-key">Key</label><input id="contact-key" className="input mono" value={form.data.key} onChange={(event) => form.setData('key', event.target.value)} placeholder="pediatric_er" />{form.errors.key ? <span className="field-error">{form.errors.key}</span> : null}</div>
                    <div className="c-12 sm-c-6 field"><label className="label req" htmlFor="contact-label">Label</label><input id="contact-label" className="input" value={form.data.label} onChange={(event) => form.setData('label', event.target.value)} /></div>
                    <div className="c-12 sm-c-6 field"><label className="label req" htmlFor="contact-number">Phone</label><input id="contact-number" className="input mono" value={form.data.number} onChange={(event) => form.setData('number', event.target.value)} /></div>
                    <div className="c-12 sm-c-6 field"><label className="label" htmlFor="contact-icon">Icon</label><input id="contact-icon" className="input" maxLength={12} value={form.data.icon} onChange={(event) => form.setData('icon', event.target.value)} /></div>
                    <div className="c-12 field"><label className="label" htmlFor="contact-description">Description</label><input id="contact-description" className="input" value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} /></div>
                    <div className="c-6 field"><label className="label" htmlFor="contact-sort">Sort</label><input id="contact-sort" className="input" type="number" min={0} value={form.data.sort_order} onChange={(event) => form.setData('sort_order', event.target.value)} /></div>
                    <label className="check c-6" style={{ alignSelf: 'end' }}><input type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} /><span>Active</span></label>
                </div>
            </form>
        </AdminModal>
    );
}

function RuleEditor({ rule, hotlines, onClose }: { rule: AdminEmergencyRule | null; hotlines: SafetyProps['hotlines']; onClose: () => void }) {
    const form = useForm({ category: '', title: '', keywords: '', severity: 'critical', action_directive: '', hotline_key: '', sort_order: '0' });

    useEffect(() => {
        if (rule) form.setData({ category: rule.category, title: rule.title, keywords: rule.keywordsText, severity: rule.severity, action_directive: rule.actionDirective, hotline_key: rule.hotlineKey ?? '', sort_order: String(rule.sortOrder) });
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [rule]);

    return (
        <AdminModal open={Boolean(rule)} title="Edit red-flag rule" subtitle="Keywords are matched deterministically before any AI path." icon="🛡" wide onClose={onClose}
            footer={<><button type="button" className="btn btn-quiet" onClick={onClose}>Cancel</button><button type="submit" form="rule-form" className="btn btn-primary" disabled={form.processing}>Save rule</button></>}>
            <form id="rule-form" className="stack-sm" onSubmit={(event) => { event.preventDefault(); if (rule) form.put(`/admin/safety/rules/${rule.id}`, { preserveScroll: true, onSuccess: onClose }); }}>
                <div className="grid">
                    <div className="c-12 sm-c-6 field"><label className="label req" htmlFor="rule-category">Category</label><input id="rule-category" className="input" value={form.data.category} onChange={(event) => form.setData('category', event.target.value)} /></div>
                    <div className="c-12 sm-c-6 field"><label className="label req" htmlFor="rule-severity">Severity</label><select id="rule-severity" className="select" value={form.data.severity} onChange={(event) => form.setData('severity', event.target.value)}><option value="critical">critical</option><option value="warning">warning</option><option value="info">info</option></select></div>
                    <div className="c-12 field"><label className="label req" htmlFor="rule-title">Title</label><input id="rule-title" className="input" value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} /></div>
                    <div className="c-12 field"><label className="label req" htmlFor="rule-keywords">Symptom keywords (comma separated)</label><textarea id="rule-keywords" className="textarea" rows={2} value={form.data.keywords} onChange={(event) => form.setData('keywords', event.target.value)} /></div>
                    <div className="c-12 field"><label className="label req" htmlFor="rule-action">Action directive</label><textarea id="rule-action" className="textarea" rows={2} value={form.data.action_directive} onChange={(event) => form.setData('action_directive', event.target.value)} /></div>
                    <div className="c-12 sm-c-6 field"><label className="label" htmlFor="rule-hotline">Hotline</label><select id="rule-hotline" className="select" value={form.data.hotline_key} onChange={(event) => form.setData('hotline_key', event.target.value)}><option value="">None</option>{hotlines.map((hotline) => <option key={hotline.key} value={hotline.key}>{hotline.label} · {hotline.number}</option>)}</select></div>
                    <div className="c-12 sm-c-6 field"><label className="label" htmlFor="rule-sort">Sort order</label><input id="rule-sort" className="input" type="number" min={0} value={form.data.sort_order} onChange={(event) => form.setData('sort_order', event.target.value)} /></div>
                </div>
            </form>
        </AdminModal>
    );
}

function MappingEditor({ mapping, specialties, onClose }: { mapping: AdminSymptomMapping | 'new' | null; specialties: SafetyProps['specialties']; onClose: () => void }) {
    const form = useForm({ symptom_category: '', keywords: '', specialty_id: '', priority: '0' });

    useEffect(() => {
        if (mapping === 'new') form.setData({ symptom_category: '', keywords: '', specialty_id: '', priority: '0' });
        else if (mapping) form.setData({ symptom_category: mapping.symptomCategory, keywords: mapping.keywordsText, specialty_id: String(mapping.specialtyId), priority: String(mapping.priority) });
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [mapping]);

    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };
        if (mapping && mapping !== 'new') form.put(`/admin/safety/mappings/${mapping.id}`, options);
        else form.post('/admin/safety/mappings', options);
    };

    return (
        <AdminModal open={Boolean(mapping)} title={mapping === 'new' ? 'Add symptom mapping' : 'Edit symptom mapping'} icon="🧭" onClose={onClose}
            footer={<><button type="button" className="btn btn-quiet" onClick={onClose}>Cancel</button><button type="submit" form="mapping-form" className="btn btn-primary" disabled={form.processing}>Save mapping</button></>}>
            <form id="mapping-form" className="stack-sm" onSubmit={(event) => { event.preventDefault(); submit(); }}>
                <div className="field"><label className="label req" htmlFor="map-cat">Symptom keyword/category</label><input id="map-cat" className="input" value={form.data.symptom_category} onChange={(event) => form.setData('symptom_category', event.target.value)} /></div>
                <div className="field"><label className="label req" htmlFor="map-keywords">Keywords (comma separated)</label><textarea id="map-keywords" className="textarea" rows={2} value={form.data.keywords} onChange={(event) => form.setData('keywords', event.target.value)} /></div>
                <div className="grid">
                    <div className="c-12 sm-c-8 field"><label className="label req" htmlFor="map-specialty">Specialty</label><select id="map-specialty" className="select" value={form.data.specialty_id} onChange={(event) => form.setData('specialty_id', event.target.value)}><option value="">Choose specialty</option>{specialties.map((specialty) => <option key={specialty.id} value={specialty.id}>{specialty.name}</option>)}</select></div>
                    <div className="c-12 sm-c-4 field"><label className="label" htmlFor="map-priority">Priority</label><input id="map-priority" className="input" type="number" min={0} value={form.data.priority} onChange={(event) => form.setData('priority', event.target.value)} /></div>
                </div>
            </form>
        </AdminModal>
    );
}