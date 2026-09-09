<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A business's connected Stripe account.
 *
 * Deliberately not `BelongsToTenant`: this is read during the Connect return
 * from Stripe and inside webhooks, where tenancy is resolved from the user or
 * not at all — and a global scope that silently returned nothing there would
 * look exactly like "not connected".
 */
class TenantStripeAccount extends Model
{
    /** Connected under StyleDesk's platform account. */
    public const MODE_PLATFORM = 'platform';

    /** The business's own Stripe account, on its own key. */
    public const MODE_OWN = 'own';

    protected $guarded = [];

    /* Never rendered back, never logged, never in an exception. A Stripe
       secret key can charge, refund and read every customer on the account. */
    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'charges_enabled' => 'boolean',
            'payouts_enabled' => 'boolean',
            'details_submitted' => 'boolean',
            'requirements' => 'array',
            'connected_at' => 'datetime',
            'synced_at' => 'datetime',
            /* Encrypted at rest. The database is not the only place a key
               could leak from, but it is the one we control. */
            'api_key' => 'encrypted',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Whether this account can actually take money today.
     *
     * Connected is not the same as ready. Stripe verifies a business over
     * hours or days, and an account that has submitted its details but not
     * passed verification cannot be charged against — offering it would fail
     * at the moment a client tries to pay.
     */
    public function canCharge(): bool
    {
        return $this->charges_enabled;
    }

    public function usesOwnKeys(): bool
    {
        return $this->mode === self::MODE_OWN;
    }

    /**
     * The last four of the key, for recognising which one is saved.
     *
     * Never the key. An owner with two Stripe accounts needs to tell them
     * apart; nobody needs to read the secret back out of StyleDesk, and a
     * screen that showed it would be a screen worth attacking.
     */
    public function keyHint(): ?string
    {
        $key = $this->api_key;

        return filled($key) ? '••••'.mb_substr((string) $key, -4) : null;
    }

    /**
     * Connected, and something is wrong or outstanding.
     *
     * Distinct from disconnected: the business believes it is set up, so the
     * screen has to say what is missing rather than showing a Connect button
     * as though nothing had happened.
     */
    public function needsAttention(): bool
    {
        return ! $this->charges_enabled || filled($this->outstanding());
    }

    /**
     * What Stripe is still waiting for, in its own words.
     *
     * Kept rather than replaced with something friendly: "we need more
     * information" helps nobody, and the field names are what an owner has to
     * go and supply.
     *
     * @return array<int, string>
     */
    public function outstanding(): array
    {
        return array_values(array_unique(array_merge(
            $this->requirements['currently_due'] ?? [],
            $this->requirements['past_due'] ?? [],
        )));
    }

    public function statusKey(): string
    {
        return match (true) {
            $this->canCharge() && ! filled($this->outstanding()) => 'connected',
            $this->details_submitted => 'needs_attention',
            default => 'incomplete',
        };
    }
}
