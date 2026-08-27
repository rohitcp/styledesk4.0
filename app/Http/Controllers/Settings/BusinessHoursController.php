<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Locations\SaveOpeningHours;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\LocationClosure;
use App\Rules\NonOverlappingPeriods;
use App\Support\LocationOptions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The Business Hours module: when each branch is open, and when it is not.
 *
 * Weekly hours, future schedules, and the dates that override both — holidays,
 * closures and special hours. Location settings edits one branch's week as part
 * of editing that branch; this module is where the business looks at all of
 * them together and where the exceptions live.
 *
 * Both screens write through SaveOpeningHours, so a week saved from either is
 * stored identically. Two implementations of "what does this submitted form
 * mean" is how one screen quietly starts treating a closed Sunday differently
 * from the other.
 */
class BusinessHoursController extends Controller
{
    /**
     * How far ahead the overview looks for exceptions.
     *
     * A year, because holidays are entered a year at a time and a business
     * that has planned next Christmas should see it. Beyond that the list
     * stops being an overview.
     */
    private const UPCOMING_MONTHS = 12;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Location::class);

        $tenant = $request->user()->tenant;

        $locations = $tenant->locations()
            ->with(['hours', 'manager'])
            ->inDisplayOrder()
            ->get();

        /**
         * Exceptions for every branch in one query, grouped after the fact.
         *
         * A per-location query inside the view would be one query per card,
         * and the number of them would only be visible to whoever eventually
         * profiles the page.
         */
        $closures = LocationClosure::query()
            ->whereIn('location_id', $locations->pluck('id'))
            ->upcoming()
            ->whereDate('starts_on', '<=', now()->addMonths(self::UPCOMING_MONTHS)->toDateString())
            ->with('location')
            ->get();

        return view('settings.hours.index', [
            'locations' => $locations,
            'closuresByLocation' => $closures->groupBy('location_id'),
            'upcoming' => $closures->sortBy('starts_on')->values(),
        ]);
    }

    public function edit(Request $request, Location $location): View
    {
        $this->authorize('manageHours', $location);

        /**
         * Which schedule is being edited.
         *
         * Defaults to the one in force. A future date opens that schedule
         * instead, so a business can revise next month's hours without first
         * having to reach the first of the month.
         */
        $schedule = $this->requestedSchedule($request, $location);

        return view('settings.hours.edit', [
            'location' => $location,
            'schedule' => $schedule,
            'isFutureSchedule' => $schedule > now()->toDateString(),
            'hoursByDay' => $this->hoursByDay($location, $schedule),
            'futureSchedules' => $location->futureScheduleDates(),
            'closures' => $location->closures()->upcoming()->get(),
            // Only other locations, and only active ones: copying a week to a
            // branch that is not trading is a change nobody asked for.
            'otherLocations' => $request->user()->tenant->locations()
                ->active()->where('id', '!=', $location->id)->inDisplayOrder()->get(),
        ]);
    }

    public function update(Request $request, Location $location, SaveOpeningHours $saver): RedirectResponse
    {
        $this->authorize('manageHours', $location);

        $data = $this->validatedHours($request);

        $effectiveFrom = $data['effective_from'] ?? null;
        $applyTo = $this->applyTargets($request, $location, $data['apply_to'] ?? []);

        /**
         * Counted before the list is built, not after.
         *
         * prepend() mutates the collection in place, so counting afterwards
         * counted this location as one of the others — a save with nothing
         * ticked cheerfully reported "also applied to 1 other location",
         * which is a screen telling the user something it did not do.
         */
        $others = $applyTo->count();

        $saver->saveMany(collect([$location])->concat($applyTo), $data['hours'] ?? [], $effectiveFrom);

        return redirect()
            ->route('settings.hours.edit', ['location' => $location, 'schedule' => $effectiveFrom])
            ->with('toast', [
                'type' => 'success',
                'message' => $this->savedMessage($effectiveFrom, $others),
            ]);
    }

    /**
     * Drop a future schedule, so the current one keeps applying.
     *
     * Deliberately refuses the current schedule: deleting the hours in force
     * would leave a branch with no opening times at all, which is a state the
     * booking engine has no sensible reading of. Closing every day is how a
     * business says that, and it says it explicitly.
     */
    public function destroySchedule(Request $request, Location $location): RedirectResponse
    {
        $this->authorize('manageHours', $location);

        $schedule = (string) $request->input('schedule');

        if ($schedule === '' || $schedule <= now()->toDateString()) {
            throw ValidationException::withMessages([
                'schedule' => 'Only a schedule that has not started yet can be discarded.',
            ]);
        }

        $location->scheduleHours($schedule)->delete();

        return redirect()
            ->route('settings.hours.edit', $location)
            ->with('toast', ['type' => 'success', 'message' => 'Upcoming hours discarded.']);
    }

    // ----------------------------------------------------------- exceptions

    public function storeClosure(Request $request, Location $location): RedirectResponse
    {
        $this->authorize('manageHours', $location);

        $location->closures()->create($this->validatedClosure($request, $location, null));

        return back()->with('toast', ['type' => 'success', 'message' => 'Added to the calendar.']);
    }

    public function updateClosure(Request $request, Location $location, LocationClosure $closure): RedirectResponse
    {
        $this->authorize('manageHours', $location);
        $this->assertBelongsTo($closure, $location);

        $closure->update($this->validatedClosure($request, $location, $closure));

        return back()->with('toast', ['type' => 'success', 'message' => 'Calendar entry updated.']);
    }

    public function destroyClosure(Request $request, Location $location, LocationClosure $closure): RedirectResponse
    {
        $this->authorize('manageHours', $location);
        $this->assertBelongsTo($closure, $location);

        $closure->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Removed from the calendar.']);
    }

    // -------------------------------------------------------------- helpers

    /**
     * @return array<string, mixed>
     */
    private function validatedHours(Request $request): array
    {
        $rules = [
            /**
             * When the week starts applying.
             *
             * Absent means now — the ordinary case of correcting today's
             * hours. A date makes it a future schedule, stored alongside the
             * current one rather than replacing it.
             */
            'effective_from' => ['nullable', 'date', 'after:today'],

            'apply_to' => ['nullable', 'array'],
            'apply_to.*' => ['integer'],

            'hours' => ['array'],
            'hours.*' => ['array'],
            /**
             * The day's own open/closed toggle, declared so it survives.
             *
             * validated() returns only what the rules name, and without a rule
             * for this key each day came back as a bare list of periods with
             * the toggle stripped out — every day then read as closed and the
             * form saved a week of nothing while reporting success.
             */
            'hours.*.is_open' => ['nullable', 'boolean'],
            'hours.*.*.opens_at' => ['nullable', 'date_format:H:i'],
            'hours.*.*.closes_at' => ['nullable', 'date_format:H:i'],
        ];

        // Overlap is a relationship between two periods, so it is checked per
        // day rather than per period: 9–1 and 12–5 are each valid times and
        // only together are they wrong.
        foreach (LocationOptions::weekdays() as $day => $label) {
            $rules['hours.'.$day] = ['array', new NonOverlappingPeriods($label)];
        }

        return $request->validate($rules, [
            'effective_from.after' => 'A future schedule must start on a later date. Leave this blank to change today’s hours.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedClosure(Request $request, Location $location, ?LocationClosure $closure): array
    {
        /**
         * The toggle is normalised before the rules read it.
         *
         * An unchecked checkbox posts nothing at all, so `required_if:
         * is_closed_all_day,0` never fired: a "special opening hours" entry
         * with no times sailed past validation and then crashed reading a key
         * validate() had not produced. Merging a real 0 or 1 is what makes
         * the rule mean what it says.
         */
        $request->merge([
            'is_closed_all_day' => $request->boolean('is_closed_all_day') ? 1 : 0,
        ]);

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(config('locations.closure_types')))],
            'name' => ['required', 'string', 'max:120'],
            'starts_on' => ['required', 'date'],
            /**
             * A single day is a range of one, so the end date defaults to the
             * start rather than being optional. Nullable would push a null
             * case into every query that asks whether a date is covered.
             */
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_closed_all_day' => ['nullable', 'boolean'],
            'opens_at' => ['nullable', 'date_format:H:i', 'required_if:is_closed_all_day,0'],
            'closes_at' => ['nullable', 'date_format:H:i', 'required_if:is_closed_all_day,0', 'after:opens_at'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'name.required' => 'Give this a name, so the team knows what it is.',
            'starts_on.required' => 'Choose a date.',
            'ends_on.after_or_equal' => 'The end date cannot be before the start date.',
            'opens_at.required_if' => 'Enter the opening time, or mark the day as closed.',
            'closes_at.required_if' => 'Enter the closing time, or mark the day as closed.',
            'closes_at.after' => 'Closing time must be after the opening time.',
        ]);

        $closed = (bool) ($data['is_closed_all_day'] ?? false);

        // Absent, empty or equal to the start all mean the same single day.
        $data['ends_on'] = ($data['ends_on'] ?? null) ?: $data['starts_on'];
        $data['is_closed_all_day'] = $closed;

        /**
         * Times are cleared on a full-day closure.
         *
         * Keeping them would leave a row that says "closed all day" and also
         * carries 9 to 5, and the next person to read it — or the next query
         * that forgets to check the flag first — has to decide which half to
         * believe.
         */
        if ($closed) {
            $data['opens_at'] = null;
            $data['closes_at'] = null;
        }

        $this->assertNoClashingException($location, $data, $closure);

        return $data;
    }

    /**
     * Two exceptions must not cover the same date.
     *
     * The spec asks for no overlapping time ranges; on the calendar the same
     * rule means one answer per date. Two rows covering next Tuesday — a
     * holiday that closes and special hours that open — leave the booking
     * engine choosing between them, and whichever it picks will be wrong half
     * the time.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertNoClashingException(Location $location, array $data, ?LocationClosure $closure): void
    {
        $clash = $location->closures()
            ->when($closure, fn ($query) => $query->where('id', '!=', $closure->id))
            ->whereDate('starts_on', '<=', $data['ends_on'])
            ->whereDate('ends_on', '>=', $data['starts_on'])
            ->first();

        if ($clash === null) {
            return;
        }

        throw ValidationException::withMessages([
            'starts_on' => '“'.$clash->name.'” already covers '.$clash->dateLabel().'. Edit that entry instead, or choose different dates.',
        ]);
    }

    private function assertBelongsTo(LocationClosure $closure, Location $location): void
    {
        // The location is authorised, so a closure from another one must not be
        // reachable by swapping the id in the URL.
        abort_unless($closure->location_id === $location->id, 404);
    }

    /**
     * The schedule this request is editing.
     */
    private function requestedSchedule(Request $request, Location $location): string
    {
        $requested = $request->query('schedule');

        if ($requested && $location->futureScheduleDates()->contains($requested)) {
            return $requested;
        }

        return $location->currentScheduleDate() ?? Location::EPOCH;
    }

    /**
     * The other locations a week is being copied to.
     *
     * Re-read from the database rather than trusted from the request: the ids
     * arrive from a form, and a crafted one would otherwise let a week be
     * written into another business's branch.
     *
     * @param  array<int, mixed>  $ids
     * @return Collection<int, Location>
     */
    private function applyTargets(Request $request, Location $location, array $ids): Collection
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)->filter()->unique();

        if ($ids->isEmpty()) {
            return collect();
        }

        return $request->user()->tenant->locations()
            ->whereIn('id', $ids)
            ->where('id', '!=', $location->id)
            ->get();
    }

    private function savedMessage(?string $effectiveFrom, int $others): string
    {
        /**
         * Built without its full stop, which is added once at the end.
         *
         * rtrim($when, '.') removed it before appending the second sentence
         * and left "saved successfully Also applied to…" — two sentences run
         * together in the one place the screen is telling the user what it
         * just did.
         */
        $when = $effectiveFrom
            ? 'Hours saved, starting '.Carbon::parse($effectiveFrom)->format('j F Y')
            : 'Business hours saved successfully';

        if ($others === 0) {
            return $when.'.';
        }

        // Says how many, because "applied to other locations" leaves the user
        // to go and count which ones actually changed.
        return $when.'. Also applied to '.$others.' other '.Str::plural('location', $others).'.';
    }

    /**
     * Every weekday with its periods, including the closed ones.
     *
     * The view needs all seven rows whether or not the location opens on them;
     * asking Blade to fill the gaps would put the business's week in a
     * template.
     *
     * @return array<int, array{label: string, periods: Collection}>
     */
    private function hoursByDay(Location $location, string $schedule): array
    {
        $byDay = $location->scheduleHours($schedule)->get()->groupBy('day_of_week');

        $days = [];

        foreach (LocationOptions::weekdays() as $day => $label) {
            $days[$day] = [
                'label' => $label,
                'periods' => $byDay->get($day, collect())->where('is_open', true)->values(),
            ];
        }

        return $days;
    }
}
