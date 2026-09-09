<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\TimeFormat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A date, or a run of dates, that does not follow the weekly pattern.
 *
 * A public holiday, a refit, a training day, or a Christmas Eve that closes at
 * four. All of them answer the same question — "what happens at this location
 * on this date" — so they are one record with a type rather than three tables
 * that would each need their own answer for a refit falling on a bank holiday.
 *
 * No BelongsToTenant: this hangs off a location, which is already scoped.
 * Adding the trait would require a tenant_id column that could contradict its
 * parent's.
 */
class LocationClosure extends Model
{
    protected $guarded = [];

    protected $attributes = [
        'type' => 'closure',
        'is_closed_all_day' => true,
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_closed_all_day' => 'boolean',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    // ------------------------------------------------------------- scopes

    /**
     * Exceptions covering a date, whether they start on it or span it.
     *
     * The comparison is against the range rather than starts_on, because a
     * two-week refit is one row and day nine of it is just as closed as day
     * one.
     */
    public function scopeCovering(Builder $query, Carbon|string $date): Builder
    {
        $on = $date instanceof Carbon ? $date->toDateString() : $date;

        return $query->whereDate('starts_on', '<=', $on)->whereDate('ends_on', '>=', $on);
    }

    /**
     * Still to come, including one running right now.
     *
     * `ends_on >= today` rather than `starts_on >= today`: a closure that
     * began last Tuesday and runs until Friday is the most relevant thing on
     * the overview, and filtering on the start date would hide it.
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('ends_on', '>=', now()->toDateString())->orderBy('starts_on');
    }

    // ------------------------------------------------------------ display

    /**
     * The type in the reader's language.
     *
     * The config still decides which types exist — validation reads that same
     * list — and falls back to its own English label, so a type added later
     * reads as itself rather than as a key.
     */
    public function typeLabel(): string
    {
        $key = 'hours.types.'.$this->type;

        return trans()->has($key)
            ? __($key)
            : config('locations.closure_types.'.$this->type.'.label', ucfirst(str_replace('_', ' ', (string) $this->type)));
    }

    public function isSingleDay(): bool
    {
        return $this->starts_on->isSameDay($this->ends_on);
    }

    /** True while the exception covers today. */
    public function isInProgress(): bool
    {
        return now()->startOfDay()->between($this->starts_on, $this->ends_on);
    }

    /**
     * The dates, as a person would write them.
     *
     * "12 March" for one day, "3 – 17 March" for a run. The year appears only
     * when it is not this one, because a calendar full of "2026" tells the
     * reader nothing they did not already assume.
     */
    public function dateLabel(): string
    {
        $format = fn (Carbon $date) => $date->isSameYear(now())
            ? $date->format('j M')
            : $date->format('j M Y');

        return $this->isSingleDay()
            ? $format($this->starts_on)
            : $format($this->starts_on).' – '.$format($this->ends_on);
    }

    /**
     * What actually happens that day.
     *
     * "Closed" or the replacement hours, never a blank: a special-hours row
     * whose times are missing is a row that says nothing, and the form does
     * not allow one.
     */
    public function hoursLabel(): string
    {
        if ($this->is_closed_all_day || $this->opens_at === null || $this->closes_at === null) {
            return __('hours.exceptions.closed_all_day');
        }

        return TimeFormat::range($this->timeValue('opens_at'), $this->timeValue('closes_at'));
    }

    public function timeValue(string $column): ?string
    {
        $raw = $this->{$column};

        return $raw === null ? null : mb_substr((string) $raw, 0, 5);
    }
}
