<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Staff;
use App\Models\StaffShift;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * One planning period, and what it comes to.
 *
 * A schedule is published a range at a time — the 1, 2, 3 or 4 weeks the
 * manager is looking at — but it is stored a shift at a time, because a shift
 * is the thing that has a date, a location and a person working it. This class
 * is the one place that turns the rows back into the period: its state, its
 * totals, and the days the email lists.
 *
 * Everything that needs to agree about a period reads it from here — the page
 * banner, the publish dialog's summary and the email all quote the same
 * numbers, rather than each recomputing them and drifting apart.
 */
class SchedulePeriod
{
    /** Nothing scheduled in this range at all. */
    public const EMPTY = 'empty';

    /** Scheduled but never sent to the staff member. */
    public const DRAFT = 'draft';

    /** Sent, and unchanged since. */
    public const PUBLISHED = 'published';

    /** Sent once, and edited since — a change waiting to be communicated. */
    public const CHANGES = 'changes';

    /** @param  Collection<int, StaffShift>  $shifts */
    public function __construct(
        public readonly Staff $staff,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $until,
        public readonly Collection $shifts,
    ) {}

    public static function for(Staff $staff, CarbonImmutable $from, CarbonImmutable $until): self
    {
        return new self($staff, $from, $until, StaffShift::query()
            ->where('staff_id', $staff->id)
            ->inRange($from->toDateString(), $until->toDateString())
            ->with(['location', 'publisher'])
            ->orderBy('date')->orderBy('starts_at')
            ->get());
    }

    /**
     * The shifts that count towards the schedule.
     *
     * A cancelled shift is kept as a fact about the week but is not one
     * anybody works, so it never adds to the hours or makes a day a working
     * day — and it never on its own puts the period into draft.
     */
    public function working(): Collection
    {
        return $this->shifts->reject(fn (StaffShift $shift) => $shift->isCancelled());
    }

    public function state(): string
    {
        $working = $this->working();

        if ($working->isEmpty()) {
            return self::EMPTY;
        }

        if ($working->every(fn (StaffShift $shift) => $shift->isPublished())) {
            return self::PUBLISHED;
        }

        /* Published once and edited since, rather than never sent. Told apart
           by published_at, which survives a shift going back to draft. */
        return $working->contains(fn (StaffShift $shift) => $shift->published_at !== null)
            ? self::CHANGES
            : self::DRAFT;
    }

    public function isPublished(): bool
    {
        return $this->state() === self::PUBLISHED;
    }

    /** Whether there is anything here worth a Save Draft or a Publish. */
    public function hasShifts(): bool
    {
        return $this->working()->isNotEmpty();
    }

    /** When this period was last sent, if it ever was. */
    public function publishedAt(): ?CarbonImmutable
    {
        $latest = $this->working()->filter(fn (StaffShift $shift) => $shift->published_at !== null)
            ->max(fn (StaffShift $shift) => $shift->published_at->getTimestamp());

        return $latest === null ? null : CarbonImmutable::createFromTimestamp($latest);
    }

    /**
     * Who last published this period, where the account still exists.
     *
     * Read from the most recently published shift rather than from all of
     * them: a period republished after an edit is one act by one person, and
     * listing everybody who ever pressed the button answers a question nobody
     * asked.
     */
    public function publishedBy(): ?string
    {
        return $this->working()
            ->filter(fn (StaffShift $shift) => $shift->published_at !== null)
            ->sortByDesc(fn (StaffShift $shift) => $shift->published_at->getTimestamp())
            ->first()?->publisher?->name;
    }

    /** @return Collection<string, Collection<int, StaffShift>> */
    public function byDate(): Collection
    {
        return $this->shifts->groupBy(fn (StaffShift $shift) => $shift->date->toDateString());
    }

    public function workingDays(): int
    {
        return $this->working()->groupBy(fn (StaffShift $shift) => $shift->date->toDateString())->count();
    }

    public function totalMinutes(): int
    {
        return (int) $this->working()->sum(fn (StaffShift $shift) => $shift->workedMinutes());
    }

    /** Hours to one decimal, which is how a rota is read and paid. */
    public function totalHours(): float
    {
        return round($this->totalMinutes() / 60, 1);
    }

    /**
     * How many whole weeks the range covers.
     *
     * The page only ever offers 1 to 4, but this is derived from the dates
     * rather than passed in, so a bookmarked range says the same thing as the
     * button that made it.
     */
    public function weeks(): int
    {
        return max(1, (int) ceil(($this->from->diffInDays($this->until) + 1) / 7));
    }

    /**
     * Every day in the range with what is worked on it, for the email.
     *
     * Days off are included: a schedule that listed only the working days
     * would leave the reader counting backwards to find out whether Saturday
     * was theirs.
     *
     * @return array<int, array{date: string, label: string, periods: array<int, string>, hours: float}>
     */
    public function days(): array
    {
        $byDate = $this->byDate();
        $days = [];

        for ($day = $this->from; $day->lte($this->until); $day = $day->addDay()) {
            $onDay = ($byDate[$day->toDateString()] ?? collect())
                ->reject(fn (StaffShift $shift) => $shift->isCancelled());

            $days[] = [
                'date' => $day->toDateString(),
                'label' => $day->translatedFormat('l, M j'),
                'periods' => $onDay->map(fn (StaffShift $shift) => $shift->hoursLabel())->values()->all(),
                'hours' => round($onDay->sum(fn (StaffShift $shift) => $shift->workedMinutes()) / 60, 1),
            ];
        }

        return $days;
    }

    /**
     * The date ranges the listing offers, and what each one covers.
     *
     * Months rather than weeks, because the listing is read to answer "what
     * did this person work this year" — a question a fortnight cannot
     * answer. The assign flow keeps its own week-based durations: planning a
     * rota and reviewing one are different jobs with different natural spans.
     *
     * @return array<string, int> period key => months covered
     */
    public const PERIODS = [
        'month' => 1,
        '3-months' => 3,
        '6-months' => 6,
        '9-months' => 9,
        'year' => 12,
    ];

    /**
     * The range a listing filter names.
     *
     * A month runs to the end of that month rather than a fixed number of
     * days, so February is February; the longer periods run from the first of
     * the chosen month for that many whole months.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function range(string $period, int $year, int $month): array
    {
        $months = self::PERIODS[$period] ?? 1;

        /* Year means the calendar year, not twelve months from the month
           somebody happened to be looking at. */
        $from = $period === 'year'
            ? CarbonImmutable::create($year, 1, 1)->startOfDay()
            : CarbonImmutable::create($year, $month, 1)->startOfDay();

        return [$from, $from->addMonths($months)->subDay()->endOfDay()];
    }

    /** "30 Aug 2026 – 5 Sep 2026", the way the page and the email both say it. */
    public function label(): string
    {
        return $this->from->translatedFormat('j M Y').' – '.$this->until->translatedFormat('j M Y');
    }
}
