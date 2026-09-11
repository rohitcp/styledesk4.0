<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * One text message, and where it got to.
 *
 * Created before the provider is called and updated by what the carrier says
 * afterwards. "Accepted by the API" and "arrived on a phone" are different
 * facts and this holds both, because the desk is asked the second one.
 */
class SmsMessage extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /**
     * Where a message can get to.
     *
     * `queued` is written down and not yet handed over; `sent` is the
     * provider's acceptance; `delivered` is the carrier's. The three failures
     * are kept apart because only one of them is worth trying again —
     * `failed` may be a moment's trouble, while `rejected` and `opted_out`
     * are answers that will not change on a retry.
     */
    public const STATUSES = [
        'queued', 'sending', 'sent', 'delivered',
        'failed', 'rejected', 'expired', 'opted_out',
    ];

    /** Which way it went. */
    public const DIRECTIONS = ['outbound', 'inbound'];

    /**
     * Where a client's reply can get to.
     *
     * `needs_review` is the default rather than the exception: a reply is
     * only settled where StyleDesk both knows which conversation it belongs
     * to and knows what to do with it.
     */
    public const REPLY_STATUSES = ['handled', 'needs_review'];

    /** The ones a retry could still turn into a delivery. */
    public const RETRYABLE = ['failed', 'expired'];

    protected function casts(): array
    {
        return [
            'segments' => 'integer',
            'reply_candidates' => 'array',
            'handled_at' => 'datetime',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /* ------------------------------------------------------- relations -- */

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(ClientMembership::class, 'client_membership_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ---------------------------------------------------------- reading -- */

    public function statusLabel(): string
    {
        return __('sms.statuses.'.$this->status.'.label');
    }

    public function statusClass(): string
    {
        return config('sms.statuses.'.$this->status.'.class', 'styledesk_badge--soon');
    }

    public function typeLabel(): string
    {
        return __('sms.types.'.$this->type);
    }

    /** Did it get somewhere, as far as anybody has been told? */
    public function hasArrived(): bool
    {
        return $this->status === 'delivered';
    }

    /**
     * Is another attempt worth making?
     *
     * An invalid number and an opt-out are answers, not accidents: retrying
     * either is spending money to be told the same thing again, and retrying
     * an opt-out is texting somebody who asked you not to.
     */
    public function canRetry(): bool
    {
        return in_array($this->status, self::RETRYABLE, true);
    }

    /* ----------------------------------------------------------- scopes -- */

    public function scopeFor(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeInbound(Builder $query): Builder
    {
        return $query->where('direction', 'inbound');
    }

    public function scopeOutbound(Builder $query): Builder
    {
        return $query->where('direction', 'outbound');
    }

    /** Replies waiting on a person. */
    public function scopeNeedingReview(Builder $query): Builder
    {
        return $query->inbound()->where('reply_status', 'needs_review');
    }

    /** Everything still on its way, which is what a webhook may still move. */
    public function scopeInFlight(Builder $query): Builder
    {
        return $query->whereIn('status', ['queued', 'sending', 'sent']);
    }
}
