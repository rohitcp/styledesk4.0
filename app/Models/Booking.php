<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * One appointment.
 *
 * The client is optional and the staff member is optional, and both for the
 * same reason: a booking taken at the desk is often "somebody who is here
 * now" with "whoever is free", and a model that demanded records for either
 * would make the front desk invent them.
 *
 * What it is worth and how long it takes are the sum of its services, which
 * are copied onto their own rows when it is taken — see the migration.
 */
class Booking extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_walk_in' => 'boolean',
            'confirmed_at' => 'datetime',
            'waived_at' => 'datetime',
            'client_snapshot' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(BookingService::class)->orderBy('sort_order');
    }

    public function review(): HasOne
    {
        return $this->hasOne(BookingReview::class);
    }

    /** Whoever was at the desk when it was taken. */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class)->orderBy('id');
    }

    /** The requests to pay that were sent out, newest first. */
    public function paymentLinks(): HasMany
    {
        return $this->hasMany(BookingPaymentLink::class)->latest('id');
    }

    /** Whoever let this booking off the money, where somebody did. */
    public function waivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by');
    }

    /**
     * What has actually been taken, in minor units.
     *
     * Refunds are negative rows, so the sum is what the business is holding
     * rather than what once passed through the till.
     */
    public function paidMinor(): int
    {
        return (int) $this->payments->whereIn('status', ['paid', 'refunded'])->sum('amount_minor');
    }

    /** What is still owed. Never negative: an overpayment is not a debt. */
    public function dueMinor(): int
    {
        return max(0, (int) $this->total_minor - $this->paidMinor());
    }

    /**
     * Work out where the bill stands and write it down.
     *
     * Stored as well as derived because every listing filters on it, and a
     * status computed per row is a status no query can reach.
     */
    public function settlePaymentStatus(): string
    {
        $paid = $this->paidMinor();
        $total = (int) $this->total_minor;

        $status = match (true) {
            $paid <= 0 => $this->payments->contains(fn (BookingPayment $payment) => $payment->status === 'pending')
                ? 'pending'
                : 'unpaid',
            $paid >= $total => 'paid',
            default => 'partial',
        };

        /* Money that went back out is its own answer, and outranks the
           arithmetic: a booking refunded in full is not "unpaid". */
        if ($this->payments->contains(fn (BookingPayment $payment) => $payment->status === 'refunded')) {
            $status = $paid <= 0 ? 'refunded' : 'partially-refunded';
        }

        $this->forceFill(['payment_status' => $status, 'paid_minor' => max(0, $paid)])->save();

        return $status;
    }

    public function paymentStatusLabel(): string
    {
        return __('bookings.payment_statuses.'.$this->payment_status.'.label');
    }

    public function paymentStatusClass(): string
    {
        return config('bookings.payment_statuses.'.$this->payment_status.'.class', 'styledesk_badge--soon');
    }

    /**
     * Who the appointment is with, whether or not they are on file.
     *
     * A walk-in has a name and nothing else, and the screens that list
     * bookings should not have to know which of the two they are looking at.
     */
    public function clientName(): string
    {
        return $this->client?->displayName()
            ?? ($this->guest_name ?: __('bookings.walk_in_guest'));
    }

    public function startsAt(): string
    {
        return substr((string) $this->starts_at, 0, 5);
    }

    public function endsAt(): string
    {
        return substr((string) $this->ends_at, 0, 5);
    }

    /**
     * "10:00 AM – 11:30 AM", in the reader's own locale.
     *
     * A draft saved before a time was chosen has none, and says so. Showing
     * 12:00 AM there would put an appointment in the small hours of every
     * listing that reads this.
     */
    public function timeLabel(): string
    {
        if ($this->startsAt() === '' || $this->endsAt() === '') {
            return __('bookings.no_time_yet');
        }

        return $this->date->copy()->setTimeFromTimeString($this->startsAt())->translatedFormat('g:i A')
            .' – '
            .$this->date->copy()->setTimeFromTimeString($this->endsAt())->translatedFormat('g:i A');
    }

    /**
     * A reference that can be read out over the phone.
     *
     * Handed out the moment the booking screen has a name to save under, and
     * never handed out again: the number a receptionist has already read to
     * somebody has to survive every later save, and the booking a lead
     * becomes keeps the number the lead was quoted under. Dated so it can be
     * found without a search, and numbered within the day so two references
     * taken an hour apart sort in the order they were taken.
     *
     * Counted across both tables, because a booking in progress lives in
     * `booking_leads` and the appointment it becomes lives here — the same
     * number in both, one after the other. Taking the highest either has
     * reached is what stops the second one being issued twice.
     */
    public static function nextReference(): string
    {
        $prefix = 'BK-'.now()->format('Ymd').'-';

        $issued = self::withTrashed()->where('reference', 'like', $prefix.'%')->pluck('reference')
            ->merge(BookingLead::query()->where('reference', 'like', $prefix.'%')->pluck('reference'));

        /* One day's worth, which is a page of rows rather than a table, so
           the highest is read in PHP instead of in four dialects of SQL. */
        $number = (int) $issued->map(fn (string $reference) => (int) substr($reference, strlen($prefix)))->max();

        do {
            $reference = $prefix.str_pad((string) ++$number, 5, '0', STR_PAD_LEFT);
        } while ($issued->contains($reference));

        return $reference;
    }

    /**
     * What has happened to this booking since it was taken.
     *
     * Immutable rows, newest last. They outlive what they describe: a
     * cancelled booking keeps the entry that says it was confirmed first.
     */
    /**
     * The room or chair the appointment is in.
     *
     * One per booking rather than one per service on it: a client lies on one
     * bed for the whole appointment, and a cupping added to a massage happens
     * in the room they are already in.
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(BookingStatusChange::class);
    }

    /**
     * What can be done to this booking now, by this reader.
     *
     * Two gates, and an action has to pass both. The status decides what the
     * act would even mean — marking a completed appointment as a no-show is
     * not a decision anybody should be able to make by mistake — and the
     * permission decides whether this person may make it.
     *
     * Actions a reader may not take are absent rather than disabled: a button
     * they can never enable is furniture, and one that is quietly missing
     * reads as a permission they lack, which here it is.
     *
     * @return array<int, string>
     */
    public function availableActions(?User $user = null): array
    {
        return collect(config('bookings.status_actions'))
            ->filter(fn (array $action) => in_array($this->status, $action['from'], true))
            /* Some acts only mean anything on the day. Checking in for
               Thursday's appointment on Tuesday is not early — it is the
               wrong booking. */
            ->filter(fn (array $action) => empty($action['today']) || $this->isToday())
            ->filter(fn (array $action) => $user === null || $user->hasPermission($action['permission'], 'own'))
            ->keys()
            ->all();
    }

    /** The appointment is today, in the business's own reckoning of today. */
    public function isToday(): bool
    {
        return $this->date?->isSameDay(now()) === true;
    }

    public function allows(string $action, ?User $user = null): bool
    {
        return in_array($action, $this->availableActions($user), true);
    }

    /**
     * When the client arrived, and who said so.
     *
     * Read from the history rather than from a pair of columns on this row:
     * the entry is written once and never rewritten, so it still says what it
     * said even after the booking has moved on to completed.
     */
    public function checkIn(): ?BookingStatusChange
    {
        return $this->statusChanges()
            ->where('to_status', 'arrived')
            ->with('changedBy')
            ->orderBy('created_at')
            ->first();
    }

    /** Called off, turned down, or nobody came: the diary is done with it. */
    public function isSettled(): bool
    {
        return in_array($this->status, ['cancelled', 'declined', 'no-show', 'completed'], true);
    }

    public function statusLabel(): string
    {
        return __('bookings.statuses.'.$this->status.'.label');
    }

    public function statusClass(): string
    {
        return config('bookings.statuses.'.$this->status.'.class', 'styledesk_badge--soon');
    }

    /** Today and after, which is what a desk is asked for. */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('date', '>=', now()->toDateString());
    }

    public function scopeOnDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('date', $date);
    }
}
