/**
 * Public doctor directory — prototypes/guest/02-directory.html.
 * URL-synced filters (§5), weekday availability, doctor grid (§7) and the four
 * data states (§0/§17). All filtering/pagination is server-side.
 */
import { Head, router, usePage } from '@inertiajs/react';
import type { FormDataConvertible } from '@inertiajs/core';
import { useEffect, useMemo, useState } from 'react';
import type { DirectoryFilters, DoctorSummary, PageMeta, SharedProps } from '@/types';
import { GuestLayout } from '@/Layouts/GuestLayout';
import { DataView } from '@/Components/Data/DataView';
import { DoctorCard } from '@/Components/Data/DoctorCard';
import { FilterBar, type FilterField, type FilterTag } from '@/Components/Data/FilterBar';
import { Pager } from '@/Components/Data/DataTable';
import { useDebouncedValue } from '@/hooks/useUi';

interface DirectoryProps {
    doctors: DoctorSummary[];
    meta: PageMeta;
    specialties: Array<{ id: number; name: string; slug: string }>;
    filters: DirectoryFilters;
    tags: FilterTag[];
    dataState: 'success' | 'skeleton' | 'empty' | 'error';
    weekdayOptions: Array<{ value: number; label: string }>;
}

export default function Directory({ doctors, meta, specialties, filters, tags, dataState, weekdayOptions }: DirectoryProps) {
    const { auth } = usePage<SharedProps>().props;
    const [search, setSearch] = useState(filters.q);
    const debouncedSearch = useDebouncedValue(search, 350);

    const apply = (partial: Partial<Record<string, FormDataConvertible>> = {}, page = 1) => {
        const next = {
            q: filters.q,
            specialty: filters.specialty,
            min_rating: filters.min_rating,
            today: filters.available_today ? '1' : '',
            weekdays: filters.weekdays.map(String),
            sort: filters.sort,
            date: filters.date ?? '',
            ...partial,
            page,
        } as Record<string, FormDataConvertible>;

        Object.keys(next).forEach((key) => {
            const value = next[key];
            if (value === '' || value === null || value === false || value === 0 || (Array.isArray(value) && value.length === 0) || (key === 'page' && value === 1)) {
                delete next[key];
            }
        });

        router.get('/doctors', next, { preserveState: true, preserveScroll: true, replace: true });
    };

    useEffect(() => {
        if (debouncedSearch !== filters.q) {
            apply({ q: debouncedSearch });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [debouncedSearch]);

    const fields: FilterField[] = useMemo(
        () => [
            {
                key: 'specialty',
                label: 'Specialty',
                type: 'select',
                placeholder: 'All specialties',
                options: specialties.map((specialty) => ({ value: specialty.slug, label: specialty.name })),
            },
            { key: 'min_rating', label: 'Minimum rating', type: 'range', min: 0, max: 5, step: 0.5, unit: '★' },
            { key: 'available_today', label: 'Available today', type: 'toggle' },
            { key: 'weekdays', label: 'Available on', type: 'weekday' },
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
        ],
        [specialties],
    );

    const values: Record<string, unknown> = {
        specialty: filters.specialty,
        min_rating: filters.min_rating,
        available_today: filters.available_today,
        weekdays: filters.weekdays.map(String),
        sort: filters.sort,
    };

    const clearAll = () => {
        setSearch('');
        router.get('/doctors', {}, { preserveState: true, preserveScroll: true, replace: true });
    };

    const removeTag = (key: string) => {
        const resets: Record<string, FormDataConvertible> = {
            q: '',
            specialty: '',
            min_rating: 0,
            today: '',
            weekdays: [],
            date: '',
        };
        if (key === 'q') setSearch('');
        apply(resets[key] !== undefined ? { [key]: resets[key] } : {});
    };

    return (
        <GuestLayout title="Find a doctor" activeHref="/doctors">
            <Head title="Find a verified doctor — MediPath">
                <meta name="description" content="Search verified doctors by specialty, availability, rating and fee." />
            </Head>

            <div className="stack">
                <div className="directory-head">
                    <div>
                        <h1>Find a doctor</h1>
                        <p className="muted" style={{ fontSize: 14 }}>
                            Showing only verified profiles. Results and filters are server-side and URL-synced.
                        </p>
                    </div>
                    <a className="cta-emergency hide-sm" href="/emergency">🚨 Emergency</a>
                </div>

                <FilterBar
                    searchValue={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Name, specialty, condition… (debounced 300ms)"
                    fields={fields}
                    values={values}
                    onChange={(key, value) => {
                        if (key === 'available_today') {
                            apply({ today: value ? '1' : '' });
                            return;
                        }
                        apply({ [key]: value as FormDataConvertible });
                    }}
                    onClear={clearAll}
                    tags={tags}
                    onRemoveTag={removeTag}
                    resultSummary={
                        <>
                            <strong className="tabular">{meta.total}</strong> verified doctor{meta.total === 1 ? '' : 's'} · page{' '}
                            <span className="tabular">{meta.current_page}</span>/{Math.max(1, meta.last_page)}
                        </>
                    }
                />

                {filters.date ? (
                    <p className="caption" role="status">
                        Preferred date: {filters.date} — showing doctors with open slots on that date (or the same weekday when further ahead).
                    </p>
                ) : null}

                <section className="card card-pad weekday-panel" aria-labelledby="wk-title">
                    <div>
                        <h2 className="h4" id="wk-title">Availability by weekday</h2>
                        <p className="caption">Computed server-side from schedules − approved leave − active bookings.</p>
                    </div>
                    <div className="weekdays" role="group" aria-label="Filter by available weekday">
                        {weekdayOptions.map((day) => (
                            <label key={day.value}>
                                <input
                                    type="checkbox"
                                    checked={filters.weekdays.includes(day.value)}
                                    onChange={(event) => {
                                        const next = event.target.checked
                                            ? [...filters.weekdays, day.value]
                                            : filters.weekdays.filter((value) => value !== day.value);
                                        apply({ weekdays: next.map(String) });
                                    }}
                                />
                                <span>{day.label}</span>
                            </label>
                        ))}
                    </div>
                </section>

                <DataView
                    name="Data view · Doctor search results"
                    state={dataState}
                    onRetry={() => router.reload()}
                    lastKnownAt={new Date(Date.now() - 8 * 60 * 1000).toISOString()}
                    emptyTitle="No doctors match these filters"
                    emptyBody="Try widening the rating, removing “Available today”, or choosing a related specialty. Nothing has been deleted."
                    emptyAction={
                        <div className="mp-row">
                            <button type="button" className="btn btn-primary" onClick={clearAll}>Clear all filters</button>
                            <a className="btn btn-outline" href="/">Back to home</a>
                        </div>
                    }
                    skeleton={
                        <div className="doctor-grid" aria-hidden="true">
                            {Array.from({ length: 6 }).map((_, index) => (
                                <DoctorCard
                                    key={index}
                                    doctor={{
                                        id: index,
                                        name: 'Loading',
                                        initials: 'MP',
                                        specialty: '—',
                                        verified: false,
                                        experienceYears: 0,
                                        rating: 0,
                                        reviewCount: 0,
                                        feeFormatted: '—',
                                        hasSlots: false,
                                    }}
                                    state="skeleton"
                                />
                            ))}
                        </div>
                    }
                >
                    <div className="doctor-grid">
                        {doctors.map((doctor) => (
                            <DoctorCard
                                key={doctor.id}
                                doctor={doctor}
                                cta={auth.user?.role === 'patient' ? 'patient' : 'guest'}
                                profileHref={doctor.profileUrl ?? `/doctors/${doctor.id}`}
                                bookHref={
                                    auth.user?.role === 'patient'
                                        ? '/patient/dashboard'
                                        : `/login?redirect=${encodeURIComponent(doctor.profileUrl ?? '/doctors')}`
                                }
                            />
                        ))}
                    </div>
                </DataView>

                {meta.last_page > 1 ? (
                    <Pager page={meta} onPageChange={(page) => apply({}, page)} />
                ) : null}
            </div>
        </GuestLayout>
    );
}