<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Somebody's working hours on a named date.
 *
 * The dated half of scheduling. A staff schedule is the recurring pattern —
 * Monday to Friday, nine to five — and a shift is one block on 12 September
 * that supplements or overrides it. Kept apart because "she works Tuesdays"
 * and "she is covering this Saturday" are different facts, and writing the
 * second into the first loses the pattern.
 */
class StaffShift extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /**
     * Mirrors the database defaults, so a model just created reads the same
     * as the row behind it rather than reporting nulls the database filled in.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'regular',
        'status' => 'scheduled',
        'publish_status' => 'draft',
        'break_minutes' => 0,
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'published_at' => 'datetime',
            'break_minutes' => 'integer',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** Who published this, where the account still exists to name. */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * A time column comes back as "10:00:00"; everything that reads one wants
     * either the wire format the form posts or something a person can read.
     */
    public function timeValue(string $field): string
    {
        return substr((string) $this->{$field}, 0, 5);
    }

    public function startsAt(): Carbon
    {
        return $this->date->copy()->setTimeFromTimeString($this->timeValue('starts_at'));
    }

    public function endsAt(): Carbon
    {
        return $this->date->copy()->setTimeFromTimeString($this->timeValue('ends_at'));
    }

    /**
     * Worked minutes, which is the length of the shift less the break.
     *
     * The break is unpaid time inside the shift rather than a second pair of
     * times: a rota that stored two blocks either side of lunch would have to
     * decide which of them the shift "is" every time it was asked.
     */
    public function workedMinutes(): int
    {
        /* Cast, because diffInMinutes returns a float in Carbon 3 — and a
           shift is a whole number of minutes. */
        return max(0, (int) $this->startsAt()->diffInMinutes($this->endsAt()) - $this->break_minutes);
    }

    /** "10:00 AM – 4:00 PM", in the reader's own locale. */
    public function hoursLabel(): string
    {
        return $this->startsAt()->translatedFormat('g:i A').' – '.$this->endsAt()->translatedFormat('g:i A');
    }

    public function typeLabel(): string
    {
        return __('shifts.types.'.$this->type);
    }

    public function typeClass(): string
    {
        return config('shifts.types.'.$this->type.'.class', 'styledesk_badge--soon');
    }

    public function statusLabel(): string
    {
        return __('shifts.statuses.'.$this->status);
    }

    public function statusClass(): string
    {
        return config('shifts.statuses.'.$this->status.'.class', 'styledesk_badge--soon');
    }

    /**
     * A cancelled shift is still a fact about the week, but it is not one
     * anybody is working — so it never counts as a clash.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Whether the person working this has been told about it.
     *
     * Not the same question as `status`: a cancelled shift may well have been
     * published, and a scheduled one may be a draft nobody has seen.
     */
    public function isPublished(): bool
    {
        return $this->publish_status === 'published';
    }

    /**
     * A draft that was published once and has since been edited — a change
     * waiting to be communicated rather than a week that was never sent.
     */
    public function hasUnpublishedChanges(): bool
    {
        return ! $this->isPublished() && $this->published_at !== null;
    }

    public function publishStatusLabel(): string
    {
        return __('schedule.publish_statuses.'.$this->publish_status);
    }

    public function scopeInRange(Builder $query, string $from, string $until): Builder
    {
        return $query->whereDate('date', '>=', $from)->whereDate('date', '<=', $until);
    }

    public function scopeOnDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('date', $date);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('date', '>=', now()->toDateString());
    }

    /**
     * Shifts that would overlap this one for the same person.
     *
     * Two shifts touching end-to-end — one finishing at 13:00 and the next
     * starting at 13:00 — do not overlap, which is why the comparisons are
     * strict. A split day is entered as two shifts and must stay legal.
     */
    public function scopeClashingWith(Builder $query, StaffShift $shift): Builder
    {
        return $query
            ->where('staff_id', $shift->staff_id)
            ->whereDate('date', $shift->date->toDateString())
            ->where('status', '!=', 'cancelled')
            ->when($shift->exists, fn (Builder $q) => $q->whereKeyNot($shift->getKey()))
            ->where('starts_at', '<', $shift->ends_at)
            ->where('ends_at', '>', $shift->starts_at);
    }
}
