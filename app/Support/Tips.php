<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\Service;
use App\Models\TipSettings;
use Illuminate\Support\Collection;

/**
 * What can be tipped on a bill, and what to suggest.
 *
 * The one rule worth stating plainly: a tip is offered on the work, not on
 * the bill. A booking with a haircut and a bottle of shampoo on it should
 * suggest a tip on the haircut — twenty per cent of the shampoo is money the
 * stylist did not earn, and a till that quietly includes it is one the client
 * will eventually notice and distrust.
 *
 * So everything here is worked out from the *eligible* portion: the lines
 * whose service accepts tips. Where none of them do, there is nothing to ask
 * about and the section does not appear at all.
 */
class Tips
{
    /**
     * What this booking may be tipped on, in minor units.
     *
     * Zero is a real answer and means "do not ask": a bill of nothing but
     * retail has no tip to offer.
     */
    public static function eligibleMinor(Booking $booking, TipSettings $settings): int
    {
        if (! $settings->is_enabled) {
            return 0;
        }

        $booking->loadMissing('services.service');

        return (int) $booking->services
            ->filter(fn ($line) => self::accepts($line->service, $settings))
            ->sum('price_minor');
    }

    /**
     * Whether a service is tipped.
     *
     * Null on the service means "whatever the business says" rather than a
     * value of its own, so a salon that switches its default has switched it
     * for every service that never disagreed — which a copied number would
     * not have done.
     */
    public static function accepts(?Service $service, TipSettings $settings): bool
    {
        if (! $settings->is_enabled) {
            return false;
        }

        /* A line whose service has since been deleted keeps its price but has
           nothing to consult, so it follows the business. */
        return $service?->accepts_tips ?? true;
    }

    /**
     * What to offer the client, and what is already ticked.
     *
     * The percentages come out as amounts as well as rates, because the
     * question a client answers is "how much" and making them multiply is how
     * a till slows down.
     *
     * @return array<string, mixed>
     */
    public static function panel(Booking $booking, TipSettings $settings): array
    {
        $eligible = self::eligibleMinor($booking, $settings);

        if ($eligible <= 0) {
            return ['enabled' => false];
        }

        /* The strictest answer any tipped service on the bill gives.
           A booking containing one service that insists on an answer is a
           booking that insists on one — the alternative is a rule that
           quietly depends on which line happens to come first. */
        $lines = $booking->services->filter(fn ($line) => self::accepts($line->service, $settings));

        $required = $lines->contains(fn ($line) => $line->service?->tip_required ?? $settings->require_selection);
        $allowNone = $lines->every(fn ($line) => $line->service?->allow_no_tip ?? $settings->allow_no_tip);

        $suggested = collect($settings->offeredPercentages())
            ->map(fn (int $percent) => [
                'percent' => $percent,
                'minor' => self::percentOf($eligible, $percent),
            ])
            ->all();

        return [
            'enabled' => true,
            'eligible_minor' => $eligible,
            'suggested' => $suggested,
            /* What the business would pick if nobody chose. Read from the
               first tipped line that has an opinion, then from the
               business. */
            'default_minor' => self::defaultMinor($eligible, $lines, $settings),
            'require_selection' => $required,
            'allow_no_tip' => $allowNone,
        ];
    }

    /** A percentage of the eligible amount, rounded to the nearest penny. */
    public static function percentOf(int $eligibleMinor, int $percent): int
    {
        return (int) round($eligibleMinor * $percent / 100);
    }

    /**
     * The suggestion that starts out chosen.
     *
     * A fixed amount is taken as written; a percentage is worked out from the
     * eligible portion rather than the whole bill, for the same reason
     * everything else here is.
     *
     * @param  Collection<int, mixed>  $lines
     */
    private static function defaultMinor(int $eligibleMinor, $lines, TipSettings $settings): int
    {
        $line = $lines->first(fn ($line) => $line->service?->tip_value !== null);

        $type = $line?->service?->tip_type ?? $settings->default_tip_type;
        $value = (int) ($line?->service?->tip_value ?? $settings->default_tip_value);

        return $type === 'fixed'
            /* A flat tip larger than the work it is on is somebody's typo,
               not a decision. */
            ? min($value * 100, $eligibleMinor)
            : self::percentOf($eligibleMinor, $value);
    }
}
