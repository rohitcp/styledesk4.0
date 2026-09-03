<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\BookingPayment;
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

        $previous = self::soldTotal(self::bookings($period->previous(), $filters));

        $total = self::soldTotal($sold);

        return [
            'total_sales' => [
                'value' => $total,
                /* Null rather than 0% when there is nothing to compare with:
                   "+0%" against a period with no sales reads as flat trade
                   rather than as a business that had not opened yet. */
                'change' => $previous > 0 ? round((($total - $previous) / $previous) * 100, 1) : null,
            ],

            'collected' => ['value' => (int) (clone $moved)->where('status', 'paid')->sum('amount_minor')],

            /* What is still owed on the appointments in this period. Derived
               from the bookings rather than from the payments, because a
               booking nobody has paid anything towards has no payment row and
               would otherwise be invisible. */
            'outstanding' => ['value' => self::outstanding($sold)],

            'refunds' => ['value' => (int) (clone $moved)->where('status', 'refunded')->sum('amount_minor')],

            'tips' => ['value' => (int) (clone $moved)->where('status', 'paid')->sum('tip_minor')],

            'transactions' => ['value' => 0, 'count' => (int) (clone $moved)->count()],
        ];
    }

    /** Bookings whose appointment falls in the period. */
    private static function bookings(SalesPeriod $period, array $filters): Builder
    {
        return Booking::query()
            ->whereBetween('date', [$period->from->toDateString(), $period->to->toDateString()])
            ->when($filters['location'] ?? null, fn (Builder $q, $id) => $q->where('location_id', $id))
            ->when($filters['staff'] ?? null, fn (Builder $q, $id) => $q->where('staff_id', $id));
    }

    /** Payments that actually moved in the period. */
    private static function payments(SalesPeriod $period, array $filters): Builder
    {
        return BookingPayment::query()
            ->whereBetween('paid_at', [$period->from, $period->to])
            ->when(
                ($filters['location'] ?? null) || ($filters['staff'] ?? null),
                fn (Builder $q) => $q->whereHas('booking', fn (Builder $b) => $b
                    ->when($filters['location'] ?? null, fn (Builder $x, $id) => $x->where('location_id', $id))
                    ->when($filters['staff'] ?? null, fn (Builder $x, $id) => $x->where('staff_id', $id))),
            );
    }

    private static function soldTotal(Builder $bookings): int
    {
        return (int) (clone $bookings)->sum('total_minor');
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
