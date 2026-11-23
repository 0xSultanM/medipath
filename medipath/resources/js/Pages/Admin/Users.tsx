/**
 * Admin · User management — prototypes/admin/02-users.html.
 * Server-side search/filter/pagination; create internal users, atomic role
 * changes with reason, suspend/restore, and typed-confirm archive. The final
 * active admin and the signed-in admin are protected server-side.
 */
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { DataState, PageMeta, SharedProps } from '@/types';
import type { AdminUserRow } from '@/types/admin';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { DataTable, type DataColumn } from '@/Components/Data/DataTable';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { AdminModal } from '@/Components/Admin/AdminModal';
import { cn, formatDate } from '@/lib/format';
import { useDebouncedValue } from '@/hooks/useUi';

interface UsersProps {
    users: AdminUserRow[];
    meta: PageMeta;
    filters: { q: string; role: string; status: string; idle: boolean };
    roles: Record<string, string>;
    statuses: Record<string, string>;
    specialties: Array<{ id: number; name: string }>;
    dataState: DataState;
    lastKnownAt: string;
}

export default function AdminUsers({
    users,
    meta,
    filters,
    roles,
    statuses,
    specialties,
    dataState,
    lastKnownAt,
}: UsersProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(dataState);
    const [search, setSearch] = useState(filters.q);
    const [createOpen, setCreateOpen] = useState(false);
    const [editing, setEditing] = useState<AdminUserRow | null>(null);
    const [roleTarget, setRoleTarget] = useState<AdminUserRow | null>(null);
    const [suspendTarget, setSuspendTarget] = useState<AdminUserRow | null>(null);
    const [archiveTarget, setArchiveTarget] = useState<AdminUserRow | null>(null);

    const debounced = useDebouncedValue(search, 350);

    const apply = (partial: Partial<Record<string, string | boolean | number>> = {}, page = 1) => {
        const params: Record<string, string> = {
            q: filters.q,
            role: filters.role,
            status: filters.status,
            idle: filters.idle ? '1' : '',
            ...Object.fromEntries(Object.entries(partial).map(([key, value]) => [key, value === false || value === 0 ? '' : String(value)])),
        };
        if (page > 1) params.page = String(page);

        Object.keys(params).forEach((key) => {
            if (params[key] === '' || params[key] === '0') delete params[key];
        });

        router.get('/admin/users', params, { preserveState: true, preserveScroll: true, replace: true });
    };

    useEffect(() => {
        if (debounced !== filters.q) apply({ q: debounced });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [debounced]);

    const columns: Array<DataColumn<AdminUserRow>> = [
        {
            key: 'user',
            label: 'User',
            render: (user) => (
                <div className="avatar-note">
                    <span className="avatar avatar-initials" style={{ width: 32, height: 32, fontSize: 11 }} aria-hidden="true">
                        {user.initials}
                    </span>
                    <div>
                        <strong>{user.name}</strong>
                        {user.isSelf ? <span className="caption"> (you)</span> : null}
                        <div className="caption mono">
                            {user.email}
                            {user.patientCode || user.doctorCode || user.pharmacistCode ? ` · ${user.patientCode ?? user.doctorCode ?? user.pharmacistCode}` : ''}
                        </div>
                    </div>
                </div>
            ),
        },
        {
            key: 'role',
            label: 'Role',
            render: (user) => (
                <div>
                    <span className="badge badge-primary">{user.roleLabel}</span>
                    {user.specialty ? <div className="caption">{user.specialty}</div> : null}
                    {user.licenseNumber ? <div className="caption mono">{user.licenseNumber}</div> : null}
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (user) => (
                <div className="stack-sm" style={{ gap: 4 }}>
                    <StatusBadge family="account" status={user.status} label={user.statusLabel} />
                    {user.verificationLabel && user.role === 'doctor' ? (
                        <span className="caption">{user.verificationLabel}</span>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'lastLogin',
            label: 'Last login',
            render: (user) => (
                <span className="tabular">
                    {user.lastLoginAt
                        ? formatDate(user.lastLoginAt, { locale: settings.locale, timezone: settings.timezone, dateFormat: settings.dateFormat })
                        : 'never'}
                </span>
            ),
        },
    ];

    return (
        <RoleLayout
            role="admin"
            title="User management"
            subtitle={`${meta.total} account${meta.total === 1 ? '' : 's'}`}
            activeHref="/admin/users"
            topbarActions={
                <button type="button" className="btn btn-primary" onClick={() => setCreateOpen(true)}>
                    + Create internal user
                </button>
            }
        >
            <Head title="User management — MediPath" />

            <form
                className="filter-bar"
                aria-label="User filters"
                onSubmit={(event) => {
                    event.preventDefault();
                    apply({ q: search });
                }}
            >
                <div className="field" style={{ margin: 0 }}>
                    <label className="label" htmlFor="user-q">Search</label>
                    <input
                        className="input"
                        id="user-q"
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="name, email, phone"
                    />
                </div>
                <div className="field" style={{ margin: 0 }}>
                    <label className="label" htmlFor="user-role">Role</label>
                    <select className="select" id="user-role" value={filters.role} onChange={(event) => apply({ role: event.target.value })}>
                        <option value="">Any role</option>
                        {Object.entries(roles).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </select>
                </div>
                <div className="field" style={{ margin: 0 }}>
                    <label className="label" htmlFor="user-status">Status</label>
                    <select className="select" id="user-status" value={filters.status} onChange={(event) => apply({ status: event.target.value })}>
                        <option value="">Any status</option>
                        {Object.entries(statuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </select>
                </div>
                <label className="switch">
                    <input
                        type="checkbox"
                        checked={filters.idle}
                        onChange={(event) => apply({ idle: event.target.checked ? '1' : '' })}
                    />
                    <span>Last login &gt; 90d</span>
                </label>
                <div className="f-actions f-full">
                    <span className="caption">{meta.total} match{meta.total === 1 ? '' : 'es'} · server-side filters</span>
                    <button
                        type="button"
                        className="btn btn-ghost mla"
                        onClick={() => {
                            setSearch('');
                            router.get('/admin/users', {}, { preserveState: true, preserveScroll: true, replace: true });
                        }}
                    >
                        Clear filters
                    </button>
                </div>
            </form>

            <DataView
                name="Data view · User directory"
                state={state}
                onStateChange={setState}
                showSwitch
                onRetry={() => router.reload()}
                lastKnownAt={lastKnownAt}
                emptyTitle="No users match"
                emptyBody="Try “Any role” and “Any status”, or clear the search."
                errorMessage="Directory failed to load. Filters and pagination are server-side; retry is safe."
            >
                <DataTable
                    caption="Users matching the current filters"
                    className="tbl-cards-md"
                    columns={columns}
                    rows={users}
                    getRowKey={(user) => user.id}
                    state="success"
                    page={meta}
                    onPageChange={(page) => apply({}, page)}
                    actions={(user) => (
                        <div className="mp-row" style={{ flexWrap: 'wrap' }}>
                            {user.status === 'pending' ? (
                                <Link className="btn btn-primary btn-sm" href="/admin/approvals">Open approval</Link>
                            ) : null}
                            <button type="button" className="btn btn-ghost btn-sm" onClick={() => setEditing(user)}>Edit</button>
                            {!user.isSelf && !user.isFinalAdmin ? (
                                <button type="button" className="btn btn-ghost btn-sm" onClick={() => setRoleTarget(user)}>
                                    Change role…
                                </button>
                            ) : null}
                            {user.status === 'suspended' || user.status === 'archived' ? (
                                <button
                                    type="button"
                                    className="btn btn-ghost btn-sm"
                                    onClick={() => router.post(`/admin/users/${user.id}/restore`, {}, { preserveScroll: true })}
                                >
                                    Restore
                                </button>
                            ) : user.status === 'approved' && !user.isSelf && !user.isFinalAdmin ? (
                                <button
                                    type="button"
                                    className="btn btn-ghost btn-sm"
                                    style={{ color: 'var(--mp-danger-text)' }}
                                    onClick={() => setSuspendTarget(user)}
                                >
                                    Suspend…
                                </button>
                            ) : null}
                            {!user.isSelf && !user.isFinalAdmin && user.status !== 'archived' ? (
                                <button type="button" className="btn btn-ghost btn-sm" onClick={() => setArchiveTarget(user)}>
                                    Archive…
                                </button>
                            ) : null}
                            {user.isFinalAdmin ? <span className="caption">final active admin</span> : null}
                        </div>
                    )}
                />
            </DataView>

            <CreateUserModal open={createOpen} roles={roles} onClose={() => setCreateOpen(false)} />
            <EditUserModal user={editing} onClose={() => setEditing(null)} />
            <RoleModal
                user={roleTarget}
                roles={roles}
                specialties={specialties}
                onClose={() => setRoleTarget(null)}
            />
            <SuspendModal user={suspendTarget} onClose={() => setSuspendTarget(null)} />
            <ArchiveUserModal user={archiveTarget} onClose={() => setArchiveTarget(null)} />
        </RoleLayout>
    );
}

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <span className="field-error" role="alert">{message}</span>;
}

function CreateUserModal({ open, roles, onClose }: { open: boolean; roles: Record<string, string>; onClose: () => void }) {
    const form = useForm({
        name: '',
        email: '',
        phone: '',
        role: 'patient',
        password: '',
        password_confirmation: '',
    });

    const close = () => {
        form.reset();
        form.clearErrors();
        onClose();
    };

    return (
        <AdminModal
            open={open}
            title="Create internal user"
            subtitle="Patient and admin accounts only — doctor/pharmacist accounts use the credential flow."
            icon="👤"
            onClose={close}
            footer={
                <>
                    <button type="button" className="btn btn-quiet" onClick={close}>Cancel</button>
                    <button
                        type="submit"
                        form="create-user-form"
                        className="btn btn-primary"
                        disabled={form.processing}
                    >
                        {form.processing ? 'Creating…' : 'Create account'}
                    </button>
                </>
            }
        >
            <form
                id="create-user-form"
                className="stack-sm"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/admin/users', {
                        preserveScroll: true,
                        onSuccess: () => close(),
                    });
                }}
            >
                <div className="field">
                    <label className="label req" htmlFor="cu-name">Full name</label>
                    <input id="cu-name" className="input" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} aria-invalid={Boolean(form.errors.name)} />
                    <FieldError message={form.errors.name} />
                </div>
                <div className="field">
                    <label className="label req" htmlFor="cu-email">Email</label>
                    <input id="cu-email" className="input" type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} aria-invalid={Boolean(form.errors.email)} />
                    <FieldError message={form.errors.email} />
                </div>
                <div className="field">
                    <label className="label" htmlFor="cu-phone">Phone</label>
                    <input id="cu-phone" className="input" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} />
                    <FieldError message={form.errors.phone} />
                </div>
                <div className="field">
                    <label className="label req" htmlFor="cu-role">Role</label>
                    <select id="cu-role" className="select" value={form.data.role} onChange={(event) => form.setData('role', event.target.value)}>
                        {Object.entries(roles)
                            .filter(([value]) => ['patient', 'admin'].includes(value))
                            .map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </select>
                    <FieldError message={form.errors.role} />
                </div>
                <div className="field">
                    <label className="label req" htmlFor="cu-password">Temporary password</label>
                    <input id="cu-password" className="input" type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} aria-invalid={Boolean(form.errors.password)} />
                    <span className="hint">Min 10 chars, mixed case, number and symbol.</span>
                    <FieldError message={form.errors.password} />
                </div>
                <div className="field">
                    <label className="label req" htmlFor="cu-password2">Confirm password</label>
                    <input id="cu-password2" className="input" type="password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} />
                </div>
            </form>
        </AdminModal>
    );
}

function EditUserModal({ user, onClose }: { user: AdminUserRow | null; onClose: () => void }) {
    const form = useForm({ name: '', email: '', phone: '' });

    useEffect(() => {
        if (user) {
            form.setData({ name: user.name, email: user.email, phone: user.phone ?? '' });
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [user]);

    return (
        <AdminModal
            open={Boolean(user)}
            title={`Edit contact — ${user?.name ?? ''}`}
            icon="✏️"
            onClose={onClose}
            footer={
                <>
                    <button type="button" className="btn btn-quiet" onClick={onClose}>Cancel</button>
                    <button
                        type="submit"
                        form="edit-user-form"
                        className="btn btn-primary"
                        disabled={form.processing}
                    >
                        {form.processing ? 'Saving…' : 'Save changes'}
                    </button>
                </>
            }
        >
            <form
                id="edit-user-form"
                className="stack-sm"
                onSubmit={(event) => {
                    event.preventDefault();
                    if (!user) return;
                    form.put(`/admin/users/${user.id}`, { preserveScroll: true, onSuccess: onClose });
                }}
            >
                <div className="field">
                    <label className="label req" htmlFor="eu-name">Full name</label>
                    <input id="eu-name" className="input" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                    <FieldError message={form.errors.name} />
                </div>
                <div className="field">
                    <label className="label req" htmlFor="eu-email">Email</label>
                    <input id="eu-email" className="input" type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} />
                    <FieldError message={form.errors.email} />
                </div>
                <div className="field">
                    <label className="label" htmlFor="eu-phone">Phone</label>
                    <input id="eu-phone" className="input" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} />
                    <FieldError message={form.errors.phone} />
                </div>
            </form>
        </AdminModal>
    );
}

function RoleModal({
    user,
    roles,
    specialties,
    onClose,
}: {
    user: AdminUserRow | null;
    roles: Record<string, string>;
    specialties: Array<{ id: number; name: string }>;
    onClose: () => void;
}) {
    const form = useForm({
        role: 'patient',
        reason: '',
        license_number: '',
        license_authority: '',
        specialty_id: '',
        experience_years: '',
        fee: '',
    });

    useEffect(() => {
        if (user) {
            form.setData({
                role: user.role === 'patient' ? 'doctor' : 'patient',
                reason: '',
                license_number: '',
                license_authority: '',
                specialty_id: '',
                experience_years: '',
                fee: '',
            });
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [user]);

    const needsLicense = form.data.role === 'doctor' || form.data.role === 'pharmacist';
    const isDoctor = form.data.role === 'doctor';

    return (
        <AdminModal
            open={Boolean(user)}
            title={`Change role — ${user?.name ?? ''}`}
            subtitle="Old role profile is archived for records; the workspace route flips immediately."
            icon="🔀"
            onClose={onClose}
            footer={
                <>
                    <button type="button" className="btn btn-quiet" onClick={onClose}>Cancel</button>
                    <button type="submit" form="role-form" className="btn btn-primary" disabled={form.processing}>
                        {form.processing ? 'Applying…' : 'Apply role change'}
                    </button>
                </>
            }
        >
            <form
                id="role-form"
                className="stack-sm"
                onSubmit={(event) => {
                    event.preventDefault();
                    if (!user) return;
                    form.post(`/admin/users/${user.id}/role`, { preserveScroll: true, onSuccess: onClose });
                }}
            >
                <div className="field">
                    <label className="label req" htmlFor="role-new">New role</label>
                    <select id="role-new" className="select" value={form.data.role} onChange={(event) => form.setData('role', event.target.value)}>
                        {Object.entries(roles).filter(([value]) => value !== user?.role).map(([value, label]) => (
                            <option key={value} value={value}>{label}</option>
                        ))}
                    </select>
                    <FieldError message={form.errors.role} />
                </div>

                {needsLicense ? (
                    <>
                        <div className="field">
                            <label className="label req" htmlFor="role-license">License number</label>
                            <input id="role-license" className="input mono" value={form.data.license_number} onChange={(event) => form.setData('license_number', event.target.value)} />
                            <FieldError message={form.errors.license_number} />
                        </div>
                        <div className="field">
                            <label className="label req" htmlFor="role-authority">License authority</label>
                            <input id="role-authority" className="input" value={form.data.license_authority} onChange={(event) => form.setData('license_authority', event.target.value)} />
                            <FieldError message={form.errors.license_authority} />
                        </div>
                    </>
                ) : null}

                {isDoctor ? (
                    <>
                        <div className="field">
                            <label className="label req" htmlFor="role-specialty">Specialty</label>
                            <select id="role-specialty" className="select" value={form.data.specialty_id} onChange={(event) => form.setData('specialty_id', event.target.value)}>
                                <option value="">Choose specialty</option>
                                {specialties.map((specialty) => <option key={specialty.id} value={specialty.id}>{specialty.name}</option>)}
                            </select>
                            <FieldError message={form.errors.specialty_id} />
                        </div>
                        <div className="grid">
                            <div className="c-6 field">
                                <label className="label" htmlFor="role-exp">Experience (years)</label>
                                <input id="role-exp" className="input" type="number" min={0} max={70} value={form.data.experience_years} onChange={(event) => form.setData('experience_years', event.target.value)} />
                            </div>
                            <div className="c-6 field">
                                <label className="label" htmlFor="role-fee">Consultation fee</label>
                                <input id="role-fee" className="input" type="number" min={0} step="0.01" value={form.data.fee} onChange={(event) => form.setData('fee', event.target.value)} />
                            </div>
                        </div>
                    </>
                ) : null}

                <div className="alert alert-warning">
                    <span className="a-ico" aria-hidden="true">⚖</span>
                    <span>Role change is <strong>atomic</strong>: old profile archived, sessions revoked, notification + immutable activity log in one transaction.</span>
                </div>

                <div className="field">
                    <label className="label req" htmlFor="role-reason">Reason (shown to the user)</label>
                    <textarea id="role-reason" className="textarea" rows={3} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} />
                    <FieldError message={form.errors.reason} />
                </div>
            </form>
        </AdminModal>
    );
}

function SuspendModal({ user, onClose }: { user: AdminUserRow | null; onClose: () => void }) {
    const form = useForm({ reason: '' });

    useEffect(() => {
        if (user) {
            form.reset();
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [user]);

    return (
        <AdminModal
            open={Boolean(user)}
            title={`Suspend ${user?.name ?? 'account'}?`}
            subtitle="All Redis sessions are revoked and the reason is shown on the sign-in screen."
            icon="⛔"
            danger
            onClose={onClose}
            footer={
                <>
                    <button type="button" className="btn btn-quiet" onClick={onClose}>Cancel</button>
                    <button type="submit" form="suspend-form" className="btn btn-danger" disabled={form.processing}>
                        {form.processing ? 'Suspending…' : 'Suspend account'}
                    </button>
                </>
            }
        >
            <form
                id="suspend-form"
                className="stack-sm"
                onSubmit={(event) => {
                    event.preventDefault();
                    if (!user) return;
                    form.post(`/admin/users/${user.id}/suspend`, { preserveScroll: true, onSuccess: onClose });
                }}
            >
                <div className="alert alert-danger">
                    <span className="a-ico" aria-hidden="true">⚠</span>
                    <span>The account cannot sign in until restored. Linked patient/doctor records are not deleted.</span>
                </div>
                <div className="field">
                    <label className="label req" htmlFor="suspend-reason">Suspension reason</label>
                    <textarea id="suspend-reason" className="textarea" rows={3} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} />
                    <FieldError message={form.errors.reason} />
                </div>
            </form>
        </AdminModal>
    );
}

function ArchiveUserModal({ user, onClose }: { user: AdminUserRow | null; onClose: () => void }) {
    const [typed, setTyped] = useState('');
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (user) {
            setTyped('');
            setProcessing(false);
        }
    }, [user]);

    const matches = Boolean(user) && typed === user?.name;

    return (
        <AdminModal
            open={Boolean(user)}
            title={`Archive ${user?.name ?? 'account'}?`}
            subtitle="Soft delete only. Linked clinical records remain readable forever."
            icon="🗄️"
            danger
            onClose={onClose}
            footer={
                <>
                    <button type="button" className="btn btn-quiet" onClick={onClose}>Cancel</button>
                    <button
                        type="button"
                        className="btn btn-danger"
                        disabled={!matches || processing}
                        onClick={() => {
                            if (!user || !matches) return;
                            setProcessing(true);
                            router.post(`/admin/users/${user.id}/archive`, { confirm_name: typed }, {
                                preserveScroll: true,
                                onSuccess: onClose,
                                onFinish: () => setProcessing(false),
                            });
                        }}
                    >
                        {processing ? 'Archiving…' : 'Archive account'}
                    </button>
                </>
            }
        >
            <p style={{ fontSize: 14 }}>
                Archiving hides the account from sign-in and the directory. No hard-delete path exists; an
                administrator can restore it later, also logged.
            </p>
            <div className="field">
                <label className="label req" htmlFor="archive-user-name">Type “{user?.name}” to confirm</label>
                <input
                    id="archive-user-name"
                    className={cn('input mono', typed !== '' && !matches && 'is-invalid')}
                    value={typed}
                    onChange={(event) => setTyped(event.target.value)}
                    autoComplete="off"
                />
            </div>
        </AdminModal>
    );
}