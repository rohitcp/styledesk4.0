<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Currencies;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A membership somebody bought.
 *
 * The plan is what the business offers today; this is what one client agreed
 * to on one day. The price, the frequency and the fees are copied here at the
 * moment of sale and never read back through the plan — a business that
 * repriced its massage membership in June has not repriced what the client
 * who joined in March is paying.
 *
 * The plan id stays, so "what does this include" still has an answer. Only
 * the money is frozen.
 */
class ClientMembership extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /**
     * The states a membership passes through, in the order it meets them.
     *
     * `cancelling` is not one of them and never reaches the column: it is
     * what a membership cancelled at the end of its cycle reads as while that
     * cycle is still running — see status().
     */
    public const STATUSES = ['scheduled', 'active', 'paused', 'cancelled', 'ended'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'next_billing_on' => 'date',
            'cancelled_at' => 'datetime',
            'paused_at' => 'datetime',
            'price_minor' => 'integer',
            'joining_fee_minor' => 'integer',
            'setup_fee_minor' => 'integer',
        ];
    }

    /* ------------------------------------------------------- relations -- */

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function soldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    public function credits(): HasMany
    {
        return $this->hasMany(MembershipCredit::class);
    }

    /**
     * The card renewals reach for.
     *
     * Only meaningful on something that renews: a package has nothing to
     * charge again, which is why the column is nullable rather than
     * required.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(ClientPaymentMethod::class, 'payment_method_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(MembershipPayment::class);
    }

    /* ---------------------------------------------------------- status -- */

    public function isRecurring(): bool
    {
        return $this->type === 'recurring';
    }

    /**
     * Where this membership has got to, today.
     *
     * The stored status, except for the transitions nobody performs. All of
     * them are the passing of a date, and waiting for something to rewrite
     * the column would leave a membership reading "Scheduled" on the morning
     * it began, or "Active" a month after it ended.
     *
     * Order matters. An end date that has passed settles it whatever else is
     * true; after that a pause outranks a pending cancellation, because a
     * paused membership is not billing and so is not counting down.
     */
    public function status(): string
    {
        /* Passed, not reached. A membership "ending on 8 October" is usable
           through the 8th — isPast() would call it over at one minute past
           midnight on the day the client was told they still had. */
        if ($this->ends_on !== null && $this->ends_on->lt(Carbon::today())) {
            return 'ended';
        }

        if (in_array($this->status, ['cancelled', 'ended', 'paused'], true)) {
            return $this->status;
        }

        /* Cancelled at the end of the cycle: the client asked to stop, the
           cycle they paid for is still running, and both of those are true
           at once. Neither "Active" nor "Cancelled" says so. */
        if ($this->cancelled_at !== null) {
            return 'cancelling';
        }

        if ($this->status === 'scheduled' && ! $this->starts_on->isFuture()) {
            return 'active';
        }

        return $this->status;
    }

    public function statusLabel(): string
    {
        return __('membership.member_statuses.'.$this->status());
    }

    public function statusClass(): string
    {
        return match ($this->status()) {
            'active' => 'styledesk_badge--active',
            'scheduled' => 'styledesk_badge--info',
            'paused', 'cancelling' => 'styledesk_badge--setup',
            default => 'styledesk_badge--danger',
        };
    }

    /**
     * Whether its benefits apply and its credits can be spent right now.
     *
     * A membership cancelled at the end of its cycle still is: the client
     * paid for the month they are standing in, and taking their credits away
     * the moment they ask to leave is the one refund conversation no salon
     * wants.
     */
    public function isLive(): bool
    {
        return in_array($this->status(), ['active', 'cancelling'], true);
    }

    /** Whether somebody has asked to end it, whenever that takes effect. */
    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null || in_array($this->status(), ['cancelled', 'ended'], true);
    }

    public function isPaused(): bool
    {
        return $this->status() === 'paused';
    }

    /**
     * The earliest date this may be cancelled, given the commitment period.
     *
     * Null where there is none. Counted from the start date rather than the
     * sale: a membership dated forward has not begun serving its commitment
     * on the day it was bought.
     */
    public function commitmentEndsOn(MembershipSettings $settings): ?Carbon
    {
        $months = (int) $settings->minimum_commitment_months;

        return $months < 1
            ? null
            : $this->starts_on->copy()->addMonthsNoOverflow($months);
    }

    /**
     * When a cancellation asked for today would actually take effect.
     *
     * The notice period first — a business owed thirty days' warning gets
     * them — and then the business's own answer about which cycle that lands
     * in. "Immediately" means the day the notice runs out, not today, or the
     * notice period would be a setting that does nothing.
     */
    public function cancellationTakesEffect(MembershipSettings $settings): Carbon
    {
        $earliest = Carbon::today()->addDays((int) $settings->cancellation_notice_days);

        if ($settings->cancellation_effective === 'immediately') {
            return $earliest;
        }

        /* End of cycle: the day before the next payment would have been
           taken, or the notice date where that is later — a client who gives
           notice with two days of the cycle left does not get to stay a month
           longer than they asked to. */
        $cycleEnd = $this->next_billing_on?->copy()->subDay();

        return $cycleEnd === null || $cycleEnd->lt($earliest) ? $earliest : $cycleEnd;
    }

    /* ----------------------------------------------------------- money -- */

    /** "$79 / month", or "$150" for something bought once. */
    public function priceLabel(): string
    {
        $price = Money::format($this->price_minor / 100, $this->currency_code ?: Currencies::resolve());

        return $this->isRecurring()
            ? __('membership.price_per', [
                'price' => $price,
                'period' => __('membership.periods.'.$this->billing_frequency),
            ])
            : $price;
    }

    /**
     * What was taken at the till today: the first cycle and any one-off fees.
     *
     * Worked out rather than stored, because it is the sum of three columns
     * that are already here and a fourth would be a number that can disagree
     * with them.
     */
    public function dueTodayMinor(): int
    {
        return $this->price_minor
            + (int) $this->joining_fee_minor
            + (int) $this->setup_fee_minor;
    }

    /** What has actually been collected against it. */
    public function paidMinor(): int
    {
        return (int) $this->payments()->where('status', 'paid')->sum('amount_minor');
    }

    /* --------------------------------------------------------- billing -- */

    /**
     * When the next payment is due, counted from a date.
     *
     * Null for a package, which never bills again — its next billing date is
     * not "none yet", it is a question that does not apply.
     */
    public function billingDateAfter(Carbon $from): ?Carbon
    {
        if (! $this->isRecurring() || $this->billing_frequency === null) {
            return null;
        }

        return $from->copy()->addMonthsNoOverflow(
            MembershipSettings::monthsFor($this->billing_frequency)
        );
    }

    /* ---------------------------------------------------------- scopes -- */

    /**
     * Memberships whose benefits apply today.
     *
     * Grouped, because this scope is used inside whereIn subqueries and a
     * bare orWhere would escape any condition applied alongside it — which
     * is how a client's credit query would start returning everybody's.
     *
     * A pending cancellation is still live: the client paid for the cycle
     * they are standing in. An end date that has passed is not, whatever the
     * column still says.
     */
    public function scopeLive(Builder $query): Builder
    {
        $today = Carbon::today()->toDateString();

        return $query->where(fn (Builder $live) => $live
            ->whereIn('status', ['active', 'scheduled'])
            ->whereDate('starts_on', '<=', $today)
            ->where(fn (Builder $q) => $q
                ->whereNull('ends_on')
                ->orWhereDate('ends_on', '>=', $today)));
    }

    public function scopeOfStatus(Builder $query, string $status): Builder
    {
        $today = Carbon::today()->toDateString();

        return match ($status) {
            /* Scheduled means scheduled *and still in the future*: one whose
               day has come reads as active everywhere else, and a filter that
               disagreed with the badge beside it would be a filter nobody
               trusts. */
            'scheduled' => $query->where('status', 'scheduled')->whereDate('starts_on', '>', $today),
            'active' => $query->live()->whereNull('cancelled_at'),
            /* Asked to end, still running. Not a stored value — see status(). */
            'cancelling' => $query->live()->whereNotNull('cancelled_at'),
            'ended' => $query->where(fn (Builder $q) => $q
                ->where('status', 'ended')
                ->orWhereDate('ends_on', '<', $today)),
            default => $query->where('status', $status),
        };
    }
}
