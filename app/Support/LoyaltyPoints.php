<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientLoyaltyPoint;
use App\Models\ClientMembership;
use App\Models\LoyaltyReward;
use App\Models\LoyaltySettings;
use App\Models\MembershipPayment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Points earned, spent, lost and given back.
 *
 * One place decides what a visit is worth, because that decision is reached
 * from four directions — the Complete button, a payment landing, a refund
 * going out, and an appointment cancelled after the fact — and four copies of
 * it is how a client ends up with points for a haircut they were refunded for.
 *
 * The engine is a reconciler rather than an accumulator. Nothing here says
 * "add the points for this booking"; everything says "this booking should
 * have earned N, it has earned M, write the difference". That single shape is
 * what makes a partial refund, a full refund, a cancellation and a payment
 * arriving in two halves all work without any of them being handled
 * separately — and what makes calling it twice harmless, which matters
 * because the payment gateways call it every time money moves.
 *
 * Nothing here throws into the caller. Finishing an appointment or taking
 * money must not fail because the loyalty module could not be reached: the
 * visit happened and the money is in the drawer either way, and a red page at
 * the front desk over a points balance is the wrong trade — the same bargain
 * App\Support\ReviewRequests makes.
 */
class LoyaltyPoints
{
    /** The lines that came from a booking rather than from a person. */
    private const EARNING_TYPES = ['earned', 'refund_adjustment', 'cancellation_adjustment'];

    /**
     * Bring a booking's points into line with what it is now worth.
     *
     * Called wherever the money or the status moved. Writes at most one line:
     * the difference between what this booking should have earned and what it
     * already has. A booking whose points are already right writes nothing,
     * which is what makes this safe to call on every payment event.
     */
    public static function settle(Booking $booking, ?int $userId = null): ?ClientLoyaltyPoint
    {
        try {
            $settings = LoyaltySettings::forTenant($booking->tenant);

            /* Switched off stops the earning and stops the taking back. A
               business that paused the scheme has not agreed to have last
               month's refunds quietly clawed the day they pause it. */
            if (! $settings->is_enabled || $booking->client_id === null) {
                return null;
            }

            $target = self::targetPointsFor($booking, $settings);
            $already = self::pointsAwardedFor($booking);
            $delta = $target - $already;

            if ($delta === 0) {
                return null;
            }

            return self::write(
                clientId: (int) $booking->client_id,
                tenantId: (string) $booking->tenant_id,
                type: self::typeForDelta($booking, $delta),
                points: $delta,
                userId: $userId,
                attributes: [
                    'booking_id' => $booking->id,
                    'location_id' => $booking->location_id,
                    'eligible_amount_minor' => self::eligibleMinorFor($booking, $settings),
                    'currency_code' => $booking->currency_code,
                    'expires_at' => $delta > 0 ? self::expiryFor($settings) : null,
                ],
            );
        } catch (\Throwable $e) {
            /* Swallowed on purpose, and loudly logged. The appointment is
               finished and the money is taken whether or not this worked. */
            Log::warning('Could not settle loyalty points for a booking.', [
                'booking_id' => $booking->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Points added or taken away by a person.
     *
     * §6. `points` is always given as a positive number and the type decides
     * the direction, because a form that accepted "-100" in a box labelled
     * Points would eventually be given "-100" by somebody who meant to remove
     * a hundred and removed nothing.
     */
    public static function adjust(
        Client $client,
        int $points,
        bool $isAddition,
        string $reason,
        ?string $note = null,
        ?int $userId = null,
    ): ClientLoyaltyPoint {
        $points = abs($points);

        return self::write(
            clientId: (int) $client->id,
            tenantId: (string) $client->tenant_id,
            type: $isAddition ? 'manual_add' : 'manual_deduct',
            points: $isAddition ? $points : -$points,
            userId: $userId,
            attributes: [
                'reason' => $reason,
                'note' => $note,
                /* Given points expire on the same clock earned ones do.
                   Points taken away never expire — they are already gone. */
                'expires_at' => $isAddition
                    ? self::expiryFor(LoyaltySettings::forTenant($client->tenant))
                    : null,
            ],
        );
    }

    /**
     * Points spent against a booking.
     *
     * Not reachable from a screen yet — the redemption controls live on the
     * payment panel, which is the next piece. It is here because the ledger,
     * the balance and the history all have to understand a redemption before
     * that panel can write one, and a redemption invented later in a
     * controller would be the second place that knows how a balance moves.
     */
    public static function redeem(Client $client, int $points, ?Booking $booking = null, ?int $userId = null): ClientLoyaltyPoint
    {
        return self::write(
            clientId: (int) $client->id,
            tenantId: (string) $client->tenant_id,
            type: 'redeemed',
            points: -abs($points),
            userId: $userId,
            attributes: [
                'booking_id' => $booking?->id,
                'location_id' => $booking?->location_id,
                'currency_code' => $booking?->currency_code,
            ],
        );
    }

    /**
     * Bring a membership payment's points into line with what it is worth.
     *
     * The same reconciler as `settle()`, against a different kind of money:
     * what this payment should have earned, minus what it already has, write
     * the difference. That shape is why calling it twice is harmless and why
     * a payment later marked unpaid takes its points back with no branch of
     * its own.
     *
     * Which switch it obeys depends on the plan. A package is bought once and
     * a subscription bills again, and a salon happy to give points on a
     * one-off purchase has not thereby agreed to give them every month for
     * the life of a subscription — so they are two settings and this asks the
     * one that applies.
     *
     * Swallows and logs like its sibling: taking money for a membership must
     * never fail over a points balance.
     */
    public static function settleMembershipPayment(MembershipPayment $payment, ?int $userId = null): ?ClientLoyaltyPoint
    {
        try {
            $membership = $payment->membership;
            $client = $membership?->client;

            if ($client === null) {
                return null;
            }

            $settings = LoyaltySettings::forTenant($client->tenant);

            if (! $settings->is_enabled) {
                return null;
            }

            $target = self::targetPointsForMembership($payment, $membership, $settings);
            $already = (int) ClientLoyaltyPoint::query()
                ->where('membership_payment_id', $payment->id)
                ->whereIn('type', self::EARNING_TYPES)
                ->sum('points');

            $delta = $target - $already;

            if ($delta === 0) {
                return null;
            }

            return self::write(
                clientId: (int) $client->id,
                tenantId: (string) $client->tenant_id,
                type: $delta > 0 ? 'earned' : 'refund_adjustment',
                points: $delta,
                userId: $userId,
                attributes: [
                    'membership_payment_id' => $payment->id,
                    'location_id' => $membership->location_id,
                    'eligible_amount_minor' => self::eligibleMinorForMembership($payment, $membership, $settings),
                    'currency_code' => $payment->currency_code,
                    'expires_at' => $delta > 0 ? self::expiryFor($settings) : null,
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('Could not settle loyalty points for a membership payment.', [
                'membership_payment_id' => $payment->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * What this membership payment is worth in points.
     *
     * Nothing at all unless the money actually landed: a payment recorded as
     * pending, failed or refunded is not a purchase, and points on one would
     * be points for a membership the client does not have.
     */
    private static function targetPointsForMembership(
        MembershipPayment $payment,
        ClientMembership $membership,
        LoyaltySettings $settings,
    ): int {
        $eligible = self::eligibleMinorForMembership($payment, $membership, $settings);

        return $eligible <= 0 ? 0 : $settings->pointsFor($eligible);
    }

    /**
     * How much of a membership payment earns.
     *
     * The whole of it or none of it. A membership is one price for one thing
     * — there is no subtotal to separate from a tax line the way a booking
     * has — so the question is only whether this kind of membership earns at
     * all.
     */
    private static function eligibleMinorForMembership(
        MembershipPayment $payment,
        ClientMembership $membership,
        LoyaltySettings $settings,
    ): int {
        if ($payment->status !== 'paid') {
            return 0;
        }

        $switch = $membership->plan?->isRecurring()
            ? 'membership_recurring'
            : 'membership_package';

        return $settings->earnsOn($switch) ? max(0, (int) $payment->amount_minor) : 0;
    }

    /**
     * A reward taken from the catalogue.
     *
     * The reward's price in points, not a figure the caller chose: the
     * catalogue is what the business decided a reward costs, and a till that
     * could pass its own number would be a second place that knows.
     *
     * Refused rather than allowed to go negative. Everywhere else in this
     * class a balance is reconciled and a correction may push it under zero —
     * that is a fact being recorded. This is a client asking for something,
     * and handing it over on points they do not have is the one case where
     * the honest answer is no.
     *
     * What it was worth is copied onto the line, because the catalogue is
     * what the business offers today and a reward repriced in June must not
     * rewrite what somebody was given in March.
     *
     * @throws \RuntimeException when the balance will not cover it
     */
    public static function redeemReward(
        Client $client,
        LoyaltyReward $reward,
        ?Booking $booking = null,
        ?int $userId = null,
        ?int $valueMinor = null,
    ): ClientLoyaltyPoint {
        $points = (int) $reward->points_required;

        if (self::balanceFor($client) < $points) {
            throw new \RuntimeException('Not enough points to redeem this reward.');
        }

        return self::write(
            clientId: (int) $client->id,
            tenantId: (string) $client->tenant_id,
            type: 'redeemed',
            points: -abs($points),
            userId: $userId,
            attributes: [
                'booking_id' => $booking?->id,
                'location_id' => $booking?->location_id,
                'currency_code' => $booking?->currency_code,
                'loyalty_reward_id' => $reward->id,
                /* What the client actually got off, where the till worked it
                   out — a percentage is worth what the bill was, and the bill
                   is not something this class can see. Falls back to the
                   reward's own figure for the types that carry one. */
                'reward_value_minor' => $valueMinor ?? $reward->value_minor,
            ],
        );
    }

    /**
     * Points given for a reason that is not a purchase.
     *
     * A joining bonus is not an adjustment somebody made and not something a
     * booking earned, so it gets its own type rather than being filed as
     * either — the history should say what it was for.
     */
    public static function credit(Client $client, int $points, string $type, ?int $userId = null): ClientLoyaltyPoint
    {
        $settings = LoyaltySettings::forTenant($client->tenant);

        return self::write(
            clientId: (int) $client->id,
            tenantId: (string) $client->tenant_id,
            type: $type,
            points: abs($points),
            userId: $userId,
            attributes: ['expires_at' => self::expiryFor($settings)],
        );
    }

    // ------------------------------------------------------------- reading

    /**
     * What a client has to spend, right now.
     *
     * The sum of the lines that have not expired. Never negative: a balance
     * pushed under zero by a correction is shown as nothing left rather than
     * as a debt, because there is no such thing as owing a salon points.
     */
    public static function balanceFor(Client $client): int
    {
        return max(0, (int) ClientLoyaltyPoint::query()
            ->where('client_id', $client->id)
            ->live()
            ->sum('points'));
    }

    /**
     * The four figures at the top of the rewards tab.
     *
     * @return array{available: int, pending: int, lifetime_earned: int, lifetime_redeemed: int}
     */
    public static function summaryFor(Client $client, ?LoyaltySettings $settings = null): array
    {
        $settings ??= LoyaltySettings::forTenant($client->tenant);

        $rows = ClientLoyaltyPoint::query()->where('client_id', $client->id);

        return [
            'available' => self::balanceFor($client),
            'pending' => self::pendingFor($client, $settings),
            /* Everything ever credited, expiry included: "lifetime earned" is
               a history, not a balance, and points that have since expired
               were still earned. */
            'lifetime_earned' => (int) (clone $rows)->where('points', '>', 0)->sum('points'),
            'lifetime_redeemed' => abs((int) (clone $rows)->where('type', 'redeemed')->sum('points')),
            /* What the client loses if they do nothing. The one figure here
               that is a warning rather than a fact, which is why it is worth
               its own tile: a balance that quietly evaporates costs more
               goodwill than never having run a scheme. */
            'expiring_soon' => self::expiringSoonFor($client),
        ];
    }

    /**
     * Points that will lapse within the warning window.
     *
     * Only credits, and only ones with a deadline: a business that has set no
     * expiry has nothing expiring, which is the default and the common case.
     * Read at the moment the question is asked, like the balance itself —
     * there is no nightly sweep marking anything.
     */
    public static function expiringSoonFor(Client $client, ?int $days = null): int
    {
        $days ??= (int) config('loyalty.expiring_soon_days', 30);

        return max(0, (int) ClientLoyaltyPoint::query()
            ->where('client_id', $client->id)
            ->where('points', '>', 0)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays($days)])
            ->sum('points'));
    }

    /** How many days ahead "expiring soon" looks. */
    public static function expiringWindowDays(): int
    {
        return (int) config('loyalty.expiring_soon_days', 30);
    }

    /**
     * Points the diary says are coming, from appointments not yet finished.
     *
     * Worked out from the bookings rather than written down as rows. A pending
     * point is a forecast, and a forecast stored in a ledger is a row somebody
     * has to remember to delete when the client cancels — which is exactly the
     * row nobody remembers. Read this way, a cancellation, a reschedule and a
     * price change all correct themselves for free.
     */
    public static function pendingFor(Client $client, ?LoyaltySettings $settings = null): int
    {
        $settings ??= LoyaltySettings::forTenant($client->tenant);

        if (! $settings->is_enabled) {
            return 0;
        }

        return Booking::query()
            ->with('services')
            ->where('client_id', $client->id)
            /* What is still going to happen. A draft is not an appointment
               and a lead is not a booking, so neither forecasts anything. */
            ->whereIn('status', ['confirmed', 'arrived'])
            ->whereDate('date', '>=', now()->toDateString())
            ->get()
            ->sum(fn (Booking $booking) => $settings->pointsFor(self::eligibleMinorFor($booking, $settings)));
    }

    /**
     * What one booking would be worth, for the estimate beside it.
     *
     * §8's "+125 points from this booking", read from the same rule the award
     * will use — so the number the client was quoted in the chair is the
     * number that lands.
     */
    public static function estimateFor(Booking $booking, ?LoyaltySettings $settings = null): int
    {
        $settings ??= LoyaltySettings::forTenant($booking->tenant);

        return $settings->is_enabled
            ? $settings->pointsFor(self::eligibleMinorFor($booking, $settings))
            : 0;
    }

    /**
     * One client's history, newest first.
     *
     * @return Collection<int, ClientLoyaltyPoint>
     */
    public static function historyFor(Client $client, int $limit = 200): Collection
    {
        return ClientLoyaltyPoint::query()
            ->with(['createdBy', 'location', 'booking'])
            ->where('client_id', $client->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    // ------------------------------------------------------------ the rule

    /**
     * The spend a booking earns on.
     *
     * §13: what the client actually gave the business. The discount comes off
     * first — points on money nobody paid is the scheme paying for its own
     * coupon twice — and tax and tips are counted only where the business said
     * to. Products, memberships, packages and gift cards contribute nothing
     * because StyleDesk does not sell them yet; the settings screen says so
     * rather than offering a switch that awards nothing.
     */
    public static function eligibleMinorFor(Booking $booking, LoyaltySettings $settings): int
    {
        if (! $settings->earnsOn('services')) {
            return 0;
        }

        $base = (int) ($booking->subtotal_minor ?: $booking->total_minor) - (int) $booking->discount_minor;

        if ($settings->earnsOn('taxes')) {
            $base += (int) $booking->tax_minor;
        }

        if ($settings->earnsOn('tips')) {
            $base += (int) $booking->tip_minor;
        }

        return max(0, $base);
    }

    /**
     * What this booking should have earned, as things stand.
     *
     * Zero unless the appointment was delivered and the money collected —
     * §6's two conditions, and the reason a cancellation after the fact takes
     * the points back without anything having to notice it was a cancellation.
     *
     * Part-paid earns in proportion. A client who has settled half the bill
     * has given the business half the money, and holding all the points back
     * until the last cent lands would make a deposit look like it earned
     * nothing.
     */
    private static function targetPointsFor(Booking $booking, LoyaltySettings $settings): int
    {
        if ($booking->status !== 'completed') {
            return 0;
        }

        $total = (int) $booking->total_minor;
        $paid = self::netPaidMinor($booking);

        if ($total <= 0 || $paid <= 0) {
            return 0;
        }

        $eligible = self::eligibleMinorFor($booking, $settings);
        $settled = (int) round($eligible * min(1.0, $paid / $total));

        return $settings->pointsFor($settled);
    }

    /**
     * Money this booking is actually holding.
     *
     * Counted here rather than read from Booking::paidMinor(), which treats a
     * refund as money taken. A refund is money handed back, and the whole of
     * §12 depends on the difference.
     */
    private static function netPaidMinor(Booking $booking): int
    {
        $payments = $booking->relationLoaded('payments')
            ? $booking->payments
            : $booking->payments()->get();

        $paid = (int) $payments->where('status', 'paid')->sum('amount_minor');
        $refunded = (int) $payments->where('status', 'refunded')->sum('amount_minor');

        /* abs on the refunds: the gateways do not agree on whether a refund
           row is stored negative, and a sign convention is not something a
           points balance should be hostage to. */
        return max(0, $paid - abs($refunded));
    }

    /** The points already written against this booking. */
    private static function pointsAwardedFor(Booking $booking): int
    {
        return (int) ClientLoyaltyPoint::query()
            ->forBooking((int) $booking->id)
            ->whereIn('type', self::EARNING_TYPES)
            ->sum('points');
    }

    /**
     * What to call a line that takes points back.
     *
     * A refund and a cancellation both end with the client holding fewer
     * points, and the history has to say which happened — §14 shows the
     * original beside the reversal, and "Refund adjustment" against an
     * appointment nobody was refunded for is a line that starts a phone call.
     */
    private static function typeForDelta(Booking $booking, int $delta): string
    {
        if ($delta > 0) {
            return 'earned';
        }

        return $booking->status === 'completed' ? 'refund_adjustment' : 'cancellation_adjustment';
    }

    /** When points written now stop counting, or null where they never do. */
    private static function expiryFor(LoyaltySettings $settings): ?Carbon
    {
        $months = config('loyalty.expiry.'.$settings->expiry.'.months');

        return $months === null ? null : now()->addMonths((int) $months);
    }

    /**
     * Write one line, and the balance it left behind.
     *
     * In a transaction with the client's existing lines locked, because
     * `balance_after` is only worth writing if it is right: two payments
     * landing at the same moment would otherwise both read the same balance
     * and both claim to have produced it.
     *
     * @param  array<string, mixed>  $attributes
     */
    private static function write(
        int $clientId,
        string $tenantId,
        string $type,
        int $points,
        ?int $userId,
        array $attributes = [],
    ): ClientLoyaltyPoint {
        return DB::transaction(function () use ($clientId, $tenantId, $type, $points, $userId, $attributes) {
            $balance = (int) ClientLoyaltyPoint::query()
                ->where('client_id', $clientId)
                ->live()
                ->lockForUpdate()
                ->sum('points');

            return ClientLoyaltyPoint::create($attributes + [
                'tenant_id' => $tenantId,
                'client_id' => $clientId,
                'type' => $type,
                'points' => $points,
                'balance_after' => max(0, $balance + $points),
                'created_by' => $userId,
            ]);
        });
    }
}
