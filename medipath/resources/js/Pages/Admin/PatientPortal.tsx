/**
 * Admin · read-only patient portal route (deep-linkable preview).
 * The Patients screen renders the same panel inside its as-patient tab; this
 * page exists so the preview can be opened directly and refreshed.
 */
import { Head } from '@inertiajs/react';
import type { PortalData } from '@/types/admin';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { PatientPortalPreview } from '@/Components/Admin/PatientPortalPreview';

export default function AdminPatientPortal({ portal }: { portal: PortalData }) {
    return (
        <RoleLayout
            role="admin"
            title={`Patient portal — ${portal.patient.name}`}
            subtitle={`Read-only as-patient preview · ${portal.patient.code}`}
            activeHref="/admin/patients"
            topbarActions={<a className="btn btn-outline" href="/admin/patients">Back to patients</a>}
        >
            <Head title={`Portal preview — ${portal.patient.name}`} />
            <PatientPortalPreview portal={portal} />
        </RoleLayout>
    );
}