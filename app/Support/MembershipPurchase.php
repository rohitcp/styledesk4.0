<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\ClientPaymentMethod;
use App\Models\Location;
use App\Models\MembershipCredit;
use App\Models\MembershipPayment;
use App\Models\MembershipPlan;
use App\Models\MembershipPlanService;
use App\Models\MembershipSettings;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Selling a membership.
 *
 * One place, because a sale is three writes that are only correct together: a
 * membership, the credits it grants, and the money taken for it. A screen
 * that did them in sequence would eventually leave a client holding a
 * membership with no credits, or credits nobody paid for.
 *
 * What the client agreed to is copied onto the membership rather than read
 * back through the plan. The plan is what the business offers today; the
 * membership is what one person was told on one day, and a repricing in June
 * must not reach into March.
 */
class MembershipPurchase
{
    /**
     * Sell a plan to a client.
     *
     * @param  array{method: string, amount_minor: int, reference?: ?string}|null  $payment
     *                                                                                       What was taken at the till, or null where nothing was.
     */
    public static function sell(
        MembershipPlan $plan,
        Client $client,
        Carbon $startsOn,
        ?Location $location = null,
        ?array $payment = null,
        ?User $seller = null,
        ?ClientPaymentMethod $card = null,
        bool $autoRenew = false,
    ): ClientMembership {
        $settings = MembershipSettings::forTenant($client->tenant);

        /* Only a plan that bills again can renew, whatever the form said.
           A package with auto-renew on is a subscription to something that
           has already finished. */
        $renews = $autoRenew && $plan->isRecurring();

        return DB::transaction(function () use ($plan, $client, $startsOn, $location, $payment, $seller, $settings, $card, $renews) {
            $membership = ClientMembership::create([
                'tenant_id' => $client->tenant_id,
                'client_id' => $client->id,
                'membership_plan_id' => $plan->id,
                'location_id' => $location?->id,

                /* A membership dated forward is Scheduled, not Active: its
                   credits do not exist and its benefits do not apply until
                   the start date arrives. */
                'status' => $startsOn->isFuture() ? 'scheduled' : 'active',
                'starts_on' => $startsOn->toDateString(),

                /* Frozen at the moment of sale. */
                'type' => $plan->type,
                'price_minor' => (int) $plan->price_minor,
                'currency_code' => Currencies::resolve(),
                'billing_frequency' => $plan->billing_frequency,
                'joining_fee_minor' => $plan->joining_fee_minor,
                'setup_fee_minor' => $plan->setup_fee_minor,

                /* The first renewal is a cycle after it starts, not a cycle
                   after it was sold: a membership bought on the 1st to begin
                   on the 15th renews on the 15th. A trial pushes it further
                   out, which is the whole of what a trial is. */
                /* When the next payment falls due, whoever takes it.
                 *
                 * Recorded for every recurring membership, not only the ones
                 * StyleDesk charges automatically: a business with no
                 * processor connected still has a subscription to collect on,
                 * and a date it can chase is the difference between that and
                 * a membership everybody forgets. What `auto_renew` decides
                 * is who takes the money, not whether it is owed. */
                'next_billing_on' => self::firstBillingDate($plan, $startsOn)?->toDateString(),

                /* The card renewals will reach for, and whether they happen
                   at all. Kept together because one without the other is a
                   renewal that cannot run: a membership set to renew with no
                   card is a charge nobody can take. */
                'payment_method_id' => $renews ? $card?->id : null,
                'auto_renew' => $renews,

                'sold_by' => $seller?->id,
            ]);

            self::grantCredits($membership, $plan, $settings, $startsOn);

            if ($payment !== null && $payment['amount_minor'] > 0) {
                MembershipPayment::create([
                    'tenant_id' => $client->tenant_id,
                    'client_membership_id' => $membership->id,
                    'method' => $payment['method'],
                    'status' => 'paid',
                    'amount_minor' => (int) $payment['amount_minor'],
                    'currency_code' => $membership->currency_code,
                    'purpose' => 'initial',
                    'reference' => $payment['reference'] ?? null,
                    'paid_at' => now(),
                    'recorded_by' => $seller?->id,
                ]);
            }

            return $membership->fresh(['credits.service', 'plan', 'client', 'payments']);
        });
    }

    /**
     * The credits one period of this plan grants.
     *
     * A recurring plan grants its list every cycle and this is the first of
     * them; a package grants its list once and that is the whole purchase.
     * The same numbers mean two different things, which is why the period is
     * written down beside them rather than inferred later.
     */
    public static function grantCredits(
        ClientMembership $membership,
        MembershipPlan $plan,
        MembershipSettings $settings,
        Carbon $periodStart,
    ): void {
        /* A business whose memberships do not include anything grants
           nothing. What the client bought is the discount and the standing,
           and writing credit rows for it would put massages on their profile
           nobody sold them. */
        if (! $settings->grantsCredits()) {
            return;
        }

        $rules = $plan->creditRules($settings);
        $recurring = $plan->isRecurring();

        $periodEnd = $recurring
            ? $periodStart->copy()->addMonthsNoOverflow(MembershipSettings::monthsFor($plan->billing_frequency))->subDay()
            : null;

        foreach ($plan->planServices as $line) {
            MembershipCredit::create([
                'tenant_id' => $membership->tenant_id,
                'client_membership_id' => $membership->id,
                'service_id' => $line->service_id,
                /* Credits governs what can be redeemed; quantity is what the
                   card describes. They agree in every ordinary membership. */
                'quantity_granted' => $line->grantedCredits(),
                'quantity_used' => 0,
                'period_start' => $recurring ? $periodStart->toDateString() : null,
                'period_end' => $periodEnd?->toDateString(),
                'expires_on' => self::expiryFor($rules['expiry'], $periodStart, $periodEnd),
            ]);
        }
    }

    /**
     * When a credit granted now stops being spendable.
     *
     * 'cycle' is not a duration — the credit dies with the billing period it
     * belongs to, whenever that happens to be — which is why it reads the
     * period rather than a number of months. On a package there is no cycle
     * to die with, so 'cycle' means never.
     */
    private static function expiryFor(string $expiry, Carbon $periodStart, ?Carbon $periodEnd): ?string
    {
        if ($expiry === 'never') {
            return null;
        }

        if ($expiry === 'cycle') {
            return $periodEnd?->toDateString();
        }

        $months = config('membership.credit_expiry.'.$expiry.'.months');

        return $months === null
            ? null
            : $periodStart->copy()->addMonthsNoOverflow((int) $months)->toDateString();
    }

    /** When the first renewal falls due, or null for something bought once. */
    private static function firstBillingDate(MembershipPlan $plan, Carbon $startsOn): ?Carbon
    {
        if (! $plan->isRecurring() || $plan->billing_frequency === null) {
            return null;
        }

        /* A trial delays the first charge and nothing else: the membership is
           live from day one, which is what the client was sold. */
        $from = $plan->trial_days > 0
            ? $startsOn->copy()->addDays((int) $plan->trial_days)
            : $startsOn->copy()->addMonthsNoOverflow(MembershipSettings::monthsFor($plan->billing_frequency));

        return $from;
    }

    /**
     * What one plan costs to start today, in minor units.
     *
     * The first cycle plus the one-off fees. A trial does not change it: what
     * a trial delays is the *second* payment, and a plan that asked for
     * nothing today would be one the till cannot take money for.
     */
    public static function dueTodayMinor(MembershipPlan $plan): int
    {
        return (int) $plan->price_minor
            + (int) $plan->joining_fee_minor
            + (int) $plan->setup_fee_minor;
    }

    /**
     * The credits a client can spend on one service right now.
     *
     * Across every membership they hold, because somebody with a massage
     * package and a monthly membership has two sources for the same service
     * and the desk should not have to know which to reach for.
     *
     * @return Collection<int, MembershipCredit>
     */
    public static function spendableFor(Client $client, int $serviceId)
    {
        return MembershipCredit::query()
            ->spendable()
            ->where('service_id', $serviceId)
            ->whereIn('client_membership_id', ClientMembership::query()
                ->where('client_id', $client->id)
                ->live()
                ->select('id'))
            /* Oldest first, so the credit closest to expiring is the one
               spent — the client keeps the one with the most life left. */
            ->orderByRaw('expires_on is null, expires_on')
            ->get();
    }

    /** Everything a plan includes, as "1 × Swedish Massage". */
    public static function includesLabel(MembershipPlan $plan): string
    {
        return $plan->planServices
            ->map(fn (MembershipPlanService $line) => $line->quantity.' × '.($line->service?->name ?? '—'))
            ->join(', ');
    }
}
