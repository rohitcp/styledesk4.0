<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientLoyaltyPoint;
use App\Models\LoyaltySettings;
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
        ];
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
