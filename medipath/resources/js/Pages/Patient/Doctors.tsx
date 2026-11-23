/**
 * Patient · Find doctors — authenticated reuse of the verified directory with
 * a direct booking link and patient-aware CTA.
 */
import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import type { DirectoryFilters, DoctorSummary, PageMeta, SharedProps } from '@/types';
import type { DataState } from '@/types';
import { RoleLayout } from '@/Layouts/RoleLayout';
import { DataView } from '@/Components/Data/DataView';
import { DoctorCard } from '@/Components/Data/DoctorCard';
import { FilterBar, type FilterField, type FilterTag } from '@/Components/Data/FilterBar';
import { Pager } from '@/Components/Data/DataTable';
import { useDebouncedValue } from '@/hooks/useUi';

interface DoctorsProps {
    doctors: DoctorSummary[];
    meta: PageMeta;
    specialties: Array<{ id: number; name: string; slug: string }>;
    filters: DirectoryFilters;
    tags: FilterTag[];
    dataState: DataState;
}

export default function PatientDoctors({ doctors, meta, specialties, filters, tags, dataState }: DoctorsProps) {
    const { settings } = usePage<SharedProps>().props;
    const [state, setState] = useState<DataState>(dataState);
    const [search, setSearch] = useState(filters.q);
    const debounced = useDebouncedValue(search, 350);

    const apply = (partial: Record<string, string | number | boolean | string[]> = {}, page = 1) => {
        const next: Record<string, string | number | boolean | string[]> = {
            q: filters.q,
            specialty: filters.specialty,
            min_rating: filters.min_rating,
            today: filters.available_today ? '1' : '',
            sort: filters.sort,
            ...partial,
        };
        if (page > 1) next.page = page;
        Object.keys(next).forEach((key) => {
            const value = next[key];
            if (value === '' || value === false || value === 0 || (Array.isArray(value) && value.length === 0) || (key === 'page' && value === 1)) delete next[key];
        });
        router.get('/patient/doctors', next, { preserveState: true, preserveScroll: true, replace: true });
    };

    useEffect(() => { if (debounced !== filters.q) apply({ q: debounced }); /* eslint-disable-next-line */ }, [debounced]);

    const fields: FilterField[] = useMemo(() => [
        { key: 'specialty', label: 'Specialty', type: 'select', placeholder: 'All specialties', options: specialties.map((specialty) => ({ value: specialty.slug, label: specialty.name })) },
        { key: 'min_rating', label: 'Minimum rating', type: 'range', min: 0, max: 5, step: 0.5, unit: '★' },
        { key: 'available_today', label: 'Available today', type: 'toggle' },
        {
            key: 'sort',
            label: 'Sort by',
            type: 'sort',
            options: [
                { value: 'next_available', label: 'Next available' },
                { value: 'rating', label: 'Highest rated' },
                { value: 'experience', label: 'Most experienced' },
                { value: 'fee', label: 'Lowest fee' },
            ],
        },
    ], [specialties]);

    const values = {
        specialty: filters.specialty,
        min_rating: filters.min_rating,
        available_today: filters.available_today,
        sort: filters.sort,
    };

    return (
        <RoleLayout role="patient" title="Find doctors" subtitle="verified profiles only · booking opens in your portal" activeHref="/patient/doctors">
            <Head title="Find a doctor — MediPath" />

            <FilterBar
                searchValue={search}
                onSearchChange={setSearch}
                searchPlaceholder="Name, specialty, condition, clinic…"
                fields={fields}
                values={values}
                onChange={(key, value) => {
                    if (key === 'available_today') { apply({ today: value ? '1' : '' }); return; }
                    apply({ [key]: value as string | number | boolean | string[] });
                }}
                onClear={() => { setSearch(''); router.get('/patient/doctors', {}, { preserveState: true, preserveScroll: true, replace: true }); }}
                tags={tags}
                onRemoveTag={(key) => apply({ [key]: key === 'q' ? '' : key === 'specialty' ? '' : key === 'min_rating' ? 0 : key === 'today' ? '' : '' })}
                resultSummary={<><strong className="tabular">{meta.total}</strong> verified doctor{meta.total === 1 ? '' : 's'} · page <span className="tabular">{meta.current_page}</span>/{Math.max(1, meta.last_page)}</>}
            />

            <DataView
                name="Data view · Doctor directory"
                state={state}
                onStateChange={setState}
                showSwitch
                onRetry={() => router.reload()}
                lastKnownAt={new Date().toISOString()}
                emptyTitle="No doctors match"
                emptyBody="Try widening the specialty, rating or availability filter."
                errorMessage="The directory could not load. Nothing was booked or changed."
            >
                <h2 className="sr-only">Verified doctors</h2>
                <div className="doctor-grid">
                    {doctors.map((doctor) => (
                        <DoctorCard
                            key={doctor.id}
                            doctor={doctor}
                            cta="patient"
                            bookHref={`/patient/book/${doctor.doctorCode}`}
                            profileHref={`/doctors/${doctor.doctorCode}`}
                        />
                    ))}
                </div>
                <div className="grid">
                    <div className="c-12">
                        <Pager page={meta} onPageChange={(page) => apply({}, page)} />
                    </div>
                </div>
            </DataView>

            <p className="caption mt-m">Consultation fees are configured by the clinic; the symbol comes from the global currency setting ({settings.currencyCode}).</p>
        </RoleLayout>
    );
}