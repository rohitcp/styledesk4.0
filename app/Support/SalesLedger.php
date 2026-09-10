<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BookingPayment;
use App\Models\MembershipPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One list of transactions, from two tables.
 *
 * Money reaches this business two ways and is stored two ways: a payment
 * against a booking, and a payment against a membership. They are genuinely
 * different rows — one has a staff member and a service, the other has a plan
 * and a billing cycle — and flattening them into one table would mean half
 * the columns null on every row.
 *
 * But the *ledger* is one thing. A business asking "what did we take in
 * March" is not asking twice, and a Sales page that showed only bookings was
 * a page that quietly under-reported every membership sold.
 *
 * So the ordering and the paging happen in the database, over a union of the
 * two tables reduced to what ordering needs — a source, an id and a date —
 * and only the rows on the page asked for are then loaded as models, with
 * their relations, from the table each belongs to. Two cheap queries to find
 * the window, two to fill it: no fetching a month of rows to show
 * twenty-five, and no ordering done in PHP where the database can do it.
 */
class SalesLedger
{
    /**
     * The transactions on one page, newest first.
     *
     * @param  Builder<BookingPayment>  $bookingPayments  already filtered
     * @param  Builder<MembershipPayment>  $membershipPayments  already filtered
     * @return array{items: Collection<int, BookingPayment|MembershipPayment>, total: int, last_page: int}
     */
    public static function page(
        Builder $bookingPayments,
        Builder $membershipPayments,
        int $perPage,
        int $page,
    ): array {
        $window = self::window($bookingPayments, $membershipPayments);

        $total = $window->count();
        $lastPage = max(1, (int) ceil($total / max(1, $perPage)));

        $keys = $window
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();

        return [
            'items' => self::hydrate($bookingPayments, $membershipPayments, $keys),
            'total' => $total,
            'last_page' => $lastPage,
        ];
    }

    /**
     * Everything both filters match, as source-and-id pairs.
     *
     * A union rather than two queries merged afterwards: which twenty-five
     * rows are on page two is a question about both tables at once, and PHP
     * cannot answer it without reading every row in the period.
     */
    private static function window(Builder $bookingPayments, Builder $membershipPayments): \Illuminate\Database\Query\Builder
    {
        $bookings = $bookingPayments->clone()->toBase()
            ->select([
                'booking_payments.id',
                'booking_payments.paid_at',
                DB::raw("'booking' as source"),
            ])
            ->reorder();

        $memberships = $membershipPayments->clone()->toBase()
            ->select([
                'membership_payments.id',
                'membership_payments.paid_at',
                DB::raw("'membership' as source"),
            ])
            ->reorder();

        return DB::query()->fromSub($bookings->unionAll($memberships), 'ledger');
    }

    /**
     * The models for one page, each from its own table.
     *
     * Ordered back into the order the window gave: two `whereIn` queries come
     * back in whatever order the database finds them, and a page that
     * reordered itself on load would be a table nobody could page through.
     *
     * @param  Collection<int, object>  $keys
     * @return Collection<int, BookingPayment|MembershipPayment>
     */
    private static function hydrate(Builder $bookingPayments, Builder $membershipPayments, Collection $keys): Collection
    {
        $ids = fn (string $source) => $keys
            ->filter(fn (object $key) => $key->source === $source)
            ->pluck('id')
            ->all();

        $bookingIds = $ids('booking');
        $membershipIds = $ids('membership');

        $found = collect();

        if ($bookingIds !== []) {
            $found = $found->merge(
                $bookingPayments->clone()->reorder()->whereIn('booking_payments.id', $bookingIds)->get()
                    ->keyBy(fn (BookingPayment $payment) => 'booking:'.$payment->id)
            );
        }

        if ($membershipIds !== []) {
            $found = $found->merge(
                $membershipPayments->clone()->reorder()->whereIn('membership_payments.id', $membershipIds)->get()
                    ->keyBy(fn (MembershipPayment $payment) => 'membership:'.$payment->id)
            );
        }

        return $keys
            ->map(fn (object $key) => $found->get($key->source.':'.$key->id))
            ->filter()
            ->values();
    }
}
