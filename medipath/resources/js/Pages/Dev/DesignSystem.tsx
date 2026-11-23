/**
 * Design-system gallery — local-only QA surface for Part 0.
 *
 * Every component below cites its COMPONENT-SPEC § in its own file header.
 * Each data-bound component exposes all four states (skeleton / empty /
 * error+Retry / success) through its State= prop and the ?state= URL param.
 * Prototype references are noted per section.
 */
import { useMemo, useState } from 'react';
import { usePage } from '@inertiajs/react';
import type { DataState, DoctorSummary, EmergencyContactItem, SharedProps, Slot } from '@/types';
import { GuestLayout } from '@/Layouts/GuestLayout';
import { DataView } from '@/Components/Data/DataView';
import { KpiCard } from '@/Components/Data/KpiCard';
import { DataTable, type DataColumn } from '@/Components/Data/DataTable';
import { FilterBar, type FilterField } from '@/Components/Data/FilterBar';
import { DoctorCard } from '@/Components/Data/DoctorCard';
import { SlotPicker } from '@/Components/Data/SlotPicker';
import { RatingSummary, StarRatingInput, Stars } from '@/Components/Data/StarRating';
import { StatusBadge } from '@/Components/Data/StatusBadge';
import { PdfButton } from '@/Components/Data/PdfButton';
import { ConfirmModal } from '@/Components/Overlay/ConfirmModal';
import { Stepper, WorkflowTracker, defaultWorkflow } from '@/Components/Workflow/Stepper';
import { NotificationBell } from '@/Components/Feedback/NotificationBell';
import { InlineError, Spinner, UnsavedBar } from '@/Components/Feedback/FeedbackKit';
import { RetryBanner } from '@/Components/Feedback/RetryBanner';
import { EmergencyKit } from '@/Components/Safety/EmergencyBlocks';
import { ChatbotWidget, type ChatTransport } from '@/Components/AI/ChatbotWidget';
import { useToast } from '@/providers/ToastProvider';
import { Money } from '@/Components/ui/Money';

interface GalleryProps {
    tokens: {
        currencySymbol: string;
        formattedFee: string;
        formattedCompact: string;
        formattedNegative: string;
    };
    sampleDoctor: DoctorSummary | null;
    sampleSlots: Slot[];
    sampleDates: Array<{ date: string; day: string; num: string; isToday: boolean; isOff: boolean }>;
    notifications: Array<{
        id: number;
        title: string;
        message: string;
        category: string;
        readAt: string | null;
        createdAt: string | null;
    }>;
    emergencyContacts: EmergencyContactItem[];
    emergencyRules: Array<{
        category: string;
        items: Array<{ id: number; title: string; directive: string }>;
    }>;
}

const SAMPLE_ROWS = [
    { id: 'APT-3391', patient: 'Farhana Karim', doctor: 'Dr. Ayesha Rahman', date: '24 Nov 2026', status: 'accepted' },
    { id: 'APT-3392', patient: 'Rafiq Ahmed', doctor: 'Dr. Kamal Hossain', date: '24 Nov 2026', status: 'pending' },
    { id: 'APT-3393', patient: 'Shila Das', doctor: 'Dr. Nusrat Jahan', date: '25 Nov 2026', status: 'completed' },
    { id: 'APT-3394', patient: 'Imran Chowdhury', doctor: 'Dr. Ayesha Rahman', date: '26 Nov 2026', status: 'cancelled' },
    { id: 'APT-3395', patient: 'Tanvir Hasan', doctor: 'Dr. Kamal Hossain', date: '27 Nov 2026', status: 'rejected' },
];

const FILTER_FIELDS: FilterField[] = [
    {
        key: 'specialty',
        label: 'Specialty',
        type: 'select',
        placeholder: 'Any specialty',
        options: [
            { value: 'cardiology', label: 'Cardiology' },
            { value: 'pediatrics', label: 'Pediatrics' },
            { value: 'dermatology', label: 'Dermatology' },
        ],
    },
    {
        key: 'sort',
        label: 'Sort',
        type: 'sort',
        options: [
            { value: 'rating', label: 'Highest rated' },
            { value: 'fee', label: 'Lowest fee' },
            { value: 'experience', label: 'Most experienced' },
        ],
    },
    { key: 'availableToday', label: 'Available today', type: 'toggle' },
    { key: 'available', label: 'Available on', type: 'weekday' },
];

export default function DesignSystem({
    tokens,
    sampleDoctor,
    sampleSlots,
    sampleDates,
    notifications,
    emergencyContacts,
    emergencyRules,
}: GalleryProps) {
    const { settings, ui } = usePage<SharedProps>().props;
    const initial = ui.initialState ?? 'success';
    const [states, setStates] = useState<Record<string, DataState>>({});
    const stateOf = (key: string) => states[key] ?? initial;
    const setState = (key: string) => (next: DataState) => setStates((current) => ({ ...current, [key]: next }));

    const [slotDate, setSlotDate] = useState(sampleDates[0]?.date);
    const [slotTime, setSlotTime] = useState('10:30');
    const [conflict, setConflict] = useState<string | null>(null);
    const [rating, setRating] = useState(4);
    const [ratingError, setRatingError] = useState<string | null>(null);
    const [filterValues, setFilterValues] = useState<Record<string, unknown>>({
        specialty: '',
        sort: '',
        availableToday: false,
        available: [],
    });
    const [search, setSearch] = useState('');
    const [sort, setSort] = useState<{ key: string; direction: 'asc' | 'desc' } | null>({ key: 'date', direction: 'asc' });
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState<'typed' | 'reason' | 'emergency' | null>(null);
    const [modalLoading, setModalLoading] = useState(false);
    const [pdfState, setPdfState] = useState<'idle' | 'loading' | 'ready' | 'disabled'>('idle');
    const [offlineChat, setOfflineChat] = useState(false);
    const { push } = useToast();

    const columns: Array<DataColumn<(typeof SAMPLE_ROWS)[number]>> = useMemo(
        () => [
            {
                key: 'id',
                label: 'Appointment',
                sortable: true,
                render: (row) => <span className="mono">{row.id}</span>,
            },
            {
                key: 'patient',
                label: 'Patient',
                sortable: true,
                render: (row) => (
                    <span>
                        <span className="cell-main">{row.patient}</span>
                        <span className="cell-sub" style={{ display: 'block' }}>{row.doctor}</span>
                    </span>
                ),
            },
            {
                key: 'date',
                label: 'Date',
                sortable: true,
                render: (row) => <span className="tabular">{row.date}</span>,
            },
            {
                key: 'status',
                label: 'Status',
                render: (row) => <StatusBadge family="appointment" status={row.status} />,
            },
            {
                key: 'fee',
                label: 'Fee',
                align: 'right',
                render: () => <Money formatted={tokens.formattedFee} />,
            },
        ],
        [tokens.formattedFee],
    );

    const transport: ChatTransport = async (message) => {
        await new Promise((resolve) => setTimeout(resolve, 700));
        const lower = message.toLowerCase();

        if (lower.includes('chest') || lower.includes('breath')) {
            return {
                text: 'Chest pain with breathlessness can be an emergency.',
                emergency: {
                    title: 'Possible cardiac red flag detected by deterministic rules.',
                    directive: 'Call emergency services now. Do not drive yourself. The AI never triages this further.',
                    contacts: emergencyContacts.length
                        ? emergencyContacts.slice(0, 2)
                        : [
                              { key: 'local', label: 'Local emergency', number: '112', tel: 'tel:112', icon: '🚨' },
                          ],
                    ruleId: 1,
                },
            };
        }

        return {
            text: `Here is what the published FAQ knowledge base says about “${message}”: bookings need a verified doctor, and prescriptions are only dispensed after deterministic allergy and interaction checks.`,
            caption: 'General guidance only — not a diagnosis.',
            source: 'faq',
        };
    };

    const applySort = (key: string) => {
        setSort((current) =>
            current?.key === key
                ? { key, direction: current.direction === 'asc' ? 'desc' : 'asc' }
                : { key, direction: 'asc' },
        );
    };

    return (
        <GuestLayout title="Component gallery">
            <div className="gallery">
                <header className="gallery-head">
                    <h1 className="h1" style={{ margin: 0 }}>Component library</h1>
                    <span className="badge badge-primary">17 components · 4 states each</span>
                    <span className="badge badge-neutral">Local only</span>
                    <span className="caption">Initial state from URL: <code className="mono">?state={initial}</code></span>
                </header>

                <div className="dev-banner" role="note">
                    <strong>QA harness.</strong> The dashed frames are local-only state switchers. Product screens render
                    the same components without the switch. Prototype references: <code className="mono">design-out/prototypes/**</code>.
                </div>

                {/* ---------------------------------------------------------- Tokens */}
                <section className="gallery-section" aria-labelledby="tokens-title">
                    <div className="gs-title">
                        <h2 id="tokens-title">Tokens & type</h2>
                        <span className="badge badge-neutral">tokens.json → tokens.scss → CSS vars</span>
                    </div>
                    <p className="gs-note">
                        Swatches resolve live from <code className="mono">--mp-*</code>. Toggle the theme to verify both
                        modes without a reload.
                    </p>

                    <div className="gallery-swatches">
                        {[
                            ['--mp-primary', 'primary'],
                            ['--mp-ink', 'ink'],
                            ['--mp-bg', 'bg'],
                            ['--mp-surface', 'surface'],
                            ['--mp-border', 'border'],
                            ['--mp-success', 'success'],
                            ['--mp-warning', 'warning'],
                            ['--mp-danger', 'danger'],
                            ['--mp-info', 'info'],
                            ['--mp-emergency', 'emergency (scoped)'],
                        ].map(([token, label]) => (
                            <div className="sw" key={token}>
                                <i style={{ background: `var(${token})` }} />
                                <strong>{label}</strong>
                                <div className="mono" style={{ fontSize: 11 }}>{token}</div>
                            </div>
                        ))}
                    </div>

                    <div className="gallery-grid cols-2">
                        <div className="gallery-item">
                            <span className="gi-label">Type ramp</span>
                            <h1 className="h1">H1 32/40 · page title</h1>
                            <h2 className="h2">H2 24/32 · section</h2>
                            <h3 className="h3">H3 20/28 · card</h3>
                            <p className="lead">Lead 18/28 · assisting paragraph.</p>
                            <p>Body 16/24 · default content.</p>
                            <p className="caption">Caption 13/18 · meta and timestamps.</p>
                        </div>
                        <div className="gallery-item">
                            <span className="gi-label">Money contract (CurrencyFormatter)</span>
                            <dl>
                                <div className="def-row"><dt>Standard</dt><dd><Money formatted={tokens.formattedFee} /></dd></div>
                                <div className="def-row"><dt>Compact</dt><dd><Money formatted={tokens.formattedCompact} /></dd></div>
                                <div className="def-row"><dt>Negative</dt><dd style={{ color: 'var(--mp-danger-text)' }}><Money formatted={tokens.formattedNegative} /></dd></div>
                            </dl>
                            <p className="caption">
                                Symbol comes from <code className="mono">{settings.currencyCode}</code>; no client formatting.
                            </p>
                        </div>
                    </div>
                </section>

                {/* --------------------------------------------------------- Buttons */}
                <section className="gallery-section" aria-labelledby="actions-title">
                    <div className="gs-title">
                        <h2 id="actions-title">Actions</h2>
                        <span className="badge badge-neutral">COMPONENT-SPEC §0 · Buttons</span>
                    </div>

                    <div className="gallery-grid cols-3">
                        <div className="gallery-item">
                            <span className="gi-label">Button variants</span>
                            <div className="mp-row">
                                <button className="btn btn-primary">Primary</button>
                                <button className="btn btn-outline">Outline</button>
                                <button className="btn btn-ghost">Ghost</button>
                            </div>
                            <div className="mp-row">
                                <button className="btn btn-danger">Danger</button>
                                <button className="btn btn-danger-outline">Danger outline</button>
                                <button className="btn btn-quiet">Quiet</button>
                            </div>
                            <div className="mp-row">
                                <button className="btn btn-primary" disabled>Disabled</button>
                                <button className="btn btn-primary">
                                    <Spinner label="Loading action" /> Saving…
                                </button>
                            </div>
                        </div>

                        <div className="gallery-item">
                            <span className="gi-label">Chips, tags, weekdays (§5)</span>
                            <div className="mp-row">
                                <span className="chip chip-select">Cardiology</span>
                                <span className="chip chip-select is-on">Today</span>
                                <span className="tag-filter">
                                    Rating: 4.5+
                                    <button type="button" aria-label="Remove rating filter">✕</button>
                                </span>
                            </div>
                            <div className="weekdays">
                                {['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((day, index) => (
                                    <label key={day}>
                                        <input type="checkbox" defaultChecked={index < 3} />
                                        <span>{day}</span>
                                    </label>
                                ))}
                            </div>
                        </div>

                        <div className="gallery-item">
                            <span className="gi-label">Status badges (§6)</span>
                            <div className="mp-row">
                                <StatusBadge family="appointment" status="pending" />
                                <StatusBadge family="appointment" status="accepted" />
                                <StatusBadge family="appointment" status="rejected" />
                                <StatusBadge family="appointment" status="completed" />
                                <StatusBadge family="prescription" status="pending_dispense" />
                                <StatusBadge family="payment" status="paid" />
                                <StatusBadge family="stock" status="low" />
                                <StatusBadge family="stock" status="out_of_stock" />
                                <StatusBadge family="expiry" status="expired" />
                                <StatusBadge family="allergy" status="life_threatening" />
                                <StatusBadge family="clinical" status="emergency" />
                            </div>
                            <div className="mp-row">
                                <StatusBadge family="appointment" status="pending" variant="solid" />
                                <StatusBadge family="appointment" status="accepted" variant="outline" />
                                <StatusBadge family="appointment" status="pending" state="skeleton" />
                                <StatusBadge family="appointment" status="pending" state="empty" />
                                <StatusBadge family="appointment" status="pending" state="error" />
                            </div>
                        </div>
                    </div>
                </section>

                {/* ------------------------------------------------------------- KPI */}
                <section className="gallery-section" aria-labelledby="kpi-title">
                    <div className="gs-title">
                        <h2 id="kpi-title">KPI cards</h2>
                        <span className="badge badge-neutral">COMPONENT-SPEC §3</span>
                        <span className="caption">prototypes/admin/01-dashboard</span>
                    </div>

                    <DataView
                        name="Data view · Platform KPIs"
                        state={stateOf('kpi')}
                        onStateChange={setState('kpi')}
                        showSwitch
                        onRetry={() => push({ tone: 'info', message: 'KPI retry dispatched (demo).' })}
                        lastKnownAt={new Date(Date.now() - 90_000).toISOString()}
                        emptyTitle="No activity in this range"
                        emptyBody="Expand the range or wait for the first record."
                        skeleton={
                            <div className="grid" aria-hidden="true">
                                {[0, 1, 2, 3].map((item) => (
                                    <div className="c-6 md-c-3" key={item}>
                                        <KpiCard label="Loading KPI" state="skeleton" />
                                    </div>
                                ))}
                            </div>
                        }
                    >
                        <div className="grid">
                            <div className="c-6 md-c-3">
                                <KpiCard label="Users" value="1,247" delta="+68 (30d)" trend="up" sparkline={[4, 6, 5, 8, 9, 12]} />
                            </div>
                            <div className="c-6 md-c-3">
                                <KpiCard label="Doctors awaiting action" value="7" tone="warning" delta="4 verifications · 3 approvals" trend="down" />
                            </div>
                            <div className="c-6 md-c-3">
                                <KpiCard label="Revenue collected" money={tokens.formattedCompact} delta="▲ 9%" trend="up" />
                            </div>
                            <div className="c-6 md-c-3">
                                <KpiCard label="Negative total" money={tokens.formattedNegative} tone="danger" delta="refund posted" trend="down" />
                            </div>
                        </div>
                    </DataView>
                </section>

                {/* ----------------------------------------------------------- Table */}
                <section className="gallery-section" aria-labelledby="table-title">
                    <div className="gs-title">
                        <h2 id="table-title">DataTable → cards &lt;640</h2>
                        <span className="badge badge-neutral">COMPONENT-SPEC §4</span>
                        <span className="caption">resize to 360 to see stacked cards with data-label headers</span>
                    </div>

                    <DataView
                        name="Data view · Appointments"
                        state={stateOf('table')}
                        onStateChange={setState('table')}
                        showSwitch
                        onRetry={() => push({ tone: 'info', message: 'Table retry dispatched (demo).' })}
                        lastKnownAt={new Date(Date.now() - 15 * 60 * 1000).toISOString()}
                    >
                        <DataTable
                            title="All appointments"
                            caption="Appointment list with status and consultation fee"
                            columns={columns}
                            rows={SAMPLE_ROWS}
                            getRowKey={(row) => row.id}
                            sort={sort}
                            onSort={applySort}
                            page={{
                                current_page: page,
                                last_page: 4,
                                per_page: 15,
                                total: 53,
                                from: (page - 1) * 15 + 1,
                                to: Math.min(page * 15, 53),
                            }}
                            onPageChange={setPage}
                            actions={() => (
                                <>
                                    <button type="button" className="btn btn-outline btn-sm">View</button>
                                    <button type="button" className="btn btn-ghost btn-sm">Cancel</button>
                                </>
                            )}
                            emptyAction={<button type="button" className="btn btn-outline" onClick={() => push({ tone: 'info', message: 'Filters reset (demo).' })}>Reset filters</button>}
                        />
                    </DataView>
                </section>

                {/* ---------------------------------------------------------- Filter */}
                <section className="gallery-section" aria-labelledby="filter-title">
                    <div className="gs-title">
                        <h2 id="filter-title">FilterBar / FilterDrawer</h2>
                        <span className="badge badge-neutral">COMPONENT-SPEC §5</span>
                        <span className="caption">prototypes/guest/02-directory · &lt;768 opens a right drawer</span>
                    </div>

                    <FilterBar
                        searchValue={search}
                        onSearchChange={setSearch}
                        fields={FILTER_FIELDS}
                        values={filterValues}
                        onChange={(key, value) => setFilterValues((current) => ({ ...current, [key]: value }))}
                        onClear={() => {
                            setSearch('');
                            setFilterValues({ specialty: '', sort: '', availableToday: false, available: [] });
                        }}
                        tags={[
                            ...(search ? [{ key: 'search', label: 'Search', valueLabel: search }] : []),
                            ...(filterValues.specialty ? [{ key: 'specialty', label: 'Specialty', valueLabel: String(filterValues.specialty) }] : []),
                            ...(filterValues.availableToday ? [{ key: 'availableToday', label: 'Available today' }] : []),
                        ]}
                        onRemoveTag={(key) => {
                            if (key === 'search') setSearch('');
                            else setFilterValues((current) => ({ ...current, [key]: key === 'available' ? [] : key === 'availableToday' ? false : '' }));
                        }}
                        facetState={stateOf('filter') === 'skeleton' ? 'skeleton' : stateOf('filter') === 'error' ? 'error' : 'success'}
                        onFacetRetry={() => setState('filter')('success')}
                        resultSummary="38 verified doctors match"
                        busy={search.length > 0}
                    />

                    <div className="dv-switch" role="group" aria-label="Preview filter facet state" style={{ alignSelf: 'flex-start' }}>
                        {(['success', 'skeleton', 'error'] as DataState[]).map((option) => (
                            <button
                                key={option}
                                type="button"
                                aria-pressed={stateOf('filter') === option}
                                onClick={() => setState('filter')(option)}
                            >
                                Facets: {option}
                            </button>
                        ))}
                    </div>
                </section>

                {/* ------------------------------------------------------- DoctorCard */}
                <section className="gallery-section" aria-labelledby="doctor-title">
                    <div className="gs-title">
                        <h2 id="doctor-title">DoctorCard</h2>
                        <span className="badge badge-neutral">COMPONENT-SPEC §7</span>
                        <span className="caption">doctor grid 1 / 2 / 3 columns at &lt;640 / 640–991 / ≥992</span>
                    </div>

                    <DataView
                        name="Data view · Featured doctors"
                        state={stateOf('doctor')}
                        onStateChange={setState('doctor')}
                        showSwitch
                        onRetry={() => push({ tone: 'info', message: 'Doctor list retry dispatched (demo).' })}
                        lastKnownAt={new Date(Date.now() - 6 * 60 * 1000).toISOString()}
                        emptyTitle="No verified doctors match"
                        emptyBody="Clear a filter or browse all specialties; only admin-verified licenses are published."
                        emptyAction={<button type="button" className="btn btn-outline">Clear filters</button>}
                        skeleton={
                            <div className="doctor-grid" aria-hidden="true">
                                {[0, 1, 2].map((item) => (
                                    <DoctorCard
                                        key={item}
                                        doctor={{
                                            id: item,
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
                        {sampleDoctor ? (
                            <div className="doctor-grid">
                                <DoctorCard doctor={sampleDoctor} cta="guest" profileHref="#" bookHref="#" />
                                <DoctorCard
                                    doctor={{ ...sampleDoctor, name: 'Dr. Kamal Hossain', initials: 'KH', specialty: 'Dermatology', rating: 4.4, reviewCount: 23, hasSlots: false, nextAvailable: null }}
                                    profileHref="#"
                                />
                                <DoctorCard
                                    doctor={{ ...sampleDoctor, name: 'Dr. Nusrat Jahan', initials: 'NJ', specialty: 'Pediatrics', rating: 4.9, reviewCount: 81 }}
                                    cta="patient"
                                    profileHref="#"
                                    state="error"
                                />
                            </div>
                        ) : (
                            <p className="caption">Seed the database to populate doctor cards.</p>
                        )}
                    </DataView>
                </section>

                {/* -------------------------------------------------------- SlotPicker */}
                <section className="gallery-section" aria-labelledby="slot-title">
                    <div className="gs-title">
                        <h2 id="slot-title">SlotPicker + DateStrip</h2>
                        <span className="badge badge-neutral">COMPONENT-SPEC §8</span>
                        <span className="caption">leave-aware kinds: open / selected / booked / past / on-leave / closed</span>
                    </div>

                    <DataView
                        name="Data view · Availability"
                        state={stateOf('slot')}
                        onStateChange={setState('slot')}
                        showSwitch
                        onRetry={() => push({ tone: 'info', message: 'Slot retry dispatched (demo).' })}
                        lastKnownAt={new Date(Date.now() - 2 * 60 * 1000).toISOString()}
                        skeleton={
                            <div className="card card-pad">
                                <SlotPicker
                                    dates={sampleDates}
                                    selectedDate={slotDate}
                                    onSelectDate={setSlotDate}
                                    slots={sampleSlots}
                                    selectedTime={slotTime}
                                    onSelectTime={setSlotTime}
                                    state="skeleton"
                                />
                            </div>
                        }
                    >
                        <div className="card card-pad">
                            <SlotPicker
                                dates={sampleDates}
                                selectedDate={slotDate}
                                onSelectDate={setSlotDate}
                                slots={sampleSlots}
                                selectedTime={slotTime}
                                onSelectTime={setSlotTime}
                                state={stateOf('slot')}
                                onRetry={() => push({ tone: 'info', message: 'Reloading slots (demo).' })}
                                conflict={conflict}
                                onReload={() => {
                                    setConflict(null);
                                    setStates((current) => ({ ...current, slot: 'success' }));
                                }}
                            />
                            <div className="mp-row mt-b">
                                <span className="caption">
                                    Selected: <strong className="tabular">{slotDate} · {slotTime}</strong>
                                </span>
                                <button
                                    type="button"
                                    className="btn btn-outline btn-sm mla"
                                    onClick={() => {
                                        setStates((current) => ({ ...current, slot: current.slot === 'error' ? 'success' : 'error' }));
                                    }}
                                >
                                    Toggle slot-fetch error
                                </button>
                            </div>
                            <div className="mt-b">
                                <button
                                    type="button"
                                    className="btn btn-quiet btn-sm"
                                    onClick={() =>
                                        setConflict(
                                            conflict
                                                ? null
                                                : '09:30 was booked by another patient while you were choosing your reason.',
                                        )
                                    }
                                >
                                    {conflict ? 'Clear slot-taken banner' : 'Simulate slot-taken conflict'}
                                </button>
                            </div>
                        </div>
                    </DataView>
                </section>

                {/* ----------------------------------------------------------- Rating */}
                <section className="gallery-section" aria-labelledby="rating-title">
                    <div className="gs-title">
                        <h2 id="rating-title">StarRating + Distribution</h2>
                        <span className="badge badge-neutral">COMPONENT-SPEC §9</span>
                        <span className="caption">prototypes/guest/03-doctor-profile · patient review input</span>
                    </div>

                    <div className="gallery-grid cols-2">
                        <div className="gallery-item">
                            <span className="gi-label">Read + summary</span>
                            <div className="mp-row">
                                <Stars value={4.7} label="Rated 4.7 out of 5, 32 reviews" size="lg" />
                                <span className="tabular">4.7 <span className="caption">(32)</span></span>
                            </div>
                            <RatingSummary
                                average={4.7}
                                total={32}
                                distribution={{ 5: 22, 4: 6, 3: 2, 2: 1, 1: 1 }}
                                state={stateOf('rating')}
                                onRetry={() => push({ tone: 'info', message: 'Ratings retry (demo).' })}
                            />
                        </div>

                        <div className="gallery-item">
                            <span className="gi-label">Input (required, 44px targets)</span>
                            <StarRatingInput
                                value={rating}
                                onChange={(value) => {
                                    setRating(value);
                                    setRatingError(null);
                                }}
                                error={ratingError}
                            />
                            <button
                                type="button"
                                className="btn btn-primary"
                                onClick={() => {
                                    if (rating < 1) setRatingError('Choose a rating');
                                    else push({ tone: 'success', message: `Review submitted with ${rating} stars (demo).` });
                                }}
                            >
                                Submit review
                            </button>
                            <InlineError id="rating-demo-error" message={ratingError} />
                        </div>
                    </div>

                    <div className="dv-switch" role="group" aria-label="Preview rating summary state" style={{ alignSelf: 'flex-start' }}>
                        {(['success', 'skeleton', 'empty', 'error'] as DataState[]).map((option) => (
                            <button
                                key={option}
                                type="button"
                                aria-pressed={stateOf('rating') === option}
                                onClick={() => setState('rating')(option)}
                            >
                                {option}
                            </button>
                        ))}
                    </div>
                </section>

                {/* ------------------------------------------------------- Bell panel */}
                <section className="gallery-section" aria-labelledby="bell-title">
                    <div className="gs-title">
                        <h2 id="bell-title">NotificationBell + Panel</h2>
                        <span className="badge badge-neutral">COMPONENT-SPEC §10</span>
                        <span className="caption">aria-live polite · popover ≥768, right drawer &lt;768</span>
                    </div>

                    <div className="gallery-grid cols-2">
                        <div className="gallery-item">
                            <span className="gi-label">Live panel (click the bell)</span>
                            <div className="mp-row">
                                <NotificationBell
                                    items={notifications}
                                    unreadCount={notifications.filter((item) => !item.readAt).length}
                                    live={false}
                                    state={stateOf('bell') === 'skeleton' ? 'skeleton' : stateOf('bell') === 'error' ? 'error' : notifications.length ? 'success' : 'empty'}
                                    onRetry={() => push({ tone: 'info', message: 'Notifications retry (demo).' })}
                                    onMarkAllRead={() => push({ tone: 'success', message: 'All notifications marked read (demo).' })}
                                    onItemRead={(id) => push({ tone: 'info', message: `Notification ${id} marked read (demo).` })}
                                    notificationsHref="#"
                                />
                                <span className="caption">Unread count is server-authoritative.</span>
                            </div>
                        </div>

                        <div className="gallery-item">
                            <span className="gi-label">State switch</span>
                            <div className="dv-switch" role="group" aria-label="Preview notification state" style={{ alignSelf: 'flex-start' }}>
                                {(['success', 'skeleton', 'empty', 'error'] as DataState[]).map((option) => (
                                    <button
                                        key={option}
                                        type="button"
                                        aria-pressed={stateOf('bell') === option}
                                        onClick={() => setState('bell')(option)}
                                    >
                                        {option}
                                    </button>
                                ))}
                            </div>
                            <p className="caption">
                                Empty copy: “You’re all caught up ✓”. Error keeps the poll restartable.
                            </p>
                        </div>
                    </div>
                </section>

                {/* -------------------------------------------------------- Emergency */}
                <section className="gallery-section" id="safety" aria-labelledby="emg-title">
                    <div className="gs-title">
                        <h2 id="emg-title">Emergency blocks</h2>
                        <span className="badge badge-emergency">COMPONENT-SPEC §12 · scoped red</span>
                        <span className="caption">prototypes/guest/04-emergency</span>
                    </div>

                    <DataView
                        name="Data view · Emergency contacts & red flags"
                        state={stateOf('emergency')}
                        onStateChange={setState('emergency')}
                        showSwitch
                        onRetry={() => push({ tone: 'info', message: 'Emergency data retry (demo).' })}
                        lastKnownAt={new Date(Date.now() - 60 * 60 * 1000).toISOString()}
                        skeleton={
                            <EmergencyKit
                                contacts={emergencyContacts}
                                groups={emergencyRules}
                                state="skeleton"
                            />
                        }
                    >
                        <EmergencyKit
                            contacts={emergencyContacts}
                            fallbackContacts={emergencyContacts}
                            groups={emergencyRules}
                            banner={{
                                title: 'Clinic emergency banner (time-bounded, admin-managed)',
                                message: 'Dismissal is by expiry only — this alert stays until its window ends.',
                                startsAt: new Date(Date.now() - 60 * 60 * 1000).toISOString(),
                                endsAt: new Date(Date.now() + 6 * 60 * 60 * 1000).toISOString(),
                            }}
                            state={stateOf('emergency')}
                            onRetry={() => push({ tone: 'info', message: 'Reloading emergency details (demo).' })}
                            cachedAt={new Date(Date.now() - 60 * 60 * 1000).toISOString()}
                        />
                    </DataView>
                </section>

                {/* ----------------------------------------------------------- Modals */}
                <section className="gallery-section" aria-labelledby="modal-title">
                    <div className="gs-title">
                        <h2 id="modal-title">ConfirmModal</h2>
                        <span className="badge badge-neutral">COMPONENT-SPEC §13</span>
                        <span className="caption">Click | Typed | ReasonRequired · dependency counts · mobile bottom sheet</span>
                    </div>

                    <div className="mp-row">
                        <button type="button" className="btn btn-outline" onClick={() => setModal('typed')}>
                            Typed archive (danger)
                        </button>
                        <button type="button" className="btn btn-outline" onClick={() => setModal('reason')}>
                            Reject with reason
                        </button>
                        <button type="button" className="btn btn-emergency" onClick={() => setModal('emergency')}>
                            Emergency alertdialog
                        </button>
                    </div>

                    <ConfirmModal
                        open={modal === 'typed'}
                        title="Archive Dr. Ayesha Rahman?"
                        message={
                            <p>
                                Archiving hides the doctor from the directory and blocks new bookings. Historical
                                prescriptions and invoices are preserved.
                            </p>
                        }
                        tone="danger"
                        confirmMode="typed"
                        expectedText="Dr. Ayesha Rahman"
                        confirmLabel="Archive doctor"
                        dependencies={[
                            { label: 'upcoming appointments', count: 4 },
                            { label: 'prescriptions', count: 34 },
                            { label: 'reviews', count: 56 },
                        ]}
                        loading={modalLoading}
                        onClose={() => setModal(null)}
                        onConfirm={() => {
                            setModalLoading(true);
                            setTimeout(() => {
                                setModalLoading(false);
                                setModal(null);
                                push({ tone: 'success', message: 'Doctor archived (demo).' });
                            }, 700);
                        }}
                    />

                    <ConfirmModal
                        open={modal === 'reason'}
                        title="Reject registration request?"
                        message={<p>The applicant sees this reason on their pending-approval screen and cannot access the portal.</p>}
                        tone="danger"
                        confirmMode="reason"
                        reasonLabel="Review reason"
                        reasonPlaceholder="e.g. License number could not be verified with the issuing authority."
                        confirmLabel="Reject request"
                        dependencies={[{ label: 'pending documents', count: 2 }]}
                        onClose={() => setModal(null)}
                        onConfirm={() => {
                            setModal(null);
                            push({ tone: 'success', message: 'Reason recorded (demo).' });
                        }}
                    />

                    <ConfirmModal
                        open={modal === 'emergency'}
                        title="Emergency: possible cardiac event"
                        message={
                            <div className="stack-sm">
                                <p>
                                    A deterministic red-flag rule matched <strong>“chest pain + shortness of breath”</strong>.
                                    The AI has stopped triage. Call emergency services now.
                                </p>
                                <div className="em-calls">
                                    {emergencyContacts.slice(0, 2).map((contact) => (
                                        <a key={contact.key} href={contact.tel} className="btn btn-emergency">
                                            {contact.label}: {contact.number}
                                        </a>
                                    ))}
                                </div>
                            </div>
                        }
                        tone="emergency"
                        confirmMode="click"
                        confirmLabel="I understand"
                        cancelLabel="Stay on this screen"
                        icon="🚨"
                        onClose={() => setModal(null)}
                        onConfirm={() => setModal(null)}
                        dependencies={[{ label: 'activity log entry', count: 1 }]}
                    />
                </section>

                {/* --------------------------------------------------------- Stepper */}
                <section className="gallery-section" aria-labelledby="stepper-title">
                    <div className="gs-title">
                        <h2 id="stepper-title">Stepper6 / WorkflowTracker</h2>
                        <span className="badge badge-neutral">COMPONENT-SPEC §14</span>
                        <span className="caption">horizontal ≥640 / vertical &lt;640 · jump-to-stage · blocked variant</span>
                    </div>

                    <div className="gallery-grid cols-2">
                        <div className="gallery-item">
                            <span className="gi-label">Workflow (interactive)</span>
                            <WorkflowTracker steps={defaultWorkflow()} onJump={(key) => push({ tone: 'info', message: `Jumped to ${key} (demo).` })} />
                        </div>
                        <div className="gallery-item">
                            <span className="gi-label">Blocked + 3-step variants</span>
                            <Stepper
                                steps={[
                                    { key: 'submitted', label: 'Submitted', state: 'done', sub: '23 Nov' },
                                    { key: 'review', label: 'Admin review', state: 'current', sub: 'usually <24h' },
                                    { key: 'activation', label: 'Activation', state: 'upcoming' },
                                ]}
                                ariaLabel="Approval progress"
                            />
                            <div className="mt-b" />
                            <Stepper
                                steps={[
                                    { key: 'stock', label: 'Stock check', state: 'blocked', reason: 'insufficient stock' },
                                    { key: 'dispense', label: 'Dispense', state: 'upcoming' },
                                ]}
                            />
                        </div>
                    </div>
                </section>

                {/* --------------------------------------------------------- Feedback */}
                <section className="gallery-section" aria-labelledby="feedback-title">
                    <div className="gs-title">
                        <h2 id="feedback-title">Feedback kit</h2>
                        <span className="badge badge-neutral">COMPONENT-SPEC §15</span>
                        <span className="caption">Toast · InlineError · Spinner · RetryBanner · UnsavedBar</span>
                    </div>

                    <div className="gallery-grid cols-2">
                        <div className="gallery-item">
                            <span className="gi-label">Toasts (danger persists)</span>
                            <div className="mp-row">
                                <button className="btn btn-outline btn-sm" onClick={() => push({ tone: 'success', message: 'Prescription saved.' })}>Success toast</button>
                                <button className="btn btn-outline btn-sm" onClick={() => push({ tone: 'danger', message: 'Dispense blocked: stock changed.', persistent: true })}>Danger toast</button>
                                <button className="btn btn-outline btn-sm" onClick={() => push({ tone: 'info', message: 'Settings refreshed.' })}>Info toast</button>
                            </div>
                            <div className="mp-row mt-s">
                                <Spinner size="lg" label="Loading preview" />
                                <span className="saving-inline"><Spinner /> Saving…</span>
                            </div>
                            <InlineError id="demo-inline-error" message="Slot 10:30 was just booked — choose another." />
                        </div>

                        <div className="gallery-item">
                            <span className="gi-label">Retry banner (cause + last-known)</span>
                            <RetryBanner
                                message="Aggregation timed out."
                                lastKnownAt={new Date(Date.now() - 5 * 60 * 1000).toISOString()}
                                onRetry={() => push({ tone: 'info', message: 'Retry invoked (demo).' })}
                            />
                            <div className="mt-b" />
                            <UnsavedBar
                                onDiscard={() => push({ tone: 'info', message: 'Changes discarded (demo).' })}
                                onStay={() => push({ tone: 'success', message: 'Stayed on the form (demo).' })}
                            />
                        </div>
                    </div>
                </section>

                {/* ------------------------------------------------------------- PDF */}
                <section className="gallery-section" aria-labelledby="pdf-title">
                    <div className="gs-title">
                        <h2 id="pdf-title">PdfButton</h2>
                        <span className="badge badge-neutral">COMPONENT-SPEC §16</span>
                        <span className="caption">Idle | Loading | Ready | Disabled</span>
                    </div>

                    <div className="gallery-item">
                        <div className="mp-row">
                            <PdfButton kind="prescription" state={pdfState} onGenerate={() => {
                                setPdfState('loading');
                                setTimeout(() => {
                                    setPdfState('ready');
                                    push({ tone: 'success', message: 'Prescription PDF ready.' });
                                }, 900);
                            }} />
                            <PdfButton kind="invoice" state="disabled" disabledReason="PDF unavailable — ownership check failed" />
                            <PdfButton kind="invoice" state="idle" />
                            <PdfButton kind="prescription" state="ready" href="#" size="sm" />
                        </div>
                        <p className="caption">
                            Ownership is enforced server-side; this control only reflects readiness.
                        </p>
                    </div>
                </section>

                {/* ---------------------------------------------------------- Chatbot */}
                <section className="gallery-section" aria-labelledby="chat-title">
                    <div className="gs-title">
                        <h2 id="chat-title">ChatbotWidget</h2>
                        <span className="badge badge-neutral">COMPONENT-SPEC §11</span>
                        <span className="caption">pinned disclaimer · in-stream emergency · AI-offline fallback + Retry</span>
                    </div>

                    <div className="gallery-item">
                        <p className="caption">
                            Click the floating “Ask MediPath” button (bottom-right). Type “chest pain” to trigger the
                            deterministic emergency stream; toggle offline mode and send to see the fallback + Retry.
                        </p>
                        <div className="mp-row">
                            <label className="switch">
                                <input type="checkbox" checked={offlineChat} onChange={(event) => setOfflineChat(event.target.checked)} />
                                <span>Simulate AI offline</span>
                            </label>
                            <span className="caption">Emergency contacts come from the DB, not the component.</span>
                        </div>
                    </div>

                    <ChatbotWidget
                        transport={transport}
                        forceOffline={offlineChat}
                        contacts={emergencyContacts}
                        offlineAnswer="Appointments can be booked from the doctor directory. Bring your patient code to the clinic. For medicine questions, a pharmacist confirms every prescription."
                        suggestions={['How do I book an appointment?', 'Can I download my prescription?', 'What are clinic hours?']}
                        initialOpen={false}
                    />
                </section>
            </div>
        </GuestLayout>
    );
}