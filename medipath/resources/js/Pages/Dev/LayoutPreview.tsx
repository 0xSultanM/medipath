/**
 * Dev/LayoutPreview — render a role shell without signing in, for the
 * 360/768/1440 acceptance pass (local only). Uses the real RoleLayout, sidebar,
 * KPI cards and bell so responsive drawer behaviour is exercised exactly as in
 * production.
 */
import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import type { DataState, SharedProps } from '@/types';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { KpiCard } from '@/Components/Data/KpiCard';
import { WorkflowTracker, defaultWorkflow } from '@/Components/Workflow/Stepper';
import { ConfirmModal } from '@/Components/Overlay/ConfirmModal';
import { useToast } from '@/providers/ToastProvider';

interface LayoutPreviewProps {
    previewRole: string;
}

const PREVIEW: Record<string, { roleLabel: string; portal: string; name: string; initials: string }> = {
    admin: { roleLabel: 'Admin', portal: 'Administration', name: 'Sultana Ahmed', initials: 'SA' },
    doctor: { roleLabel: 'Doctor', portal: 'Doctor workspace', name: 'Dr. Ayesha Rahman', initials: 'AR' },
    pharmacist: { roleLabel: 'Pharmacist', portal: 'Pharmacy', name: 'Nadia Tabassum', initials: 'NT' },
    patient: { roleLabel: 'Patient', portal: 'Patient portal', name: 'Farhana Karim', initials: 'FK' },
};

export default function LayoutPreview({ previewRole }: LayoutPreviewProps) {
    const { ui } = usePage<SharedProps>().props;
    const info = PREVIEW[previewRole] ?? PREVIEW.patient;
    const [state, setState] = useState<DataState>(ui.initialState ?? 'success');
    const [modalOpen, setModalOpen] = useState(false);
    const { push } = useToast();

    return (
        <RoleLayout
            preview={{
                role: previewRole,
                roleLabel: info.roleLabel,
                portal: info.portal,
                name: info.name,
                initials: info.initials,
                dashboardUrl: `/${previewRole}/dashboard`,
            }}
            title={`${info.roleLabel} shell preview`}
            subtitle="local only · 360 / 768 / 1440"
            activeHref={`/${previewRole}/dashboard`}
        >
            <div className="dev-banner" role="note">
                Shell preview — no session required. Resize to 360 / 768 / 991 / 1440. The burger appears below 992 and
                the sidebar becomes a slide-over drawer with a focus trap.
            </div>

            <div className="preview-note mt-b" role="note">
                Links in the sidebar point to their final phase URLs; only <code className="mono">/dashboard</code> is
                mounted in Part 0.
            </div>

            <DataView
                name="Data view · Shell metrics"
                state={state}
                onStateChange={setState}
                showSwitch
                onRetry={() => push({ tone: 'info', message: 'Retry (preview).' })}
                lastKnownAt={new Date().toISOString()}
            >
                <div className="grid">
                    <div className="c-6 md-c-3">
                        <KpiCard label="Today’s appointments" value="6" delta="4 completed" trend="flat" />
                    </div>
                    <div className="c-6 md-c-3">
                        <KpiCard label="Pending requests" value="4" tone="warning" delta="oldest 3h" trend="down" />
                    </div>
                    <div className="c-6 md-c-3">
                        <KpiCard label="Prescriptions" value="12" delta="+3 this week" trend="up" />
                    </div>
                    <div className="c-6 md-c-3">
                        <KpiCard label="Average rating" value="4.7" delta="56 reviews" trend="flat" />
                    </div>
                </div>
            </DataView>

            <div className="grid mt-l" style={{ alignItems: 'start' }}>
                <section className="c-12 md-c-7 card card-pad">
                    <h2 className="h4">Workflow tracker (preview)</h2>
                    <div className="mt-b">
                        <WorkflowTracker steps={defaultWorkflow()} onJump={(key) => push({ tone: 'info', message: `Jump to ${key}` })} />
                    </div>
                </section>

                <section className="c-12 md-c-5 card card-pad stack-sm">
                    <h2 className="h4">Drawer / modal checks</h2>
                    <button type="button" className="btn btn-outline" onClick={() => setModalOpen(true)}>
                        Open confirm modal
                    </button>
                    <p className="caption">
                        <strong>360:</strong> modal is a full-height bottom sheet; tables stack; sidebar is a drawer.
                        <br />
                        <strong>768:</strong> 2-column grids; sidebar still a drawer (until 992).
                        <br />
                        <strong>1440:</strong> fixed 264px sidebar; content max 1440.
                    </p>
                </section>
            </div>

            <ConfirmModal
                open={modalOpen}
                title="Preview dependency counts"
                message={<p>This dialog demonstrates the §13 anatomy: counts, danger tone, mobile bottom sheet and focus trap.</p>}
                tone="danger"
                confirmMode="click"
                confirmLabel="Acknowledge"
                dependencies={[
                    { label: 'appointments', count: 4 },
                    { label: 'prescriptions', count: 12 },
                ]}
                onClose={() => setModalOpen(false)}
                onConfirm={() => {
                    setModalOpen(false);
                    push({ tone: 'success', message: 'Preview confirmed.' });
                }}
            />
        </RoleLayout>
    );
}