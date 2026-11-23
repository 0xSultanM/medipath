<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Doctor;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Leave-aware availability (PLAN §3, Sprint 1.5/4.5).
 *
 * A slot is available only when it falls inside an active schedule block,
 * outside the configured break, is not already held by a pending/accepted
 * appointment, and is not in the past. Approved leave removes the whole day.
 * This is the single source for the public profile, directory "available today"
 * filter and (later) the patient booking picker.
 */
class DoctorAvailabilityService
{
    /**
     * @return array<int, array{
     *   date:string,label:string,day_of_week:int,is_today:bool,
     *   windows:string,open_slots:int,total_slots:int,status:string,
     *   open_times:array<int,string>
     * }>
     */
    public function forDoctor(Doctor $doctor, CarbonInterface|string|null $from = null, int $days = 7): array
    {
        $start = $from
            ? Carbon::parse($from)->startOfDay()
            : Carbon::today();
        $end = $start->copy()->addDays(max(1, min($days, 31)) - 1);

        $schedules = $doctor->schedules()
            ->where('is_active', true)
            ->get()
            ->groupBy('day_of_week');

        $leaves = $doctor->leaves()
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->get();

        $bookings = Appointment::query()
            ->where('doctor_id', $doctor->id)
            ->whereIn('status', ['pending', 'accepted'])
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->get()
            ->groupBy(fn (Appointment $appointment) => $appointment->date->format('Y-m-d'));

        $rows = [];
        $cursor = $start->copy();
        $now = now();

        while ($cursor->lte($end)) {
            $dateKey = $cursor->toDateString();
            $dayOfWeek = (int) $cursor->dayOfWeek;
            $daySchedules = $schedules->get($dayOfWeek, collect());
            $onLeave = $leaves->contains(fn ($leave) => $cursor->betweenIncluded(
                Carbon::parse($leave->start_date)->startOfDay(),
                Carbon::parse($leave->end_date)->endOfDay(),
            ));

            $windows = $daySchedules->map(fn ($schedule) => substr((string) $schedule->start_time, 0, 5).'–'.substr((string) $schedule->end_time, 0, 5))->implode(' · ');

            if ($onLeave) {
                $rows[] = $this->row($cursor, $dayOfWeek, $windows ?: '—', 0, 0, 'leave', []);

                $cursor->addDay();

                continue;
            }

            if ($daySchedules->isEmpty()) {
                $rows[] = $this->row($cursor, $dayOfWeek, '—', 0, 0, 'closed', []);

                $cursor->addDay();

                continue;
            }

            $booked = collect($bookings->get($dateKey, collect()))
                ->map(fn (Appointment $appointment) => substr((string) $appointment->start_time, 0, 5))
                ->all();

            $openTimes = [];
            $total = 0;

            foreach ($daySchedules as $schedule) {
                $duration = max(5, (int) $schedule->slot_duration);
                $blockStart = Carbon::parse($dateKey.' '.$schedule->start_time);
                $blockEnd = Carbon::parse($dateKey.' '.$schedule->end_time);
                $breakStart = $schedule->break_start ? Carbon::parse($dateKey.' '.$schedule->break_start) : null;
                $breakEnd = $schedule->break_end ? Carbon::parse($dateKey.' '.$schedule->break_end) : null;

                $slot = $blockStart->copy();

                while ($slot->copy()->addMinutes($duration)->lte($blockEnd)) {
                    $total++;
                    $slotKey = $slot->format('H:i');

                    $inBreak = $breakStart && $breakEnd
                        && $slot->gte($breakStart) && $slot->lt($breakEnd);

                    $past = $slot->lte($now);

                    if (! $inBreak && ! $past && ! in_array($slotKey, $booked, true)) {
                        $openTimes[] = $slotKey;
                    }

                    $slot->addMinutes($duration);
                }
            }

            $status = $openTimes === [] ? 'full' : 'available';

            $rows[] = $this->row($cursor, $dayOfWeek, $windows, count($openTimes), $total, $status, $openTimes);

            $cursor->addDay();
        }

        return $rows;
    }

    /** First open slot within the horizon, or null. */
    public function nextAvailable(Doctor $doctor, int $days = 14): ?Carbon
    {
        foreach ($this->forDoctor($doctor, null, $days) as $row) {
            if ($row['open_slots'] > 0 && $row['open_times'] !== []) {
                return Carbon::parse($row['date'].' '.$row['open_times'][0]);
            }
        }

        return null;
    }

    public function label(?CarbonInterface $slot): ?string
    {
        if (! $slot) {
            return null;
        }

        $slot = Carbon::parse($slot);

        if ($slot->isToday()) {
            return 'Today, '.$slot->format('g:i A');
        }

        if ($slot->isTomorrow()) {
            return 'Tomorrow, '.$slot->format('g:i A');
        }

        return $slot->format('D j M, g:i A');
    }

    public function hasSlotsToday(Doctor $doctor): bool
    {
        return $this->forDoctor($doctor, null, 1)[0]['open_slots'] > 0;
    }

    /**
     * @param  array<int, string>  $openTimes
     * @return array<string, mixed>
     */
    private function row(Carbon $date, int $dayOfWeek, string $windows, int $open, int $total, string $status, array $openTimes): array
    {
        return [
            'date' => $date->toDateString(),
            'label' => $date->format('D j M'),
            'day_of_week' => $dayOfWeek,
            'is_today' => $date->isToday(),
            'windows' => $windows,
            'open_slots' => $open,
            'total_slots' => $total,
            'status' => $status,
            'open_times' => $openTimes,
        ];
    }
}
