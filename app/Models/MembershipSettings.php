<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Whether this business sells memberships, and on what terms.
 *
 * Three questions and their boundaries: where a membership may be bought,
 * what happens to a credit nobody used, and what cancelling one means. What a
 * particular membership costs and includes is not here — that is a plan, and
 * a plan is a row of its own.
 *
 * Switching membership off stops the selling. It erases nothing: existing
 * members keep their plans, their credits and their history, exactly as they
 * were when the business comes back.
 */
class MembershipSettings extends Model
{
    use BelongsToTenant;

    protected $table = 'membership_settings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'allow_purchase_in_store' => 'boolean',
            'allow_purchase_online' => 'boolean',
            'allow_staff_to_sell' => 'boolean',
            'allow_start_date_selection' => 'boolean',
            'credits_enabled' => 'boolean',
            'reset_credits_on_cycle' => 'boolean',
            'allow_rollover' => 'boolean',
            'allow_credits_across_locations' => 'boolean',
            'allow_service_substitution' => 'boolean',
            'allow_cancellation' => 'boolean',
            'allow_pause' => 'boolean',
        ];
    }

    /**
     * This business's settings, whether or not anybody has saved any.
     *
     * Unsaved rather than created on read, the same way LoyaltySettings and
     * ReviewSettings work: a business that has never opened the screen has no
     * row, and reading the defaults must not quietly write one. The screen's
     * save is what makes it real.
     */
    public static function forTenant(?Tenant $tenant): self
    {
        $existing = $tenant === null
            ? null
            : self::query()->where('tenant_id', $tenant->getTenantKey())->first();

        return $existing ?? new self(self::defaults() + [
            'tenant_id' => $tenant?->getTenantKey(),
        ]);
    }

    /**
     * The terms a business gets before it has chosen any.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $defaults = config('membership.defaults');

        return [
            'is_enabled' => false,

            'allow_purchase_in_store' => true,
            'allow_purchase_online' => false,
            'allow_staff_to_sell' => true,
            'allow_start_date_selection' => true,
            'default_activation' => $defaults['default_activation'],

            /* On, because every membership that exists includes services.
               Off is the discount-only membership: perks, no credits. */
            'credits_enabled' => true,

            'reset_credits_on_cycle' => true,
            'allow_rollover' => false,
            'maximum_rollover' => null,
            'credit_expiry' => $defaults['credit_expiry'],
            'allow_credits_across_locations' => true,
            'allow_service_substitution' => false,

            'allow_cancellation' => true,
            'allow_pause' => false,
            'minimum_commitment_months' => $defaults['minimum_commitment_months'],
            'cancellation_notice_days' => $defaults['cancellation_notice_days'],
            'cancellation_effective' => $defaults['cancellation_effective'],
        ];
    }

    /** Every membership type, recurring and package. */
    public static function types(): array
    {
        return array_keys(config('membership.types'));
    }

    /** How often a recurring membership may bill. */
    public static function billingFrequencies(): array
    {
        return array_keys(config('membership.billing_frequencies'));
    }

    public static function activations(): array
    {
        return config('membership.activation');
    }

    public static function creditExpiries(): array
    {
        return array_keys(config('membership.credit_expiry'));
    }

    public static function cancellationTimings(): array
    {
        return config('membership.cancellation');
    }

    /** Every purchase channel, including the ones nothing can sell through yet. */
    public static function channels(): array
    {
        return array_keys(config('membership.channels'));
    }

    /** The ones a save may actually switch on. */
    public static function availableChannels(): array
    {
        return collect(config('membership.channels'))
            ->filter(fn (array $channel) => $channel['available'])
            ->keys()
            ->all();
    }

    /** How many months a billing frequency covers. */
    public static function monthsFor(string $frequency): int
    {
        return (int) (config('membership.billing_frequencies.'.$frequency.'.months') ?? 1);
    }

    /**
     * Can this business sell a membership at all right now?
     *
     * Enabled is not the whole answer: a business that switched membership on
     * and then closed every channel has nowhere to sell one, and the booking
     * screen should not offer a purchase type that leads to an empty list.
     */
    public function canSell(): bool
    {
        return $this->is_enabled
            && ($this->allow_purchase_in_store || $this->allow_purchase_online);
    }

    /**
     * Does a membership here include anything to draw down?
     *
     * The question above every other credit setting. Off, and what the client
     * buys is the discount and the standing — a real product, and one every
     * screen has to stop asking credit questions about.
     */
    public function grantsCredits(): bool
    {
        return (bool) $this->credits_enabled;
    }

    /**
     * Does an unused credit survive the end of its cycle?
     *
     * Rollover and reset are the same question asked from opposite ends, and
     * a business can only have one answer. Rollover wins where both are set,
     * because it is the one somebody had to deliberately turn on.
     */
    public function rollsOver(): bool
    {
        return $this->allow_rollover;
    }
}
