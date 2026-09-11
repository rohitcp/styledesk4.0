<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\MembershipCredit;
use App\Models\MembershipCreditRedemption;
use App\Models\Service;
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
        $costs = self::costsFor($serviceIds);

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
                /* What one booking of this costs. A service is not always
                   one credit: the business sets that per service, and a
                   screen that assumed one would promise a redemption the
                   engine then refuses. */
                'cost' => $costs[$serviceId] ?? 1,
                'expires_on' => $credit->expires_on?->toDateString(),
            ];
        }

        return $offers;
    }

    /**
     * Everything this client's memberships cover, membership by membership.
     *
     * The offers list answers "can this line be covered" for services already
     * on a booking. This answers the question a receptionist asks before
     * there is a booking at all: what has this client got, and what is left
     * of it — which is what the service selector needs to show a membership
     * section rather than a flat catalogue.
     *
     * Expired credits are left out. A benefit whose date has passed is not a
     * benefit, and listing it would have the desk promising a massage the
     * engine then refuses.
     *
     * @return list<array<string, mixed>>
     */
    public static function benefitsFor(?Client $client): array
    {
        if ($client === null) {
            return [];
        }

        $memberships = ClientMembership::query()
            ->where('client_id', $client->id)
            ->live()
            /* The redemptions as well as the counters. "How many are gone" is
               what the counter answers; "is one of them spoken for by
               Thursday's appointment" is not, and that is the difference
               between a benefit that is used and one that is merely
               reserved. */
            ->with(['plan', 'credits.service', 'credits.redemptions' => fn ($query) => $query
                ->held()
                ->with('booking:id,reference,date,starts_at,status')])
            ->orderBy('starts_on')
            ->get();

        $costs = self::costsFor(
            $memberships->flatMap(fn (ClientMembership $held) => $held->credits->pluck('service_id'))
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->all()
        );

        return $memberships
            ->map(fn (ClientMembership $held) => self::benefitRow($held, $costs))
            /* A membership whose every credit has expired has nothing to
               offer, and a section with no rows in it is a heading somebody
               has to interpret. */
            ->filter(fn (array $row) => $row['services'] !== [])
            ->values()
            ->all();
    }

    /**
     * One membership, and what is left of each thing it covers.
     *
     * @param  array<int, int>  $costs
     * @return array<string, mixed>
     */
    private static function benefitRow(ClientMembership $held, array $costs): array
    {
        $services = $held->credits
            ->reject(fn (MembershipCredit $credit) => $credit->isExpired())
            ->map(function (MembershipCredit $credit) use ($costs) {
                $cost = $costs[(int) $credit->service_id] ?? 1;
                $available = $credit->remaining() >= $cost;

                /* Included = available + reserved + used. The counter on the
                   credit is available's other half — it counts everything
                   spoken for — so the split between the two comes from the
                   redemptions rather than from a third column that could
                   drift away from them. */
                $reservations = $credit->redemptions->reject(fn (MembershipCreditRedemption $redemption) => $redemption->isConsumed());
                $reserved = (int) $reservations->sum('quantity');
                $used = max(0, (int) $credit->quantity_used - $reserved);

                return [
                    'service_id' => (int) $credit->service_id,
                    'name' => $credit->service?->name ?? '—',
                    'included' => (int) $credit->quantity_granted,
                    'used' => $used,
                    'reserved' => $reserved,
                    'remaining' => $credit->remaining(),
                    /* What one booking of it costs, and so whether what is
                       left is enough to take another. */
                    'cost' => $cost,
                    'available' => $available,
                    /* Which of the three the reader is looking at. Reserved
                       beats used where both are true: what a desk needs to
                       know about a benefit it cannot spend today is that it
                       is coming back, and when. */
                    'status' => match (true) {
                        $available => 'available',
                        $reserved > 0 => 'reserved',
                        default => 'used',
                    },
                    /* The appointments holding it. Without these, a benefit
                       that has run out and one that is spoken for by
                       Thursday read exactly alike, and the desk has to go
                       looking for the difference. */
                    'reservations' => self::reservationRows($reservations),
                    'expires_on' => $credit->expires_on?->translatedFormat('j M Y'),
                ];
            })
            ->values()
            ->all();

        /* The cycle this lot of credits belongs to. A recurring membership's
           allowance is per cycle, and "1 left" means nothing without knowing
           until when. */
        $cycle = $held->credits->first(fn (MembershipCredit $credit) => $credit->period_start && $credit->period_end);

        return [
            'id' => $held->id,
            'reference' => $held->reference,
            'name' => $held->plan?->name ?? '—',
            'type' => $held->type,
            'type_label' => __('membership.types.'.$held->type),
            'status_label' => $held->statusLabel(),
            'status_class' => $held->statusClass(),
            'cycle' => $cycle === null
                ? null
                : $cycle->period_start->translatedFormat('j M').' – '.$cycle->period_end->translatedFormat('j M Y'),
            'renews_on' => $held->next_billing_on?->translatedFormat('j M Y'),
            /* The membership's own screen, opened in a new tab rather than
               over the booking: a desk checking what a plan includes has a
               half-taken appointment behind it that it cannot afford to
               lose. */
            'plan_url' => route('membership.sales.show', $held),
            'services' => $services,
        ];
    }

    /**
     * The appointments a benefit is being held for.
     *
     * Named and dated, with a way to reach each one. A desk told only that a
     * benefit is spoken for has to go and find out by whom; told which
     * appointment, it can decide in front of the client whether to move that
     * one or simply charge for this one.
     *
     * @param  Collection<int, MembershipCreditRedemption>  $reservations
     * @return list<array<string, mixed>>
     */
    private static function reservationRows(Collection $reservations): array
    {
        return $reservations
            ->filter(fn (MembershipCreditRedemption $redemption) => $redemption->booking !== null)
            ->sortBy(fn (MembershipCreditRedemption $redemption) => $redemption->booking->date?->toDateString())
            ->map(function (MembershipCreditRedemption $redemption) {
                $booking = $redemption->booking;

                return [
                    'booking_id' => (int) $booking->id,
                    'reference' => $booking->reference,
                    'date' => $booking->date?->translatedFormat('j M Y'),
                    'at' => TimeFormat::time($booking->starts_at),
                    'quantity' => max(1, (int) $redemption->quantity),
                    'url' => route('bookings.show', $booking),
                    /* Straight to the dialogue the booking's own header
                       offers, rather than a second cancellation path here:
                       cancelling asks for a reason and is gated on a
                       permission, and both of those live there. A reader
                       without that permission simply lands on the booking. */
                    'cancel_url' => route('bookings.show', $booking).'?action=cancelled',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The client walked in, so the benefit is theirs.
     *
     * Reserving a credit holds it down; this is what spends it. Called at
     * check-in, which is the point the business has decided the benefit was
     * delivered — before that the appointment could still be called off, and
     * a benefit marked used for a visit that never happened is one the client
     * would be right to argue about.
     *
     * Idempotent: only redemptions not yet consumed are stamped, so a desk
     * that presses Check In twice does not move the date.
     */
    public static function consume(Booking $booking): int
    {
        return MembershipCreditRedemption::query()
            ->reserved()
            ->where('booking_id', $booking->id)
            ->update(['consumed_at' => now()]);
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

        /* Counted in credits rather than in lines. Two of the same service
           on one booking need two redemptions, and a redemption is not
           always one credit — a service the business priced at two costs two
           every time it is taken. */
        $covered = [];
        $used = [];

        foreach ($requested as $serviceId) {
            $serviceId = (int) $serviceId;
            $available = $offers[$serviceId]['remaining'] ?? 0;
            $cost = $offers[$serviceId]['cost'] ?? 1;
            $spent = $used[$serviceId] ?? 0;

            /* Not enough left for a whole redemption. Half a massage is not
               a thing to hand somebody, so the line is simply paid for. */
            if ($spent + $cost > $available) {
                continue;
            }

            $used[$serviceId] = $spent + $cost;
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

        $costs = self::costsFor($serviceIds);

        foreach ($serviceIds as $serviceId) {
            $cost = $costs[(int) $serviceId] ?? 1;
            $credit = self::nextSpendable($client, (int) $serviceId, $cost);

            /* Gone between the quote and the save, or no longer enough left
               for a whole redemption. The line is simply paid for; refusing
               the booking over it would be the screen arguing with a client
               who is standing at the desk. */
            if ($credit === null) {
                continue;
            }

            $value = (int) ($valuesByService[$serviceId] ?? 0);

            /* What the service costs, not one. Taken from a single credit
               row rather than split across two: a redemption belongs to one
               membership, and half of it charged to another is a record
               nobody could explain. */
            $credit->increment('quantity_used', $cost);

            MembershipCreditRedemption::create([
                'tenant_id' => $booking->tenant_id,
                'membership_credit_id' => $credit->id,
                'client_membership_id' => $credit->client_membership_id,
                'booking_id' => $booking->id,
                'service_id' => (int) $serviceId,
                'quantity' => $cost,
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
                /* Exactly what was taken, which is written on the redemption
                   rather than assumed to be one: a two-credit service gives
                   two back, and a service repriced since is not the question
                   — what was spent is.

                   Never below zero. A credit whose count somehow drifted is a
                   bug to find, not a reason to hand out a negative. */
                $taken = max(1, (int) $redemption->quantity);

                MembershipCredit::query()
                    ->whereKey($redemption->membership_credit_id)
                    ->where('quantity_used', '>=', $taken)
                    ->decrement('quantity_used', $taken);

                $redemption->forceFill(['released_at' => now()])->save();
            }

            return $held->count();
        });
    }

    /**
     * What one booking of each service costs in credits.
     *
     * Read once for the whole set rather than once per line: a booking of
     * four massages asks about one service, and four queries for one number
     * is three too many. Not memoised beyond the call — a service repriced
     * between two bookings must be read again.
     *
     * @param  list<int>  $serviceIds
     * @return array<int, int>
     */
    private static function costsFor(array $serviceIds): array
    {
        if ($serviceIds === []) {
            return [];
        }

        return Service::withoutGlobalScopes()
            ->whereIn('id', array_unique(array_map('intval', $serviceIds)))
            ->pluck('credit_usage', 'id')
            ->map(fn ($usage) => max(1, (int) $usage))
            ->all();
    }

    /**
     * The credit to spend next on this service.
     *
     * Read fresh rather than from the offer list: between the quote and the
     * save somebody else may have spent it, and this is the moment it has to
     * be true.
     */
    private static function nextSpendable(Client $client, int $serviceId, int $cost = 1): ?MembershipCredit
    {
        return MembershipCredit::query()
            ->spendable()
            /* Enough for the whole redemption on this one membership. A
               two-credit service taken half from one membership and half
               from another is a record nobody could explain, and a refund
               nobody could work out. */
            ->whereRaw('quantity_granted - quantity_used >= ?', [max(1, $cost)])
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
