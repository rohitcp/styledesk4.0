<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\MembershipCredit;
use App\Models\MembershipCreditRedemption;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Spending what a client already bought.
 *
 * A credit pays for one whole service. Not part of one — a client who bought
 * "one massage a month" bought the massage, not sixty pounds off it, and a
 * partial credit is a conversation no desk wants to have when the price list
 * changes.
 *
 * Three things happen here and nowhere else: working out what a client could
 * cover, holding those credits down when a booking is taken, and giving them
 * back when it is called off. Doing any of them in a controller is how a
 * cancelled appointment quietly eats somebody's massage.
 *
 * Like ReviewRequests and LoyaltyPoints, this never decides *whether* to
 * apply — the desk does that, per service, on the booking screen. What it
 * refuses is applying one that is not there.
 */
class MembershipCredits
{
    /**
     * What this client could cover, keyed by service id.
     *
     * One entry per service they hold a spendable credit for, whether or not
     * that service is on the booking — the caller filters. Each says which
     * membership it would come out of, so the screen can name it.
     *
     * @param  list<int>  $serviceIds
     * @return array<int, array{membership_id: int, membership_name: string, remaining: int, expires_on: ?string}>
     */
    public static function offersFor(?Client $client, array $serviceIds): array
    {
        if ($client === null || $serviceIds === []) {
            return [];
        }

        $credits = MembershipCredit::query()
            ->spendable()
            ->whereIn('service_id', $serviceIds)
            ->whereIn('client_membership_id', ClientMembership::query()
                ->where('client_id', $client->id)
                ->live()
                ->select('id'))
            ->with('membership.plan')
            /* Oldest deadline first, so the credit closest to expiring is the
               one offered and the client keeps the one with the most life. */
            ->orderByRaw('expires_on is null, expires_on')
            ->get();

        $offers = [];

        foreach ($credits as $credit) {
            $serviceId = (int) $credit->service_id;

            /* One offer per service. A client holding two memberships that
               both cover a massage is offered the first of them; the second
               is not a second choice to make at the desk, it is what happens
               next time. */
            if (isset($offers[$serviceId])) {
                $offers[$serviceId]['remaining'] += $credit->remaining();

                continue;
            }

            $offers[$serviceId] = [
                'membership_id' => (int) $credit->client_membership_id,
                'membership_name' => $credit->membership?->plan?->name ?? '',
                'remaining' => $credit->remaining(),
                'expires_on' => $credit->expires_on?->toDateString(),
            ];
        }

        return $offers;
    }

    /**
     * Which of the services asked for can actually be covered.
     *
     * The screen's list is a request, not an instruction: it was assembled
     * before the booking was confirmed and a credit can be spent elsewhere in
     * between. Anything that cannot be covered is silently dropped rather
     * than refusing the whole booking — the appointment is the thing that
     * matters, and the client pays for that line instead.
     *
     * @param  list<int>  $requested
     * @return list<int>
     */
    public static function coverable(?Client $client, array $requested): array
    {
        if ($client === null || $requested === []) {
            return [];
        }

        $offers = self::offersFor($client, $requested);

        /* Deduplicated: two of the same service on one booking need two
           credits, and this asks per service. The count is what limits it. */
        $covered = [];
        $used = [];

        foreach ($requested as $serviceId) {
            $serviceId = (int) $serviceId;
            $available = $offers[$serviceId]['remaining'] ?? 0;
            $spent = $used[$serviceId] ?? 0;

            if ($spent >= $available) {
                continue;
            }

            $used[$serviceId] = $spent + 1;
            $covered[] = $serviceId;
        }

        return $covered;
    }

    /**
     * Hold the credits down against a booking that has just been taken.
     *
     * Called inside the booking's own transaction, so an appointment that
     * fails to save has not spent anybody's massage.
     *
     * @param  array<int, int>  $valuesByService  what each covered line was worth
     * @param  list<int>  $serviceIds  one entry per line being covered
     */
    public static function apply(Booking $booking, Client $client, array $serviceIds, array $valuesByService, ?User $by = null): int
    {
        $takenMinor = 0;

        foreach ($serviceIds as $serviceId) {
            $credit = self::nextSpendable($client, (int) $serviceId);

            /* Gone between the quote and the save. The line is simply paid
               for; refusing the booking over it would be the screen arguing
               with a client who is standing at the desk. */
            if ($credit === null) {
                continue;
            }

            $value = (int) ($valuesByService[$serviceId] ?? 0);

            $credit->increment('quantity_used');

            MembershipCreditRedemption::create([
                'tenant_id' => $booking->tenant_id,
                'membership_credit_id' => $credit->id,
                'client_membership_id' => $credit->client_membership_id,
                'booking_id' => $booking->id,
                'service_id' => (int) $serviceId,
                'quantity' => 1,
                'value_minor' => $value,
                'redeemed_by' => $by?->id,
            ]);

            $takenMinor += $value;
        }

        return $takenMinor;
    }

    /**
     * Give back every credit a booking is holding.
     *
     * Called when the visit is cancelled, declined or marked a no-show: a
     * client whose appointment was called off has not used their massage, and
     * a credit quietly kept is the business taking something it did not
     * deliver.
     *
     * Idempotent, because the status handlers it hangs off can fire more than
     * once — only redemptions still held are released, so calling it twice
     * does not hand the same credit back twice.
     */
    public static function release(Booking $booking): int
    {
        return DB::transaction(function () use ($booking) {
            $held = MembershipCreditRedemption::query()
                ->held()
                ->where('booking_id', $booking->id)
                ->get();

            foreach ($held as $redemption) {
                /* Never below zero. A credit whose count somehow drifted is a
                   bug to find, not a reason to hand out a negative. */
                MembershipCredit::query()
                    ->whereKey($redemption->membership_credit_id)
                    ->where('quantity_used', '>', 0)
                    ->decrement('quantity_used');

                $redemption->forceFill(['released_at' => now()])->save();
            }

            return $held->count();
        });
    }

    /**
     * The credit to spend next on this service.
     *
     * Read fresh rather than from the offer list: between the quote and the
     * save somebody else may have spent it, and this is the moment it has to
     * be true.
     */
    private static function nextSpendable(Client $client, int $serviceId): ?MembershipCredit
    {
        return MembershipCredit::query()
            ->spendable()
            ->where('service_id', $serviceId)
            ->whereIn('client_membership_id', ClientMembership::query()
                ->where('client_id', $client->id)
                ->live()
                ->select('id'))
            ->orderByRaw('expires_on is null, expires_on')
            ->lockForUpdate()
            ->first();
    }

    /**
     * What a booking's credits took off it, as rows to read back.
     *
     * @return Collection<int, MembershipCreditRedemption>
     */
    public static function on(Booking $booking): Collection
    {
        return MembershipCreditRedemption::query()
            ->held()
            ->with('service')
            ->where('booking_id', $booking->id)
            ->get();
    }
}
