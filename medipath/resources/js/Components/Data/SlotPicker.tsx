/**
 * SlotPicker + DateStrip — COMPONENT-SPEC §8 (date strip + leave-aware slot
 * grid, disabled kinds with explanations, 4 data states, slot-taken conflict
 * path). Reproduces prototypes/patient/02-booking.
 *
 * All availability is computed server-side; this component only renders the
 * server's slots and never guesses availability (G8).
 */
import { useId } from 'react';
import type { DataState, DateChip, Slot } from '@/types';
import { cn } from '@/lib/format';

export function DateStrip({
    dates,
    selected,
    onSelect,
    state = 'success',
}: {
    dates: DateChip[];
    selected?: string;
    onSelect: (date: string) => void;
    state?: DataState;
}) {
    if (state === 'skeleton') {
        return (
            <div className="date-strip" aria-busy="true">
                {Array.from({ length: 7 }).map((_, index) => (
                    <span className="sk" style={{ width: 64, height: 64, borderRadius: 12 }} key={index} aria-hidden="true" />
                ))}
            </div>
        );
    }

    return (
        <div className="date-strip" role="group" aria-label="Choose a date">
            {dates.map((date) => (
                <button
                    type="button"
                    key={date.date}
                    className={cn(
                        'date-chip',
                        date.date === selected && 'is-on',
                        date.isToday && 'is-today',
                        date.isOff && 'is-off',
                    )}
                    aria-pressed={date.date === selected}
                    aria-disabled={date.isOff || undefined}
                    onClick={() => {
                        if (!date.isOff) onSelect(date.date);
                    }}
                >
                    <span className="dow">{date.day.toUpperCase()}</span>
                    <span className="dnum">{date.num}</span>
                    {date.isToday ? <span className="sr-only">, today</span> : null}
                    {date.isOff ? <span className="sr-only">, clinic closed</span> : null}
                </button>
            ))}
        </div>
    );
}

interface SlotPickerProps {
    dates: DateChip[];
    selectedDate?: string;
    onSelectDate: (date: string) => void;
    slots: Slot[];
    selectedTime?: string;
    onSelectTime: (time: string) => void;
    state?: DataState;
    onRetry?: () => void;
    emptyHint?: string;
    /** Server-provided conflict message (e.g. slot just taken). */
    conflict?: string | null;
    onReload?: () => void;
}

const SLOT_CLASS: Record<Slot['kind'], string> = {
    open: '',
    selected: 'is-on',
    booked: 'is-booked',
    past: 'is-past',
    leave: 'is-leave',
    closed: 'is-closed',
};

const SLOT_REASON: Record<Slot['kind'], string> = {
    open: 'available',
    selected: 'selected',
    booked: 'already booked',
    past: 'in the past',
    leave: 'doctor on leave',
    closed: 'clinic closed',
};

export function SlotPicker({
    dates,
    selectedDate,
    onSelectDate,
    slots,
    selectedTime,
    onSelectTime,
    state = 'success',
    onRetry,
    emptyHint = 'The next opening is Monday at 09:00. Try another date.',
    conflict,
    onReload,
}: SlotPickerProps) {
    const groupId = useId();

    return (
        <div className="stack">
            <DateStrip dates={dates} selected={selectedDate} onSelect={onSelectDate} state={state} />

            {state === 'error' ? (
                <div className="retry-banner" role="alert">
                    <span className="ico" aria-hidden="true">⚠</span>
                    <span className="txt">
                        <strong>Slot availability failed to load.</strong> Booking is disabled until slots refresh.
                    </span>
                    <button type="button" className="btn btn-primary" onClick={onRetry}>
                        Retry
                    </button>
                </div>
            ) : state === 'skeleton' ? (
                <div className="slot-grid" aria-busy="true">
                    {Array.from({ length: 12 }).map((_, index) => (
                        <span className="sk" style={{ height: 'var(--mp-touch)' }} key={index} aria-hidden="true" />
                    ))}
                </div>
            ) : state === 'empty' || slots.length === 0 ? (
                <div className="empty">
                    <span className="glyph" aria-hidden="true">🕓</span>
                    <p className="empty-title">No open slots this week</p>
                    <p className="empty-body">{emptyHint}</p>
                </div>
            ) : (
                <>
                    <div className="slot-grid" role="radiogroup" aria-label="Available time slots" id={groupId}>
                        {slots.map((slot) => {
                            const disabled = slot.kind !== 'open' && slot.kind !== 'selected';
                            const slotId = `${groupId}-${slot.time.replace(':', '')}`;

                            return (
                                <span key={slot.time} style={{ display: 'contents' }}>
                                    <input
                                        type="radio"
                                        id={slotId}
                                        name={groupId}
                                        className="sr-only"
                                        checked={selectedTime === slot.time && slot.kind !== 'booked'}
                                        disabled={disabled}
                                        onChange={() => onSelectTime(slot.time)}
                                    />
                                    <label
                                        htmlFor={slotId}
                                        className={cn('slot', SLOT_CLASS[slot.kind], disabled && 'is-disabled')}
                                        aria-label={`${slot.time}, ${SLOT_REASON[slot.kind]}`}
                                        aria-disabled={disabled || undefined}
                                    >
                                        {slot.time}
                                        {slot.kind === 'booked' ? <span className="sr-only">, already booked</span> : null}
                                    </label>
                                </span>
                            );
                        })}
                    </div>

                    <div className="slot-legend" aria-label="Slot legend">
                        <span className="l-open"><i aria-hidden="true" />Available</span>
                        <span className="l-sel"><i aria-hidden="true" />Selected</span>
                        <span className="l-book"><i aria-hidden="true" />Booked</span>
                        <span className="l-leave"><i aria-hidden="true" />On leave</span>
                    </div>
                </>
            )}

            {conflict ? (
                <div className="alert alert-danger" role="alert">
                    <span className="a-ico" aria-hidden="true">⚠</span>
                    <div className="a-body">
                        <strong className="a-title">That slot was just taken.</strong>
                        <p>{conflict}</p>
                        <p className="caption">Your visit reason was kept. Reload availability to choose another time.</p>
                        <button type="button" className="btn btn-primary btn-sm mt-s" onClick={onReload}>
                            Reload availability
                        </button>
                    </div>
                </div>
            ) : null}
        </div>
    );
}