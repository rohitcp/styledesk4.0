<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * What the client thought of one appointment, and what the business did about
 * it.
 *
 * The row now begins before there is anything to read: it is written when the
 * appointment is completed, carrying the link that will be sent and the time
 * the asking is due, and it stays unanswered until somebody taps a star. So
 * `rating` is nullable and `submitted_at` is the only honest test of whether
 * this is a review at all.
 *
 * Whole stars, and the staff member, service and location are stored on the
 * review rather than read through the booking: the review is about who did the
 * work and where, and a booking reassigned afterwards must not quietly move
 * the compliment with it.
 */
class BookingReview extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'contact_requested' => 'boolean',
            'reviewed_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'submitted_at' => 'datetime',
            'google_opened_at' => 'datetime',
            'assigned_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
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

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * A token nobody can guess.
     *
     * The link is the whole credential: anybody holding it can rate the
     * appointment it names, without signing in. So it is 64 characters of
     * randomness rather than an id, and it is never derived from anything
     * about the booking or the client — §8 asks for exactly that.
     */
    public static function newToken(): string
    {
        do {
            $token = Str::random(64);
        } while (self::withoutGlobalScopes()->where('token', $token)->exists());

        return $token;
    }

    /** Answered, whatever else the row says. */
    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    /**
     * Four stars and up.
     *
     * The line between the two journeys, read from config rather than written
     * as `>= 4` in the controller, the page and the report — three copies of
     * one judgement is how they come to disagree.
     */
    public function isPositive(): bool
    {
        return $this->rating !== null
            && $this->rating >= (int) config('reviews.positive_from');
    }

    /** Unhappy, and not yet dealt with. The "Needs attention" list. */
    public function needsAttention(): bool
    {
        return $this->isSubmitted()
            && ! $this->isPositive()
            && ! in_array($this->status, ['resolved', 'closed', 'no_action'], true);
    }

    /**
     * Where the asking has got to.
     *
     * Worked out rather than read, the way a payment link's is: the row holds
     * three clocks and the answer is which of them has struck. A column would
     * have to be kept in step by every writer, and one that forgot would show
     * "Sent" for a request still sitting in the queue.
     */
    public function requestStatus(): string
    {
        if ($this->isSubmitted()) {
            return 'completed';
        }

        if ($this->sent_at !== null) {
            return 'sent';
        }

        if ($this->scheduled_for !== null) {
            return 'scheduled';
        }

        return 'not_requested';
    }

    public function statusLabel(): string
    {
        return __('reviews.statuses.'.$this->status);
    }

    public function statusClass(): string
    {
        return config('reviews.statuses.'.$this->status.'.class', 'styledesk_badge--soon');
    }
}
