<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\TimeFormat;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * One thing that happened to a booking, and why.
 *
 * Written once and never again — no update path, no delete route. An audit
 * trail somebody can edit is not one anybody can rely on, and the whole value
 * of the row is that it still says what it said on the day.
 *
 * The reason is kept twice: the code it was chosen from, and the words that
 * code had at the time. The words are what the timeline prints. A business
 * that renames "Client Did Not Arrive" next spring has renamed their list,
 * not last March's no-show.
 */
class BookingStatusChange extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** Created, and never updated. */
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function reasonCode(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class);
    }

    /** Whoever did it. */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function fromStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'from_staff_id');
    }

    public function toStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'to_staff_id');
    }

    /**
     * Nothing here is ever rewritten.
     *
     * Enforced on the model as well as by the absence of a route, because the
     * next person to add a feature will reach for `->update()` before they
     * read the migration.
     */
    protected static function booted(): void
    {
        static::updating(function (): bool {
            return false;
        });

        static::deleting(function (): bool {
            return false;
        });
    }

    /** Newest first, which is the only order a history is read in. */
    public function scopeNewest(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    /** "Booking marked as no show", in the reader's own language. */
    public function title(): string
    {
        return __('bookings.activity.events.'.$this->to_status);
    }

    /**
     * Who did it, or StyleDesk where nothing did.
     *
     * Null is not missing data: an automated status change and a receptionist
     * pressing a button are different facts, and a blank would read as broken
     * rather than as automatic.
     */
    public function actor(): string
    {
        return $this->changedBy?->name ?? __('bookings.activity.system');
    }

    /** A reschedule, and so a row with two slots on it rather than one. */
    public function movedTheAppointment(): bool
    {
        return $this->from_date !== null && $this->to_date !== null;
    }

    /** "4 Sep 2026 · 10:30 AM", from the halves the row keeps separately. */
    public function slotLabel(string $which): ?string
    {
        $date = $this->{$which.'_date'};
        $time = $this->{$which.'_starts_at'};

        if ($date === null || $time === null) {
            return null;
        }

        /* Through TimeFormat rather than a hard-coded 'h:mm A': 12-hour is the
           default, not the only answer, and a business that chose a 24-hour
           clock reading "2:09 PM" on this one line would be right to call it a
           fault. */
        return $date->isoFormat('D MMM Y').' · '.CarbonImmutable::parse($time)->format(TimeFormat::clock());
    }
}
