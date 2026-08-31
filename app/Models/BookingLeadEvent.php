<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * One thing that happened to a lead.
 *
 * Append-only, and never updated: an event is a fact about the past.
 */
class BookingLeadEvent extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /* Only ever created, so there is nothing for `updated_at` to record. */
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(BookingLead::class, 'booking_lead_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The line a person reads.
     *
     * The detail is folded in where the wording needs it — which step, which
     * reason — and left out where the kind says everything.
     */
    public function label(): string
    {
        return match ($this->kind) {
            'step' => __('leads.events.step', ['step' => __('leads.steps.'.$this->detail)]),
            'cancelled' => __('leads.events.cancelled', ['reason' => __('leads.reasons.'.$this->detail)]),
            default => __('leads.events.'.$this->kind),
        };
    }
}
