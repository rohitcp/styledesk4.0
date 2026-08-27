<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One opening period on one weekday at one location.
 *
 * A period rather than a day: a business that closes for lunch has two rows
 * for Monday, ordered by sort_order. A day with no open row is closed.
 *
 * No BelongsToTenant: this hangs off a location, which is already scoped.
 * Adding the trait would require a tenant_id column that could contradict
 * its parent's.
 */
class LocationHour extends Model
{
    protected $guarded = [];

    protected $attributes = [
        'is_open' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return ['is_open' => 'boolean'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function dayLabel(): string
    {
        return config('locations.weekdays.'.$this->day_of_week, 'Day '.$this->day_of_week);
    }

    /**
     * The stored time as HH:MM, which is what a time input expects.
     *
     * MySQL hands back "09:00:00" and a `time` input silently rejects it,
     * leaving the field blank on an edit screen that had hours saved — the
     * form then posts nothing and the day appears to close itself.
     */
    public function timeValue(string $column): ?string
    {
        $raw = $this->{$column};

        return $raw === null ? null : mb_substr((string) $raw, 0, 5);
    }

    /** "9:00 AM – 6:00 PM", or null on a closed period. */
    public function rangeLabel(): ?string
    {
        if (! $this->is_open || $this->opens_at === null || $this->closes_at === null) {
            return null;
        }

        $format = fn (string $time) => Carbon::createFromFormat('H:i', mb_substr($time, 0, 5))->format('g:i A');

        return $format((string) $this->opens_at).' – '.$format((string) $this->closes_at);
    }
}
