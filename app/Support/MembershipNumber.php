<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ClientMembership;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The number one client's membership is known by.
 *
 * Not the plan's code. MembershipCode names the product a business sells;
 * this names the thing a particular client bought. Two clients on the same
 * plan hold two memberships, and "which one are we talking about" cannot be
 * answered by a code they share.
 *
 * MBR-20260909-0001: what it is, the day it was sold, and which one that day.
 * The counter is per business and per day, so two businesses never collide
 * and one business's numbers stay short enough to read down a phone.
 *
 * Given once, at the sale, and never regenerated. It goes on the
 * confirmation, into the Sales ledger and onto whatever the client is handed,
 * so a number that could be reissued would eventually name two things.
 */
class MembershipNumber
{
    private const PREFIX = 'MBR';

    /** How many collisions to walk past before giving up on a day. */
    private const MAX_ATTEMPTS = 9999;

    /**
     * The next free number for this business, on this day.
     *
     * The highest that day plus one is normally free. It is not when rows
     * were numbered by hand or by a backfill, so the next free one is
     * searched for rather than assumed — and the search is bounded, so one
     * sale can never become an endless loop.
     */
    public static function next(?Tenant $tenant, ?Carbon $on = null): string
    {
        $day = ($on ?? Carbon::now())->format('Ymd');
        $stem = self::PREFIX.'-'.$day.'-';

        $used = self::used($tenant, $stem);
        $number = self::highest($used, $stem) + 1;

        for ($i = 0; $i < self::MAX_ATTEMPTS; $i++, $number++) {
            $candidate = $stem.str_pad((string) $number, 4, '0', STR_PAD_LEFT);

            if (! $used->contains($candidate)) {
                return $candidate;
            }
        }

        /* Ten thousand memberships to one business in one day: a scheme that
           has run out rather than a business that has been busy. The
           timestamp keeps the sale working and stays obviously different
           from the ordinary shape. */
        return $stem.'X'.($on ?? Carbon::now())->format('His');
    }

    /**
     * The numbers this business has already used that day.
     *
     * Cancelled and ended memberships among them: a number that has been on
     * a receipt cannot be handed to something else later.
     *
     * @return Collection<int, string>
     */
    private static function used(?Tenant $tenant, string $stem): Collection
    {
        return ClientMembership::withoutGlobalScopes()
            ->when(
                $tenant !== null,
                fn ($query) => $query->where('tenant_id', $tenant->getTenantKey())
            )
            ->where('reference', 'like', $stem.'%')
            ->pluck('reference')
            ->filter()
            ->map(fn (string $reference) => mb_strtoupper($reference))
            ->values();
    }

    /** @param  Collection<int, string>  $used */
    private static function highest(Collection $used, string $stem): int
    {
        return (int) $used
            ->map(fn (string $reference) => (int) mb_substr($reference, mb_strlen($stem)))
            ->max();
    }
}
