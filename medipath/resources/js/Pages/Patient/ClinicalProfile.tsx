/**
 * Patient · Allergies & current medications — prototypes/patient/07.
 * These lists feed prescribing and pharmacy safety gates live; edits are
 * recorded in the audit trail and duplicates are rejected server-side.
 */
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { DataState, SharedProps } from '@/types';
import type { PatientAllergy, PatientMedication } from '@/types/patient';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { DataTable, type DataColumn } from '@/Components/Data/DataTable';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { AdminModal } from '@/Components/Admin/AdminModal';
import { formatDate } from '@/lib/format';

interface ClinicalProfileProps {
    tab: 'allergies' | 'medications';
    allergies: PatientAllergy[];
    medications: PatientMedication[];
    counts: { allergies: number; severeAllergies: number; medications: number; activeMedications: number };
    options: { allergyTypes: string[]; severities: Array<{ value: string; label: string }> };
    dataState: DataState;
    lastKnownAt: string;
}

export default function PatientClinicalProfile({ tab, allergies, medications, counts, options, dataState, lastKnownAt }: ClinicalProfileProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(dataState);
    const [allergyEditor, setAllergyEditor] = useState<PatientAllergy | 'new' | null>(null);
    const [medicationEditor, setMedicationEditor] = useState<PatientMedication | 'new' | null>(null);
    const [removeTarget, setRemoveTarget] = useState<PatientAllergy | null>(null);

    const switchTab = (next: 'allergies' | 'medications') => {
        router.get('/patient/clinical-profile', next === 'medications' ? { tab: 'medications' } : {}, { preserveState: true, preserveScroll: true, replace: true });
    };

    const allergyColumns: Array<DataColumn<PatientAllergy>> = [
        { key: 'substance', label: 'Substance', render: (row) => <strong>{row.substance}</strong> },
        { key: 'type', label: 'Category', render: (row) => row.type },
        { key: 'severity', label: 'Severity', render: (row) => <StatusBadge family="allergy" status={row.severity} label={row.severityLabel} /> },
        { key: 'reaction', label: 'Reaction', render: (row) => row.reactionNotes ?? '—' },
    ];

    const medicationColumns: Array<DataColumn<PatientMedication>> = [
        { key: 'name', label: 'Medicine', render: (row) => <strong>{row.name}</strong> },
        { key: 'dosage', label: 'Dosage', render: (row) => row.dosage },
        { key: 'frequency', label: 'Frequency', render: (row) => row.frequency },
        { key: 'since', label: 'Since', render: (row) => <span className="tabular">{formatDate(row.startDate, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })}</span> },
        { key: 'active', label: 'Active', render: (row) => <span className={row.isActive ? 'badge badge-success' : 'badge badge-neutral'}><span className="dot" />{row.isActive ? 'active' : 'stopped'}</span> },
    ];

    return (
        <RoleLayout
            role="patient"
            title="Allergies & current medications"
            subtitle={`${counts.severeAllergies} severe allergy alert(s) · ${counts.activeMedications} active medication(s)`}
            activeHref="/patient/clinical-profile"
            topbarActions={<button type="button" className="btn btn-primary" onClick={() => tab === 'allergies' ? setAllergyEditor('new') : setMedicationEditor('new')}>{tab === 'allergies' ? '+ Add allergy' : '+ Add medication'}</button>}
        >
            <Head title="Allergies & medicines — MediPath" />

            <div className="alert alert-info">
                <span className="a-ico" aria-hidden="true">🛡</span>
                <span>Doctors and the pharmacy safety gate read these lists <strong>live</strong> at prescribing and dispensing. Keeping them current is the most protective thing you can do.</span>
            </div>

            <DataView
                name="Data view · Safety lists"
                state={state}
                onStateChange={setState}
                showSwitch
                onRetry={() => router.reload()}
                lastKnownAt={lastKnownAt}
                emptyTitle="Nothing recorded yet"
                emptyBody="Add any allergy or medicine you take — clinicians see it instantly."
                emptyAction={<button type="button" className="btn btn-primary" onClick={() => tab === 'allergies' ? setAllergyEditor('new') : setMedicationEditor('new')}>Add the first entry</button>}
                errorMessage="Safety lists couldn't load. Prescribing still uses clinician re-verification."
            >
                <div className="tabs" role="tablist" aria-label="Safety lists">
                    <button type="button" role="tab" aria-selected={tab === 'allergies'} onClick={() => switchTab('allergies')}>Allergies <span className="cnt tabular">{counts.allergies}</span></button>
                    <button type="button" role="tab" aria-selected={tab === 'medications'} onClick={() => switchTab('medications')}>Current medications <span className="cnt tabular">{counts.medications}</span></button>
                </div>

                <div className="mt-m">
                    {tab === 'allergies' ? (
                        <DataTable
                            className="tbl-cards-md"
                            caption="Your recorded allergies"
                            columns={allergyColumns}
                            rows={allergies}
                            getRowKey={(row) => row.id}
                            actions={(row) => (
                                <div className="mp-row">
                                    <button type="button" className="btn btn-ghost btn-sm" onClick={() => setAllergyEditor(row)}>Edit</button>
                                    <button type="button" className="btn btn-ghost btn-sm" style={{ color: 'var(--mp-danger-text)' }} onClick={() => setRemoveTarget(row)}>Remove…</button>
                                </div>
                            )}
                        />
                    ) : (
                        <DataTable
                            className="tbl-cards-md"
                            caption="Medications you currently take"
                            columns={medicationColumns}
                            rows={medications}
                            getRowKey={(row) => row.id}
                            actions={(row) => (
                                <div className="mp-row">
                                    <button type="button" className="btn btn-ghost btn-sm" onClick={() => setMedicationEditor(row)}>Edit</button>
                                    <button type="button" className="btn btn-ghost btn-sm" onClick={() => router.post(`/patient/clinical-profile/medications/${row.id}/toggle`, {}, { preserveScroll: true })}>{row.isActive ? 'Mark stopped' : 'Reactivate'}</button>
                                </div>
                            )}
                        />
                    )}
                </div>
                <p className="caption mt-m">Duplicates are rejected server-side by the unique active-substance constraint. Clinicians see the change history.</p>
            </DataView>

            <AllergyEditor allergy={allergyEditor} options={options} onClose={() => setAllergyEditor(null)} />
            <MedicationEditor medication={medicationEditor} onClose={() => setMedicationEditor(null)} />
            <RemoveAllergyModal allergy={removeTarget} onClose={() => setRemoveTarget(null)} />
        </RoleLayout>
    );
}

function AllergyEditor({ allergy, options, onClose }: { allergy: PatientAllergy | 'new' | null; options: ClinicalProfileProps['options']; onClose: () => void }) {
    const form = useForm({ substance: '', allergy_type: 'medication', severity: 'moderate', reaction_notes: '' });

    useEffect(() => {
        if (allergy === 'new') form.setData({ substance: '', allergy_type: 'medication', severity: 'moderate', reaction_notes: '' });
        else if (allergy) form.setData({ substance: allergy.substance, allergy_type: allergy.type, severity: allergy.severity, reaction_notes: allergy.reactionNotes ?? '' });
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [allergy]);

    const submit = () => {
        const opts = { preserveScroll: true, onSuccess: onClose };
        if (allergy && allergy !== 'new') form.put(`/patient/clinical-profile/allergies/${allergy.id}`, opts);
        else form.post('/patient/clinical-profile/allergies', opts);
    };

    return (
        <AdminModal open={Boolean(allergy)} title={allergy === 'new' ? 'Add allergy' : 'Edit allergy'} icon="⚠️" onClose={onClose}
            footer={<><button type="button" className="btn btn-quiet" onClick={onClose}>Cancel</button><button type="submit" form="allergy-form" className="btn btn-primary" disabled={form.processing}>{form.processing ? 'Saving…' : 'Save allergy'}</button></>}>
            <form id="allergy-form" className="stack-sm" onSubmit={(event) => { event.preventDefault(); submit(); }}>
                <div className="field"><label className="label req" htmlFor="allergy-substance">Substance</label><input id="allergy-substance" className="input" value={form.data.substance} onChange={(event) => form.setData('substance', event.target.value)} />{form.errors.substance ? <span className="field-error">{form.errors.substance}</span> : null}</div>
                <div className="grid tight">
                    <div className="c-12 sm-c-6 field"><label className="label" htmlFor="allergy-type">Category</label><select id="allergy-type" className="select" value={form.data.allergy_type} onChange={(event) => form.setData('allergy_type', event.target.value)}>{options.allergyTypes.map((type) => <option key={type} value={type}>{type}</option>)}</select></div>
                    <div className="c-12 sm-c-6 field"><label className="label" htmlFor="allergy-severity">Severity</label><select id="allergy-severity" className="select" value={form.data.severity} onChange={(event) => form.setData('severity', event.target.value)}>{options.severities.map((severity) => <option key={severity.value} value={severity.value}>{severity.label}</option>)}</select></div>
                </div>
                <div className="field"><label className="label" htmlFor="allergy-reaction">Reaction notes</label><textarea id="allergy-reaction" className="textarea" rows={3} value={form.data.reaction_notes} onChange={(event) => form.setData('reaction_notes', event.target.value)} placeholder="What happened, when, how it was treated" /></div>
            </form>
        </AdminModal>
    );
}

function MedicationEditor({ medication, onClose }: { medication: PatientMedication | 'new' | null; onClose: () => void }) {
    const form = useForm({ medicine_name: '', dosage: '', frequency: '', start_date: '', end_date: '', notes: '' });

    useEffect(() => {
        if (medication === 'new') form.setData({ medicine_name: '', dosage: '', frequency: '', start_date: new Date().toISOString().slice(0, 10), end_date: '', notes: '' });
        else if (medication) form.setData({ medicine_name: medication.name, dosage: medication.dosage, frequency: medication.frequency, start_date: medication.startDate ?? '', end_date: medication.endDate ?? '', notes: medication.notes ?? '' });
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [medication]);

    const submit = () => {
        const opts = { preserveScroll: true, onSuccess: onClose };
        if (medication && medication !== 'new') form.put(`/patient/clinical-profile/medications/${medication.id}`, opts);
        else form.post('/patient/clinical-profile/medications', opts);
    };

    return (
        <AdminModal open={Boolean(medication)} title={medication === 'new' ? 'Add medication' : 'Edit medication'} icon="💊" onClose={onClose}
            footer={<><button type="button" className="btn btn-quiet" onClick={onClose}>Cancel</button><button type="submit" form="medication-form" className="btn btn-primary" disabled={form.processing}>{form.processing ? 'Saving…' : 'Save medication'}</button></>}>
            <form id="medication-form" className="stack-sm" onSubmit={(event) => { event.preventDefault(); submit(); }}>
                <div className="field"><label className="label req" htmlFor="med-name">Medicine name (as printed)</label><input id="med-name" className="input" value={form.data.medicine_name} onChange={(event) => form.setData('medicine_name', event.target.value)} />{form.errors.medicine_name ? <span className="field-error">{form.errors.medicine_name}</span> : null}</div>
                <div className="grid tight">
                    <div className="c-12 sm-c-4 field"><label className="label req" htmlFor="med-dosage">Dosage</label><input id="med-dosage" className="input" value={form.data.dosage} onChange={(event) => form.setData('dosage', event.target.value)} /></div>
                    <div className="c-12 sm-c-4 field"><label className="label req" htmlFor="med-frequency">Frequency</label><input id="med-frequency" className="input" value={form.data.frequency} onChange={(event) => form.setData('frequency', event.target.value)} /></div>
                    <div className="c-12 sm-c-4 field"><label className="label" htmlFor="med-start">Start date</label><input id="med-start" className="input" type="date" value={form.data.start_date} onChange={(event) => form.setData('start_date', event.target.value)} /></div>
                </div>
                <div className="field"><label className="label" htmlFor="med-notes">Notes</label><textarea id="med-notes" className="textarea" rows={2} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} /></div>
            </form>
        </AdminModal>
    );
}

function RemoveAllergyModal({ allergy, onClose }: { allergy: PatientAllergy | null; onClose: () => void }) {
    return (
        <AdminModal
            open={Boolean(allergy)}
            title={`Remove “${allergy?.substance ?? ''}”?`}
            subtitle="Future prescriptions stop auto-blocking this substance."
            icon="🗑"
            danger
            onClose={onClose}
            footer={<><button type="button" className="btn btn-quiet" onClick={onClose}>Keep it</button><button type="button" className="btn btn-danger" onClick={() => { if (allergy) router.delete(`/patient/clinical-profile/allergies/${allergy.id}`, { preserveScroll: true, onSuccess: onClose }); }}>Remove allergy</button></>}
        >
            <p style={{ fontSize: 14 }}>The change is recorded with who/when in the activity log and shown to clinicians as a history line.</p>
        </AdminModal>
    );
}