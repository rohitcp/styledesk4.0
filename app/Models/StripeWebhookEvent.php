<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One event Stripe sent, and what StyleDesk did about it.
 *
 * A payments integration that cannot answer "did that webhook arrive, and
 * what happened to it" is one that gets debugged by asking Stripe. This is
 * the log that makes a failed renewal or a missing refund something the desk
 * can be told about rather than something nobody notices.
 *
 * Deliberately not `BelongsToTenant`: webhooks arrive with no authenticated
 * user, and a global scope that silently returned nothing there would look
 * exactly like "never received".
 */
class StripeWebhookEvent extends Model
{
    protected $guarded = [];

    /**
     * `ignored` is an outcome, not a failure.
     *
     * Stripe sends events StyleDesk has no opinion about. Logging those as
     * errors would bury the ones that matter under the ones that do not.
     */
    public const STATUSES = ['received', 'processing', 'completed', 'failed', 'ignored'];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Start a record of an event, or find the one already there.
     *
     * Stripe retries, so the same event id arrives more than once. Recognising
     * it here is what makes the whole chain idempotent: a renewal charged on
     * the first delivery must not be charged again on the second.
     */
    public static function begin(string $eventId, string $type, ?string $accountId = null): self
    {
        $event = static::firstOrNew(['event_id' => $eventId]);

        $event->fill([
            'event_type' => $type,
            'stripe_account_id' => $accountId ?: $event->stripe_account_id,
            'received_at' => $event->received_at ?? now(),
        ]);

        /* Counted on every delivery, including the retries. A row with four
           attempts and no completion is the shape of a problem. */
        $event->attempts = (int) $event->attempts + 1;
        $event->status = 'processing';
        $event->save();

        return $event;
    }

    /** Whether this event has already been dealt with. */
    public function isSettled(): bool
    {
        return in_array($this->status, ['completed', 'ignored'], true);
    }

    public function complete(?string $tenantId = null): void
    {
        $this->forceFill([
            'tenant_id' => $tenantId ?? $this->tenant_id,
            'status' => 'completed',
            'error' => null,
            'processed_at' => now(),
        ])->save();
    }

    /** Nothing to do with it, which is not the same as something going wrong. */
    public function ignore(?string $reason = null): void
    {
        $this->forceFill([
            'status' => 'ignored',
            'error' => $reason,
            'processed_at' => now(),
        ])->save();
    }

    /**
     * It went wrong, and here is what Stripe or StyleDesk said.
     *
     * The message is kept for the desk to read. It is never shown to a
     * client: a raw API error is a description of our integration, not an
     * explanation of their card.
     */
    public function fail(string $error): void
    {
        $this->forceFill([
            'status' => 'failed',
            'error' => mb_substr($error, 0, 2000),
            'processed_at' => now(),
        ])->save();
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }
}
