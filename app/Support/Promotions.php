<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Whether a promotion may be used, and what it takes off.
 *
 * All the rules live here rather than in the screens that apply them,
 * because there are two of those — the client typing a code into online
 * booking, and the desk adding one at checkout — and they must never
 * disagree. A coupon the website accepts and the till refuses is worse than
 * one nobody can use.
 *
 * Nothing here writes anything. `redeem()` is the only method that does, and
 * it is called once the booking is settled.
 */
class Promotions
{
    /**
     * Why a promotion cannot be used, or null if it can.
     *
     * A reason rather than a boolean: the desk is going to have to tell the
     * client something, and "invalid" is not an answer anybody can act on.
     *
     * @param  Collection<int, array{service_id: ?int, price_minor: int, category_id: ?int}>  $lines
     */
    public static function refusal(
        Promotion $promotion,
        Collection $lines,
        ?Client $client = null,
        ?int $locationId = null,
        ?Carbon $on = null,
    ): ?string {
        $on ??= Carbon::today();

        if ($promotion->is_disabled || $promotion->is_draft) {
            return __('promotions.refused.not_running');
        }

        if ($promotion->starts_on !== null && $promotion->starts_on->gt($on)) {
            return __('promotions.refused.not_started');
        }

        if ($promotion->ends_on !== null && $promotion->ends_on->lt($on)) {
            return __('promotions.refused.expired');
        }

        /* Which days it runs. An empty list means every day rather than
           none: a promotion nobody could ever use is not what a blank set
           of checkboxes means. */
        $days = $promotion->days ?: [];

        if ($days !== [] && ! in_array((int) $on->dayOfWeek, array_map('intval', $days), true)) {
            return __('promotions.refused.wrong_day');
        }

        if ($promotion->location_mode === 'selected'
            && ! $promotion->locations->pluck('id')->contains($locationId)) {
            return __('promotions.refused.wrong_location');
        }

        if (($refusal = self::clientRefusal($promotion, $client)) !== null) {
            return $refusal;
        }

        /* What the promotion actually covers. Nothing eligible is a refusal
           rather than a discount of nought — a client told a coupon "worked"
           and given nothing off would rightly complain. */
        $eligible = self::eligibleMinor($promotion, $lines);

        if ($eligible <= 0) {
            return __('promotions.refused.no_eligible_services');
        }

        /* Minimum spend is read against the whole bill, not the eligible
           part: it is there to stop £25 off being taken against a £30
           booking, and that is a fact about the booking. */
        $total = (int) $lines->sum('price_minor');

        if ($promotion->min_spend_minor !== null && $total < $promotion->min_spend_minor) {
            return __('promotions.refused.under_minimum', [
                'amount' => Money::format($promotion->min_spend_minor / 100, Currencies::resolve()),
            ]);
        }

        if ($promotion->total_limit !== null
            && $promotion->redemptions()->count() >= $promotion->total_limit) {
            return __('promotions.refused.fully_redeemed');
        }

        if ($promotion->per_client_limit !== null && $client !== null) {
            $used = $promotion->redemptions()->where('client_id', $client->id)->count();

            if ($used >= $promotion->per_client_limit) {
                return __('promotions.refused.client_limit');
            }
        }

        return null;
    }

    /**
     * Whether this client may use it.
     *
     * "New" means nobody has ever finished an appointment for them — which
     * is the offer every salon runs and the one worth getting exactly right:
     * a booking taken and cancelled does not make somebody an existing
     * client.
     */
    private static function clientRefusal(Promotion $promotion, ?Client $client): ?string
    {
        if ($promotion->eligibility === 'all') {
            return null;
        }

        /* A walk-in with no record cannot be shown to be new or returning,
           so anything narrower than "everyone" cannot be checked. */
        if ($client === null) {
            return __('promotions.refused.needs_a_client');
        }

        $visits = Booking::query()
            ->where('client_id', $client->id)
            ->where('status', 'completed')
            ->count();

        return match ($promotion->eligibility) {
            'new' => $visits > 0 ? __('promotions.refused.new_only') : null,
            'existing' => $visits === 0 ? __('promotions.refused.existing_only') : null,
            'selected' => $promotion->clients->pluck('id')->contains($client->id)
                ? null
                : __('promotions.refused.not_for_this_client'),
            default => null,
        };
    }

    /**
     * The part of the bill this promotion applies to.
     *
     * This is the whole reason "20% off facials" is different from "20% off
     * the bill": the percentage is taken against the facials, not against
     * the shampoo the client bought on the way out.
     *
     * @param  Collection<int, array{service_id: ?int, price_minor: int, category_id: ?int}>  $lines
     */
    public static function eligibleMinor(Promotion $promotion, Collection $lines): int
    {
        return (int) match ($promotion->applies_to) {
            'services' => $lines
                ->filter(fn (array $line) => $promotion->services->pluck('id')->contains($line['service_id']))
                ->sum('price_minor'),

            'categories' => $lines
                ->filter(fn (array $line) => $promotion->serviceCategories->pluck('id')->contains($line['category_id']))
                ->sum('price_minor'),

            /* Entire booking and all services are the same sum today —
               there is nothing on a booking but services. They are kept
               apart because the day a booking can carry a retail line, they
               stop being the same. */
            default => $lines->sum('price_minor'),
        };
    }

    /**
     * What it takes off, in minor units.
     *
     * Never more than the part it applies to: a £25 coupon against a £20
     * facial takes £20, not £25 with the difference coming out of the rest
     * of the bill.
     *
     * @param  Collection<int, array{service_id: ?int, price_minor: int, category_id: ?int}>  $lines
     */
    public static function discountMinor(Promotion $promotion, Collection $lines): int
    {
        $eligible = self::eligibleMinor($promotion, $lines);

        if ($eligible <= 0) {
            return 0;
        }

        $discount = $promotion->discount_type === 'percent'
            ? (int) round($eligible * $promotion->discount_value / 100)
            : (int) $promotion->discount_value;

        return max(0, min($discount, $eligible));
    }

    /**
     * The lines of a booking, in the shape the rules read.
     *
     * @return Collection<int, array{service_id: ?int, price_minor: int, category_id: ?int}>
     */
    public static function linesOf(Booking $booking): Collection
    {
        $booking->loadMissing('services.service');

        return $booking->services->map(fn ($line) => [
            'service_id' => $line->service_id === null ? null : (int) $line->service_id,
            'category_id' => $line->service?->service_category_id === null
                ? null
                : (int) $line->service->service_category_id,
            'price_minor' => (int) $line->price_minor,
        ])->values();
    }

    /**
     * Find a coupon by the code somebody typed.
     *
     * Case and surrounding space are forgiven — a client reading a code off
     * a poster is not proof-reading it.
     */
    public static function byCode(string $code): ?Promotion
    {
        $code = mb_strtoupper(trim($code));

        return $code === '' ? null : Promotion::query()
            ->with(['services', 'serviceCategories', 'locations', 'clients'])
            ->where('type', 'coupon')
            ->where('code', $code)
            ->first();
    }

    /**
     * Write down that it was used.
     *
     * Called once the booking is settled rather than when the code is typed:
     * a coupon applied to a booking somebody then abandoned has not been
     * redeemed, and counting it would burn a limited promotion on nothing.
     */
    public static function redeem(Promotion $promotion, Booking $booking, int $discountMinor, ?int $userId = null): PromotionRedemption
    {
        return PromotionRedemption::create([
            'tenant_id' => $booking->tenant_id,
            'promotion_id' => $promotion->id,
            'booking_id' => $booking->id,
            'client_id' => $booking->client_id,
            'discount_minor' => $discountMinor,
            'booking_total_minor' => (int) $booking->total_minor,
            'redeemed_by' => $userId,
            'created_at' => now(),
        ]);
    }

    /**
     * What a promotion has actually done.
     *
     * @return array<string, int>
     */
    public static function report(Promotion $promotion): array
    {
        $redemptions = $promotion->redemptions()->get(['client_id', 'discount_minor', 'booking_total_minor']);

        return [
            'redemptions' => $redemptions->count(),
            'clients' => $redemptions->pluck('client_id')->filter()->unique()->count(),
            'discount_minor' => (int) $redemptions->sum('discount_minor'),
            /* What the bookings it was used on came to. Not a claim that the
               promotion caused them — attribution is a harder question, and
               a number that quietly claimed it would be believed. */
            'revenue_minor' => (int) $redemptions->sum('booking_total_minor'),
        ];
    }
}
