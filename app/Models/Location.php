<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection as SupportCollection;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A place the business operates from. Onboarding creates the primary one.
 */
class Location extends Model
{
    use BelongsToTenant;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /**
     * The effective date rows carry when a business never set one.
     *
     * Mirrors the migration's own epoch. Named here too because the model is
     * where callers look for it, and two copies of a magic date that disagree
     * would silently split one schedule into two.
     */
    public const EPOCH = '2000-01-01';

    protected $guarded = [];

    /**
     * Defaults the instance carries, not only the table.
     *
     * A column default is applied by the database and is not reflected on the
     * model that was just created, so `Location::create([...])->status` read
     * back empty and the badge rendered blank on the screen that had only
     * just saved it.
     */
    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'is_primary' => false,
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    // ---------------------------------------------------------- relations

    /**
     * The opening hours in force today, ordered as they are read.
     *
     * Scoped to the current schedule rather than every row: once a business
     * enters next month's hours the table holds two weeks at once, and a
     * relation returning both would show a location open twice on Monday.
     *
     * The schedule is picked by a correlated subquery, not by reading
     * $this->id. Eager loading calls this on a bare instance with no id, so a
     * constraint built from the instance would quietly resolve to "no
     * location" and hand every row back the epoch schedule — the current
     * hours would look right on a page that loaded one location and wrong on
     * the page that lists them all.
     *
     * Split days mean several rows per weekday, so the order is part of the
     * data rather than an incidental artefact of insertion: 2–7pm appearing
     * above 9am–1pm would read as a mistake in the business's own hours.
     */
    public function hours(): HasMany
    {
        return $this->hasMany(LocationHour::class)
            ->whereRaw(
                'location_hours.effective_from = ('
                .' select max(current_schedule.effective_from) from location_hours as current_schedule'
                .' where current_schedule.location_id = location_hours.location_id'
                .' and current_schedule.effective_from <= ?)',
                [now()->toDateString()]
            )
            ->orderBy('day_of_week')->orderBy('sort_order');
    }

    /** Every row, across every schedule. Used when rewriting or comparing. */
    public function allHours(): HasMany
    {
        return $this->hasMany(LocationHour::class)
            ->orderBy('effective_from')->orderBy('day_of_week')->orderBy('sort_order');
    }

    /** One named schedule, current or future. */
    public function scheduleHours(?string $effectiveFrom): HasMany
    {
        return $this->hasMany(LocationHour::class)
            ->where('effective_from', $effectiveFrom ?? self::EPOCH)
            ->orderBy('day_of_week')->orderBy('sort_order');
    }

    public function closures(): HasMany
    {
        return $this->hasMany(LocationClosure::class)->orderBy('starts_on');
    }

    /**
     * The effective date of the schedule in force on a day.
     *
     * The latest one that has already started. A schedule dated next month is
     * deliberately not it — that is the whole point of entering it early.
     */
    public function currentScheduleDate(): ?string
    {
        $date = LocationHour::query()
            ->where('location_id', $this->id)
            ->whereDate('effective_from', '<=', now()->toDateString())
            ->max('effective_from');

        return $date === null ? null : mb_substr((string) $date, 0, 10);
    }

    /**
     * Schedules dated in the future, earliest first.
     *
     * Surfaced rather than left silent: a business that entered next month's
     * hours and then forgot is one that will be surprised on the first of the
     * month, and the screen should say so before then.
     *
     * @return SupportCollection<int, string>
     */
    public function futureScheduleDates(): SupportCollection
    {
        return LocationHour::query()
            ->where('location_id', $this->id)
            ->whereDate('effective_from', '>', now()->toDateString())
            ->distinct()
            ->orderBy('effective_from')
            ->pluck('effective_from')
            ->map(fn ($date) => mb_substr((string) $date, 0, 10));
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'manager_staff_id');
    }

    public function assistantManagers(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'location_assistant_manager', 'location_id', 'staff_id');
    }

    /** Staff whose primary location this is. */
    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    /**
     * Whether anything in the business still points at this branch.
     *
     * The spec's list — bookings, transactions, staff assignments, client
     * records — is longer than what exists today, so this checks what there
     * is and grows as those tables arrive. Erring towards "in use" is the
     * safe direction: refusing a delete costs a click, while allowing one
     * costs the history the row was holding.
     */
    public function isInUse(): bool
    {
        return $this->staff()->withoutGlobalScopes()->exists()
            || $this->assistantManagers()->exists()
            || TeamInvitation::withoutGlobalScopes()->where('location_id', $this->id)->exists();
    }

    // ------------------------------------------------------------- scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * The order the list is read in: primary first, then alphabetical.
     *
     * The primary branch is the one people are looking for most of the time,
     * and alphabetical alone would bury it under whichever branch happens to
     * start with an A.
     */
    public function scopeInDisplayOrder(Builder $query): Builder
    {
        return $query->orderByDesc('is_primary')->orderBy('name');
    }

    // ------------------------------------------------------------ display

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function statusLabel(): string
    {
        return config('locations.statuses.'.$this->status.'.label', ucfirst((string) $this->status));
    }

    public function statusClass(): string
    {
        return config('locations.statuses.'.$this->status.'.class', 'styledesk_badge--soon');
    }

    public function typeLabel(): ?string
    {
        return $this->type ? config('locations.types.'.$this->type, $this->type) : null;
    }

    public function countryName(): ?string
    {
        return $this->country ? config('locations.countries.'.$this->country, $this->country) : null;
    }

    /**
     * The address on one line, for a list row or a summary.
     *
     * Empty parts are dropped rather than left as stray commas: "Downtown, ,
     * NY" reads as a data fault, when the truth is only that there is no
     * second address line.
     */
    public function addressLine(): string
    {
        return collect([
            $this->address_line1,
            $this->address_line2,
            $this->suite,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->countryName(),
        ])->filter(fn ($part) => filled($part))->join(', ');
    }

    /**
     * Today's opening periods, in this location's own time zone.
     *
     * The tenant's time zone is the wrong clock for a branch in another one:
     * a London office reading "today" for a Sydney salon would show the wrong
     * day's hours for most of the working day.
     *
     * @return Collection<int, LocationHour>
     */
    public function hoursForToday(): Collection
    {
        $today = now($this->timezone ?: config('app.timezone'))->dayOfWeek;

        return $this->hours
            ->where('day_of_week', $today)
            ->where('is_open', true)
            ->values();
    }

    /**
     * Today's hours as one readable string.
     *
     * Returns "Closed" rather than an em dash or a blank: a location that is
     * shut today is a fact, and the list should say it rather than leave a
     * gap that reads as missing data.
     */
    public function todayLabel(): string
    {
        $periods = $this->hoursForToday();

        if ($periods->isEmpty()) {
            return 'Closed today';
        }

        return $periods->map(fn (LocationHour $hour) => $hour->rangeLabel())->join(', ');
    }
}
