<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Location;
use App\Models\ShiftRule;
use App\Models\Staff;
use App\Models\StaffShift;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Whether a proposed week of work is one somebody may actually be given.
 *
 * The checks a rota has to survive live here rather than in the controller,
 * because they are the same checks wherever a shift comes from — the assign
 * dialog, a single shift typed by hand, and whatever generates a rota next.
 * A copy per entry point is a rule that eventually disagrees with itself.
 *
 * Every check answers the same question — is this hour workable — from a
 * different direction: the business is shut, the person is already on, the
 * rule says no more than eight, the rule says ten hours between. Each returns
 * a message against the day it is about, so the dialog can put it beside the
 * row rather than at the top.
 */
class ScheduleGuard
{
    /**
     * @param  Collection<int, array{date: string, starts_at: string, ends_at: string, break_minutes: int|null}>  $proposed
     */
    public function __construct(
        private Staff $staff,
        private ?ShiftRule $rule,
        private ?Location $location,
    ) {}

    /**
     * Every problem with the proposal, keyed by the day it belongs to.
     *
     * All of them, not the first: a fortnight entered in one go with three
     * bad days should say so once rather than three times over.
     *
     * @param  Collection<int, array<string, mixed>>  $proposed
     * @param  array<int, int>  $ignoreShiftIds  shifts being replaced by this
     *                                           proposal, so a week does not
     *                                           clash with the copy of itself
     *                                           it is about to overwrite
     * @return array<string, array<int, string>>
     */
    public function problems(Collection $proposed, array $ignoreShiftIds = []): array
    {
        $problems = [];

        $add = function (string $date, string $message) use (&$problems): void {
            $problems[$date][] = $message;
        };

        if (! $this->staff->is_active) {
            $add('*', __('schedule.validation.staff_not_active', ['name' => $this->staff->displayName()]));
        }

        $byDate = $proposed->groupBy('date');

        foreach ($byDate as $date => $periods) {
            $this->checkDay($date, $periods, $add);
        }

        $this->checkWeeklyHours($byDate, $add);
        $this->checkRestBetween($proposed, $ignoreShiftIds, $add);
        $this->checkConsecutiveDays($byDate, $add);
        $this->checkExistingShifts($proposed, $ignoreShiftIds, $add);

        return $problems;
    }

    /** @param Collection<int, array<string, mixed>> $periods */
    private function checkDay(string $date, Collection $periods, callable $add): void
    {
        $sorted = $periods
            ->map(fn (array $p) => [
                'from' => $this->minutes($p['starts_at']),
                'to' => $this->minutes($p['ends_at']),
                'break' => (int) ($p['break_minutes'] ?? 0),
            ])
            ->sortBy('from')
            ->values();

        foreach ($sorted as $period) {
            if ($period['to'] <= $period['from']) {
                $add($date, __('schedule.validation.ends_after_starts'));

                return;
            }
        }

        /* Two periods in a day is a split shift, which is a thing the rule
           either permits or does not. */
        if ($sorted->count() > 1 && $this->rule && ! $this->rule->allow_split_shift) {
            $add($date, __('schedule.validation.split_not_allowed'));
        }

        for ($i = 1; $i < $sorted->count(); $i++) {
            /* Touching end-to-end is not an overlap: nine-to-one and one-to-six
               is a legal way to describe a long day. */
            if ($sorted[$i]['from'] < $sorted[$i - 1]['to']) {
                $add($date, __('schedule.validation.periods_overlap'));

                break;
            }
        }

        $this->checkSplitLimits($date, $sorted, $add);
        $this->checkBusinessHours($date, $sorted, $add);
        $this->checkDailyHours($date, $sorted, $add);
    }

    /** @param Collection<int, array<string, int>> $sorted */
    private function checkSplitLimits(string $date, Collection $sorted, callable $add): void
    {
        if ($this->rule === null || $sorted->count() < 2) {
            return;
        }

        if (! $this->rule->allow_same_employee_multiple_periods) {
            $add($date, __('schedule.validation.one_period_only'));

            return;
        }

        $max = (int) $this->rule->max_periods_per_employee_per_day;

        if ($max > 0 && $sorted->count() > $max) {
            $add($date, __('schedule.validation.too_many_periods', ['count' => $max]));
        }

        $gap = (int) $this->rule->min_gap_minutes;

        if ($gap <= 0) {
            return;
        }

        for ($i = 1; $i < $sorted->count(); $i++) {
            if ($sorted[$i]['from'] - $sorted[$i - 1]['to'] < $gap) {
                $add($date, __('schedule.validation.gap_too_short', ['hours' => $this->asHours($gap)]));

                break;
            }
        }
    }

    /**
     * Inside the hours the business keeps, on the weekday in question.
     *
     * Inside any one open period rather than the day's outer bounds: a
     * business that shuts for lunch is shut for lunch, and a shift spanning
     * the gap would be rostering somebody into a closed building.
     *
     * @param  Collection<int, array<string, int>>  $sorted
     */
    private function checkBusinessHours(string $date, Collection $sorted, callable $add): void
    {
        if ($this->location === null || $this->location->hours->isEmpty()) {
            return;
        }

        $weekday = Carbon::parse($date)->dayOfWeek;
        $open = $this->location->hours->where('day_of_week', $weekday)->where('is_open', true);

        if ($open->isEmpty()) {
            $add($date, __('schedule.validation.business_closed'));

            return;
        }

        $windows = $open->map(fn ($hour) => [
            'from' => $this->minutes($hour->timeValue('opens_at')),
            'to' => $this->minutes($hour->timeValue('closes_at')),
        ]);

        foreach ($sorted as $period) {
            $fits = $windows->contains(
                fn (array $window) => $window['from'] <= $period['from'] && $window['to'] >= $period['to']
            );

            if (! $fits) {
                $add($date, __('schedule.validation.outside_business_hours', [
                    'hours' => $open->map->rangeLabel()->implode(', '),
                ]));

                return;
            }
        }
    }

    /** @param Collection<int, array<string, int>> $sorted */
    private function checkDailyHours(string $date, Collection $sorted, callable $add): void
    {
        $max = (int) ($this->rule?->max_hours_per_day ?? 0);

        if ($max <= 0) {
            return;
        }

        $worked = $sorted->sum(fn (array $p) => max(0, $p['to'] - $p['from'] - $p['break']));

        /* Overtime is a separate setting: a rule that permits it permits the
           day to run over, and the check is the reader's warning rather than
           a refusal. Where it is off, the ceiling is a ceiling. */
        if ($worked > $max * 60 && ! $this->rule->allow_overtime) {
            $add($date, __('schedule.validation.over_daily_hours', ['count' => $max]));
        }
    }

    /** @param Collection<string, Collection<int, array<string, mixed>>> $byDate */
    private function checkWeeklyHours(Collection $byDate, callable $add): void
    {
        $max = (int) ($this->rule?->max_hours_per_week ?? 0);

        if ($max <= 0 || $this->rule->allow_overtime) {
            return;
        }

        /* Grouped by the week each day falls in, not across the whole range:
           a fortnight of thirty-five hours a week is not seventy hours in a
           week, and treating it as one would refuse a perfectly ordinary
           fortnight. */
        $weeks = $byDate->groupBy(fn ($periods, string $date) => Carbon::parse($date)->startOfWeek()->toDateString());

        foreach ($weeks as $weekStart => $days) {
            $worked = $days->flatten(1)->sum(fn (array $p) => max(
                0,
                $this->minutes($p['ends_at']) - $this->minutes($p['starts_at']) - (int) ($p['break_minutes'] ?? 0)
            ));

            if ($worked > $max * 60) {
                $add($weekStart, __('schedule.validation.over_weekly_hours', ['count' => $max]));
            }
        }
    }

    /**
     * Enough time off between finishing and starting again.
     *
     * Measured against what is already on the rota as well as against the
     * proposal, because the night before a new week is a shift this proposal
     * does not contain.
     *
     * @param  Collection<int, array<string, mixed>>  $proposed
     * @param  array<int, int>  $ignoreShiftIds
     */
    private function checkRestBetween(Collection $proposed, array $ignoreShiftIds, callable $add): void
    {
        $hours = (int) ($this->rule?->min_rest_hours ?? 0);

        if ($hours <= 0 || $proposed->isEmpty()) {
            return;
        }

        $blocks = $proposed
            ->map(fn (array $p) => [
                'date' => $p['date'],
                'from' => Carbon::parse($p['date'].' '.$p['starts_at']),
                'to' => Carbon::parse($p['date'].' '.$p['ends_at']),
            ])
            ->merge($this->surroundingShifts($proposed, $ignoreShiftIds))
            ->sortBy('from')
            ->values();

        for ($i = 1; $i < $blocks->count(); $i++) {
            $rest = $blocks[$i - 1]['to']->diffInMinutes($blocks[$i]['from'], false);

            /* Same-day periods are a split shift, which the gap rule governs;
               this one is about finishing one day and starting the next. */
            if ($blocks[$i]['date'] === $blocks[$i - 1]['date']) {
                continue;
            }

            if ($rest >= 0 && $rest < $hours * 60) {
                $add($blocks[$i]['date'], __('schedule.validation.not_enough_rest', ['count' => $hours]));
            }
        }
    }

    /**
     * The shifts either side of the proposal, which it has to fit between.
     *
     * @param  Collection<int, array<string, mixed>>  $proposed
     * @param  array<int, int>  $ignoreShiftIds
     * @return Collection<int, array<string, mixed>>
     */
    private function surroundingShifts(Collection $proposed, array $ignoreShiftIds): Collection
    {
        $dates = $proposed->pluck('date');
        $from = Carbon::parse($dates->min())->subDay();
        $until = Carbon::parse($dates->max())->addDay();

        return StaffShift::query()
            ->where('staff_id', $this->staff->id)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $until->toDateString())
            ->whereNotIn('id', $ignoreShiftIds)
            ->where('status', '!=', 'cancelled')
            ->get()
            ->reject(fn (StaffShift $shift) => $dates->contains($shift->date->toDateString()))
            ->map(fn (StaffShift $shift) => [
                'date' => $shift->date->toDateString(),
                'from' => $shift->startsAt(),
                'to' => $shift->endsAt(),
            ])
            ->values();
    }

    /** @param Collection<string, Collection<int, array<string, mixed>>> $byDate */
    private function checkConsecutiveDays(Collection $byDate, callable $add): void
    {
        $max = (int) ($this->rule?->max_consecutive_days ?? 0);

        if ($max <= 0) {
            return;
        }

        $dates = $byDate->keys()->sort()->values();
        $run = 0;
        $previous = null;

        foreach ($dates as $date) {
            $day = Carbon::parse($date);

            $run = $previous !== null && $previous->copy()->addDay()->isSameDay($day) ? $run + 1 : 1;
            $previous = $day;

            if ($run > $max) {
                $add($date, __('schedule.validation.too_many_consecutive', ['count' => $max]));
            }
        }
    }

    /**
     * Nobody is in two places at once.
     *
     * @param  Collection<int, array<string, mixed>>  $proposed
     * @param  array<int, int>  $ignoreShiftIds
     */
    private function checkExistingShifts(Collection $proposed, array $ignoreShiftIds, callable $add): void
    {
        $existing = StaffShift::query()
            ->where('staff_id', $this->staff->id)
            ->whereIn('date', $proposed->pluck('date')->unique()->all())
            ->whereNotIn('id', $ignoreShiftIds)
            ->where('status', '!=', 'cancelled')
            ->get();

        foreach ($proposed as $period) {
            $clash = $existing->first(fn (StaffShift $shift) => $shift->date->toDateString() === $period['date']
                && $shift->timeValue('starts_at') < $period['ends_at']
                && $shift->timeValue('ends_at') > $period['starts_at']);

            if ($clash !== null) {
                $add($period['date'], __('schedule.validation.already_working', [
                    'hours' => $clash->hoursLabel(),
                ]));
            }
        }
    }

    private function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', array_pad(explode(':', $time), 2, 0));

        return $hours * 60 + $minutes;
    }

    private function asHours(int $minutes): string
    {
        return rtrim(rtrim(number_format($minutes / 60, 1), '0'), '.');
    }
}
