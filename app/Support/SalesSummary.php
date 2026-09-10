<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\MembershipPayment;
use Illuminate\Database\Eloquent\Builder;

/**
 * The six figures at the top of the Sales page.
 *
 * Two of them are counted by APPOINTMENT date and four by when the money
 * moved, and the difference is not a detail: a deposit taken in August for a
 * September appointment is September's sale and August's payment. Reporting
 * both against one date would make the page disagree with itself — the
 * outstanding balance would not be the total minus what was collected.
 *
 * The screen says which is which rather than leaving the reader to discover
 * it from a total that will not reconcile.
 */
class SalesSummary
{
    /**
     * @return array<string, array{value: int, count?: int, change?: ?float}>
     */
    public static function for(SalesPeriod $period, array $filters = []): array
    {
        $sold = self::bookings($period, $filters);
        $moved = self::payments($period, $filters);
        /* Memberships are sold too. A figure that counted only bookings was
           one that under-reported the month by every membership taken in
           it. */
        $memberships = self::membershipPayments($period, $filters);

        $previous = self::soldTotal(self::bookings($period->previous(), $filters))
            + self::membershipTotal(self::membershipPayments($period->previous(), $filters));

        $total = self::soldTotal($sold) + self::membershipTotal($memberships);

        return [
            'total_sales' => [
                'value' => $total,
                /* Null rather than 0% when there is nothing to compare with:
                   "+0%" against a period with no sales reads as flat trade
                   rather than as a business that had not opened yet. */
                'change' => $previous > 0 ? round((($total - $previous) / $previous) * 100, 1) : null,
            ],

            'collected' => ['value' => (int) (clone $moved)->where('status', 'paid')->sum('amount_minor')
                + (int) (clone $memberships)->where('status', 'paid')->sum('amount_minor')],

            /* What is still owed on the appointments in this period. Derived
               from the bookings rather than from the payments, because a
               booking nobody has paid anything towards has no payment row and
               would otherwise be invisible. */
            'outstanding' => ['value' => self::outstanding($sold)],

            'refunds' => ['value' => (int) (clone $moved)->where('status', 'refunded')->sum('amount_minor')
                + (int) (clone $memberships)->where('status', 'refunded')->sum('amount_minor')],

            'tips' => ['value' => (int) (clone $moved)->where('status', 'paid')->sum('tip_minor')],

            'transactions' => ['value' => 0, 'count' => (int) (clone $moved)->count()
                + (int) (clone $memberships)->count()],
        ];
    }

    /** Bookings whose appointment falls in the period. */
    private static function bookings(SalesPeriod $period, array $filters): Builder
    {
        return Booking::query()
            ->whereBetween('date', [$period->from->toDateString(), $period->to->toDateString()])
            /* The widgets follow the table. A reader who has narrowed the
               list to memberships is asking what the memberships came to,
               and a figure counting everything beside a table showing two
               rows is a figure they cannot use. */
            ->when(($filters['type'] ?? null) === 'membership', fn (Builder $q) => $q->whereRaw('1 = 0'))
            ->when($filters['location'] ?? null, fn (Builder $q, $id) => $q->where('location_id', $id))
            ->when($filters['staff'] ?? null, fn (Builder $q, $id) => $q->where('staff_id', $id));
    }

    /** Payments that actually moved in the period. */
    private static function payments(SalesPeriod $period, array $filters): Builder
    {
        return BookingPayment::query()
            ->whereBetween('paid_at', [$period->from, $period->to])
            ->when(($filters['type'] ?? null) === 'membership', fn (Builder $q) => $q->whereRaw('1 = 0'))
            ->when(
                ($filters['location'] ?? null) || ($filters['staff'] ?? null),
                fn (Builder $q) => $q->whereHas('booking', fn (Builder $b) => $b
                    ->when($filters['location'] ?? null, fn (Builder $x, $id) => $x->where('location_id', $id))
                    ->when($filters['staff'] ?? null, fn (Builder $x, $id) => $x->where('staff_id', $id))),
            );
    }

    /**
     * Membership money that moved in the period.
     *
     * Filtered on location only. A membership has no staff member and no
     * service, so a summary narrowed to either is a summary about
     * appointments and memberships have no part in it.
     */
    private static function membershipPayments(SalesPeriod $period, array $filters): Builder
    {
        return MembershipPayment::query()
            ->whereBetween('paid_at', [$period->from, $period->to])
            ->when(($filters['type'] ?? null) === 'service', fn (Builder $q) => $q->whereRaw('1 = 0'))
            ->when(($filters['staff'] ?? null) !== null, fn (Builder $q) => $q->whereRaw('1 = 0'))
            ->when(
                ($filters['location'] ?? null),
                fn (Builder $q, $id) => $q->whereHas('membership', fn (Builder $m) => $m->where('location_id', $id)),
            );
    }

    private static function soldTotal(Builder $bookings): int
    {
        return (int) (clone $bookings)->sum('total_minor');
    }

    /**
     * What memberships brought in, refunds taken off.
     *
     * A refund is a row of its own rather than an edit to the original, so a
     * plain sum would count money that came back as money that came in.
     */
    private static function membershipTotal(Builder $payments): int
    {
        return (int) (clone $payments)->where('status', 'paid')->sum('amount_minor')
            - (int) (clone $payments)->where('status', 'refunded')->sum('amount_minor');
    }

    /**
     * What is still owed across those bookings.
     *
     * `total_minor - paid_minor`, floored at zero per booking rather than in
     * total: an overpayment on one appointment is not credit against another,
     * and summing the raw difference would let it quietly cancel a real debt.
     */
    private static function outstanding(Builder $bookings): int
    {
        /* CAST to SIGNED before subtracting. Both columns are UNSIGNED
           BIGINT, so an overpaid booking — paid greater than total — wraps
           around to an astronomical positive instead of going negative, and
           MySQL raises "value is out of range" rather than quietly answering
           wrong. Either way the figure would be nonsense. */
        return (int) (clone $bookings)
            ->selectRaw('COALESCE(SUM(GREATEST(CAST(total_minor AS SIGNED) - CAST(paid_minor AS SIGNED), 0)), 0) as due')
            ->value('due');
    }
}
