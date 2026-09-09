<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\MembershipPlan;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The reference a membership is known by internally.
 *
 * Generated rather than typed. A code is only useful if it is unique and
 * everybody spells it the same way, and neither survives being asked of a
 * person filling in a form at the desk — "MS-15" and "ms15" are two answers
 * to a question that has one.
 *
 * The shape is PKG-20260909-0001: what it is, when it was made, and which one
 * that day. The date is in it because a reference that has to be looked up is
 * usually being looked up alongside a date, and the counter is per business
 * and per day so two businesses can never collide and one business's numbers
 * stay short enough to read out loud.
 *
 * Assigned once, at the first save, and never regenerated: a draft reopened,
 * a plan edited, a plan taken off sale and put back — all keep the code they
 * were given, because the whole point of a reference is that it goes on
 * meaning the same row.
 */
class MembershipCode
{
    /** Recurring memberships and packages are counted apart. */
    private const PREFIXES = ['package' => 'PKG', 'recurring' => 'MEM'];

    /** How many collisions to walk past before giving up on a day. */
    private const MAX_ATTEMPTS = 999;

    /**
     * The next free code for this business, on this day.
     *
     * The highest number that day plus one is normally free. It is not when a
     * code was typed by hand before this existed, so the next free one is
     * searched for rather than assumed — and the search is bounded, so one
     * save can never become an endless loop.
     */
    public static function next(?Tenant $tenant, string $type, ?Carbon $on = null): string
    {
        $prefix = self::prefixFor($type);
        $day = ($on ?? Carbon::now())->format('Ymd');
        $stem = $prefix.'-'.$day.'-';

        $used = self::usedCodes($tenant, $stem);
        $number = self::highestNumber($used, $stem) + 1;

        for ($i = 0; $i < self::MAX_ATTEMPTS; $i++, $number++) {
            $candidate = $stem.str_pad((string) $number, 4, '0', STR_PAD_LEFT);

            if (! $used->contains($candidate)) {
                return $candidate;
            }
        }

        /* Every number for the day is taken — ten thousand memberships in one
           afternoon, which is a scheme that has run out rather than a
           business that has been busy. The timestamp keeps the save working
           and stays obviously different from the ordinary shape. */
        return $stem.'X'.(($on ?? Carbon::now())->format('His'));
    }

    /** PKG for something bought once, MEM for something that bills again. */
    public static function prefixFor(string $type): string
    {
        return self::PREFIXES[$type] ?? 'MEM';
    }

    /**
     * The codes this business has already used that day.
     *
     * Drafts and disabled plans among them, and soft-deleted ones too: a
     * reference that has been on a receipt cannot be handed to something
     * else later.
     *
     * @return Collection<int, string>
     */
    private static function usedCodes(?Tenant $tenant, string $stem): Collection
    {
        return MembershipPlan::withoutGlobalScopes()
            ->when(
                $tenant !== null,
                fn ($query) => $query->where('tenant_id', $tenant->getTenantKey())
            )
            ->where('internal_code', 'like', $stem.'%')
            ->pluck('internal_code')
            ->filter()
            ->map(fn (string $code) => mb_strtoupper($code))
            ->values();
    }

    /** @param  Collection<int, string>  $used */
    private static function highestNumber(Collection $used, string $stem): int
    {
        return (int) $used
            ->map(fn (string $code) => (int) mb_substr($code, mb_strlen($stem)))
            ->max();
    }
}
