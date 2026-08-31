<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A reusable working pattern.
 *
 * "Standard Full-Time": forty hours a week at most, an hour for lunch, ten
 * hours' rest between shifts. A template and nothing more — a staff schedule
 * is what applies it to a person and generates the dated shifts, and editing
 * one of those shifts must never reach back and change the pattern everybody
 * else is on. That separation is the reason this is its own record rather
 * than a set of columns on a schedule.
 *
 * A rule keeps no hours of its own. When the business is open is one fact,
 * held once in location_hours and edited at App Settings → Business → Working
 * Hours; a rule that stored a copy would be a second answer, free to drift
 * the moment somebody changed the business's Monday and not the rule's. So a
 * rule says what a schedule built from it may do — limits, breaks, rest,
 * overtime — and the hours it works within are the business's.
 */
class ShiftRule extends Model
{
    use BelongsToTenant;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $guarded = [];

    /**
     * Mirrors the database defaults, so a model just created reads the same
     * as the row behind it rather than reporting nulls the database filled in.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'location_scope' => 'all',
        'break_type' => 'none',
        'allow_overtime' => false,
        'allow_adjustment' => true,
        'allow_split_shift' => false,
        'allow_same_employee_multiple_periods' => false,
        'max_periods_per_employee_per_day' => 2,
    ];

    protected function casts(): array
    {
        return [
            'allow_overtime' => 'boolean',
            'allow_adjustment' => 'boolean',
            'allow_split_shift' => 'boolean',
            'allow_same_employee_multiple_periods' => 'boolean',
            'max_periods_per_employee_per_day' => 'integer',
            'min_gap_minutes' => 'integer',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    /**
     * The named periods the business day is divided into.
     *
     * Only meaningful while allow_split_shift is on. Kept when it goes off,
     * so switching it back on does not cost the reader the periods they
     * wrote — the same reading as a service's resource mapping.
     */
    public function shiftPeriods(): HasMany
    {
        return $this->hasMany(ShiftPeriod::class)->orderBy('sort_order')->orderBy('starts_at');
    }

    /** Only meaningful while the scope is 'specific'. */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function statusLabel(): string
    {
        return __('shift_rules.statuses.'.$this->status);
    }

    public function statusClass(): string
    {
        return config('shift_rules.statuses.'.$this->status.'.class', 'styledesk_badge--soon');
    }

    /**
     * The branch whose working hours this rule operates within.
     *
     * The one it names when it names exactly one, and the business's primary
     * branch otherwise — a rule that applies everywhere is still displayed
     * against a real week, and the primary branch is the one a business
     * answers "what are your hours" with.
     */
    public function hoursLocation(): ?Location
    {
        if ($this->location_scope === 'specific' && $this->locations->count() === 1) {
            return $this->locations->first();
        }

        return Location::query()->orderByDesc('is_primary')->orderBy('name')->first();
    }

    /**
     * The weekdays worked, as day numbers.
     *
     * The business's open days, not the rule's own: a rule holds no hours,
     * and a second list here would be a second answer able to disagree with
     * App Settings → Business → Working Hours.
     *
     * @return array<int, int>
     */
    public function workingDays(?Location $location = null): array
    {
        return $this->openHours($location)
            ->pluck('day_of_week')->unique()->sort()->values()->all();
    }

    /**
     * The open periods of the week this rule reads, in day order.
     *
     * @return Collection<int, LocationHour>
     */
    public function openHours(?Location $location = null): Collection
    {
        $location ??= $this->hoursLocation();

        if ($location === null) {
            return collect();
        }

        return $location->hours->where('is_open', true)->values();
    }

    /**
     * "Mon–Fri", or the days themselves when they are not a run.
     *
     * A rota is scanned, not read: five separate day names in a table column
     * is something to decode, where "Mon–Fri" is recognised at a glance.
     */
    public function workingDaysLabel(?Location $location = null): string
    {
        $days = $this->workingDays($location);

        if ($days === []) {
            return __('shift_rules.no_working_days');
        }

        $names = collect($days)->map(fn (int $day) => __('shift_rules.weekdays_short.'.$day));

        /* A run of three or more consecutive days is worth collapsing; two is
           not — "Mon–Tue" is longer than "Mon, Tue" and says less. */
        $isRun = count($days) >= 3
            && count($days) - 1 === $days[count($days) - 1] - $days[0];

        return $isRun
            ? $names->first().'–'.$names->last()
            : $names->implode(', ');
    }

    /**
     * The hours the pattern keeps, when every working day keeps the same ones.
     *
     * A rule whose days differ has no single answer, and inventing one — the
     * first day's hours, say — would be a column quietly describing one
     * seventh of the rule.
     */
    public function defaultHoursLabel(?Location $location = null): string
    {
        $byDay = $this->openHours($location)->groupBy('day_of_week')
            ->map(fn (Collection $periods) => $periods->map->rangeLabel()->implode(', '));

        if ($byDay->isEmpty()) {
            return __('shift_rules.no_working_days');
        }

        return $byDay->unique()->count() === 1
            ? $byDay->first()
            : __('shift_rules.hours_vary');
    }

    /** Minutes the business is open across the week, before any break. */
    public function weeklyMinutes(?Location $location = null): int
    {
        return $this->openHours($location)->sum(
            fn (LocationHour $hour) => static::minutesBetween(
                $hour->timeValue('opens_at'), $hour->timeValue('closes_at'),
            )
        );
    }

    /** "09:30" to "17:00" as a count of minutes. */
    public static function minutesBetween(?string $from, ?string $to): int
    {
        if ($from === null || $to === null) {
            return 0;
        }

        $read = function (string $time): int {
            [$hours, $minutes] = array_map('intval', array_pad(explode(':', $time), 2, 0));

            return $hours * 60 + $minutes;
        };

        return max(0, $read($to) - $read($from));
    }

    /** The people on this pattern. */
    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    /**
     * How many staff are on this pattern.
     *
     * One method, so the card, the form and the delete guard all count from
     * the same place rather than three — and so the answer changed in exactly
     * one place when staff gained a rule to be assigned to.
     */
    public function assignedStaffCount(): int
    {
        /* The eager-loaded count when the caller asked for one — the rules
           page draws every card, and a query per card is a query per card. */
        return (int) ($this->staff_count ?? $this->staff()->count());
    }

    /**
     * Whether this rule may be offered for a new assignment.
     *
     * Active, and — where it names branches — one that includes the branch in
     * question. An inactive rule already on somebody stays on them; it is new
     * assignments it is kept out of.
     */
    public function isAvailableAt(?int $locationId): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->location_scope !== 'specific') {
            return true;
        }

        /* A rule restricted to branches cannot be given to somebody who is
           not at one of them — and somebody with no branch recorded is at
           none of them in particular, so a restricted rule is not theirs. */
        return $locationId !== null && $this->locations->contains('id', $locationId);
    }

    /**
     * The rules a person at this branch may be put on.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public static function availableAt(?int $locationId): \Illuminate\Database\Eloquent\Collection
    {
        return static::query()->active()->with('locations')->orderBy('name')->get()
            ->filter(fn (self $rule) => $rule->isAvailableAt($locationId))
            ->values();
    }

    /**
     * Whether the rule has ever been used.
     *
     * A rule with history is deactivated rather than deleted: the schedules
     * that used it would otherwise be explained by nothing.
     */
    public function isInUse(): bool
    {
        return $this->assignedStaffCount() > 0;
    }
}
