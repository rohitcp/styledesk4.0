<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One named division of the business day — "Morning Shift, 9:00–1:00".
 *
 * The middle of three ideas that must not be folded together:
 *
 *   business working hours   when is the business open?
 *   shift periods            how is that day divided for coverage?
 *   split shift              may one person work more than one of them?
 *
 * A period is a standing division rather than a per-weekday one: the morning
 * shift is the morning shift on Tuesday and on Saturday, and which days it
 * runs on follows from the business's own week.
 *
 * No BelongsToTenant: this hangs off a rule, which is already scoped. A
 * tenant_id here would be a second answer able to contradict its parent's.
 */
class ShiftPeriod extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'break_minutes' => 'integer',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ShiftRule::class, 'shift_rule_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The stored time as HH:MM, which is what the form posts and the picker
     * expects. MySQL hands back "09:00:00", which a time control silently
     * rejects — leaving a saved period looking blank on the edit screen.
     */
    public function timeValue(string $column): string
    {
        return mb_substr((string) $this->{$column}, 0, 5);
    }

    public function minutes(): int
    {
        return ShiftRule::minutesBetween($this->timeValue('starts_at'), $this->timeValue('ends_at'));
    }

    /** Worked minutes: the period less its break. */
    public function workedMinutes(): int
    {
        return max(0, $this->minutes() - (int) $this->break_minutes);
    }

    /** "9:00 AM – 1:00 PM", in the reader's own locale. */
    public function rangeLabel(): string
    {
        $format = fn (string $time) => now()->setTimeFromTimeString($time)->translatedFormat('g:i A');

        return $format($this->timeValue('starts_at')).' – '.$format($this->timeValue('ends_at'));
    }
}
