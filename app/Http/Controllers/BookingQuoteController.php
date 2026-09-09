<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Service;
use App\Models\TipSettings;
use App\Support\BookingTotals;
use App\Support\Currencies;
use App\Support\MembershipCredits;
use App\Support\Promotions;
use App\Support\Tips;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * What a booking comes to, before it exists.
 *
 * The booking screen has to answer "what will this cost" while somebody is
 * still choosing — as they switch between card and cash, type a coupon code,
 * pick a tip. Every one of those changes the answer, and several of them
 * depend on rules the browser cannot be told without handing over the whole
 * promotions table.
 *
 * So the arithmetic happens here, in one place, and the screen renders what
 * it is given. That also means the quote the client is shown and the bill the
 * booking is written with come out of the same code — which is the only way
 * they can be guaranteed to agree.
 *
 * The order is fixed and worth stating, because every other order gives a
 * different total:
 *
 *   1. price each service by how it is being paid for
 *   2. add them up
 *   3. take the discount off
 *   4. work the tip out on what is left
 *   5. tax
 *   6. total
 *
 * The tip is worked out after the discount and before tax: it is a share of
 * the work, and a client who used a coupon did not buy more work.
 */
class BookingQuoteController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('appointments.create', 'own'), 403);

        $data = $request->validate([
            'services' => ['array'],
            'services.*' => ['integer'],
            'payment_method' => ['nullable', Rule::in(['card', 'cash'])],
            'client_id' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer'],
            'coupon' => ['nullable', 'string', 'max:40'],
            /* Which lines the desk chose to pay for with a membership credit.
               A request rather than an instruction: what is actually coverable
               is decided here, against the credits that exist right now. */
            'membership_credits' => ['nullable', 'array'],
            'membership_credits.*' => ['integer'],
            /* Either a percentage of the discounted subtotal, or an amount
               somebody typed. Never both — see the note in the tip block. */
            'tip_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'tip_amount' => ['nullable', 'numeric', 'min:0'],
            /* Whether anybody has answered the tip question yet.
             *
             * Without it "no tip" and "not asked yet" arrive as the same
             * empty field, and the screen either loses the business's default
             * or overrides a client who deliberately said no. */
            'tip_chosen' => ['nullable', 'boolean'],
        ]);

        $currency = Currencies::resolve();
        $method = $data['payment_method'] ?? 'card';
        $tenant = $request->user()->tenant;

        $services = Service::query()
            ->with(['prices', 'category'])
            ->whereIn('id', $data['services'] ?? [])
            ->get();

        /* 1 & 2 — each service at the price for how it is being paid. */
        $lines = $services->map(fn (Service $service) => [
            'service_id' => (int) $service->id,
            'category_id' => $service->service_category_id === null ? null : (int) $service->service_category_id,
            'name' => $service->name,
            'price_minor' => $service->priceMinorFor($currency, $method),
        ])->values();

        $subtotal = (int) $lines->sum('price_minor');

        $client = isset($data['client_id']) ? Client::query()->find($data['client_id']) : null;

        /* 2a — membership credits, before the coupon.
         *
         * A credit pays for one whole service, so a covered line leaves the
         * bill entirely rather than being discounted. It has to happen first:
         * ten per cent off a massage the client is not paying for is ten per
         * cent of nothing, and a coupon worked out before the credits would
         * take it off work that was already covered.
         *
         * What the client could cover is answered whether or not any is
         * applied — that is what the screen offers them. */
        $offers = MembershipCredits::offersFor($client, $lines->pluck('service_id')->all());
        $covered = MembershipCredits::coverable($client, array_map('intval', $data['membership_credits'] ?? []));

        /* One credit per line, in the order the lines were chosen: two
           massages on one booking with one credit left covers the first. */
        $remainingCover = array_count_values($covered);
        $creditMinor = 0;

        $payable = $lines->reject(function (array $line) use (&$remainingCover, &$creditMinor) {
            $serviceId = $line['service_id'];

            if (($remainingCover[$serviceId] ?? 0) < 1) {
                return false;
            }

            $remainingCover[$serviceId]--;
            $creditMinor += $line['price_minor'];

            return true;
        })->values();

        /* 3 — the coupon, against what is left to pay and at the price for
           this method. Ten per cent of the card price and ten per cent of the
           cash price are different amounts, and the client is owed the one
           they are actually paying. */
        $discount = 0;
        $coupon = null;
        $couponError = null;

        if (filled($data['coupon'] ?? null)) {
            $promotion = Promotions::byCode((string) $data['coupon']);

            if ($promotion === null) {
                $couponError = __('promotions.refused.unknown_code');
            } else {
                $couponError = Promotions::refusal(
                    $promotion,
                    $payable,
                    $client,
                    isset($data['location_id']) ? (int) $data['location_id'] : null,
                );

                if ($couponError === null) {
                    $discount = Promotions::discountMinor($promotion, $payable);
                    $coupon = [
                        'id' => $promotion->id,
                        'code' => $promotion->code,
                        'name' => $promotion->name,
                        'label' => $promotion->discountLabel($currency),
                        'discount_minor' => $discount,
                    ];
                }
            }
        }

        $discounted = max(0, $subtotal - $creditMinor - $discount);

        /* 4 — the tip.
         *
         * A percentage is worked out again every time anything moves; an
         * amount somebody typed is left exactly as typed. That is the whole
         * difference between the two, and getting it wrong either silently
         * changes a number a person chose or freezes one they meant as a
         * share.
         *
         * Until somebody answers, the business's own default applies. A
         * salon that configured 15/18/20/25 with 15 as the default meant the
         * screen to open on 15 — opening on nothing is the screen quietly
         * choosing No Tip on the client's behalf, which is the one answer
         * nobody asked for. */
        $tipSettings = TipSettings::forTenant($tenant);
        $tipMinor = 0;
        $tipPercent = $data['tip_percent'] ?? null;
        $tipChosen = (bool) ($data['tip_chosen'] ?? false);

        [$defaultPercent, $defaultTipMinor] = $this->defaultTip($tipSettings, $discounted);

        if ($tipSettings->is_enabled) {
            /* What was actually sent, first. A request naming a tip is
               quoted the tip it named — the flag beside it says whether
               anybody has been *asked*, which is a different question and
               only decides what to do when nothing was named. Reading the
               flag first meant a caller that sent 15% was quoted the
               business default instead, silently. */
            if (isset($data['tip_amount'])) {
                $tipMinor = (int) round(((float) $data['tip_amount']) * 100);
                $tipPercent = null;
            } elseif ($tipPercent !== null) {
                $tipMinor = Tips::percentOf($discounted, (int) $tipPercent);
            } elseif (! $tipChosen) {
                /* Nobody has been asked yet. The default is an answer the
                   business already gave, so it is applied rather than
                   suggested — the total under it is the one the desk reads
                   out. */
                $tipPercent = $defaultPercent;
                $tipMinor = $defaultTipMinor;
            }

            /* Asked, and answered with nothing: that is No tip, and the
               nought it starts on is the right answer. */
        }

        /* 5 & 6 — tax on the discounted work, and the total. The tip is not
           taxed: it is not the salon's revenue. */
        /* 5 & 6 — tax and the total, on what is actually being paid for.
           The credits go in as a reduction alongside the discount because
           that is what they do to the bill; they are reported apart from it
           because a coupon is the business giving money away and a credit is
           the client spending something they already bought. */
        $totals = BookingTotals::of($lines->pluck('price_minor'), $currency, $discount + $creditMinor);

        return response()->json([
            'currency' => $currency,
            'method' => $method,
            'lines' => $lines->map(fn (array $line) => [
                'name' => $line['name'],
                'price' => $totals->money($line['price_minor']),
            ])->all(),
            'subtotal_minor' => $subtotal,
            'subtotal' => $totals->money($subtotal),
            'discount_minor' => $discount,
            'discount' => $totals->money($discount),
            'coupon' => $coupon,
            'coupon_error' => $couponError,
            /* What the client could cover, and what they are covering. The
               first is the offer the screen makes; the second is what it has
               actually taken off. */
            'membership_offers' => $offers,
            'membership_covered' => $covered,
            'membership_credit_minor' => $creditMinor,
            'membership_credit' => $totals->money($creditMinor),
            'tip_minor' => $tipMinor,
            'tip' => $totals->money($tipMinor),
            'tip_percent' => $tipPercent,
            'tips_enabled' => (bool) $tipSettings->is_enabled,
            'tip_percentages' => $tipSettings->offeredPercentages(),
            /* The till's own row, in whichever units this business tips in.
               A business set to flat sums offers $5/$10/$15/$20 and no
               percentage among them — showing a row of percentages there
               would be offering a choice its settings say it does not
               make. */
            'tip_type' => $tipSettings->default_tip_type,
            'tip_amounts' => $tipSettings->offeredAmounts(),
            /* Worked out here rather than in the screen: what a flat sum
               comes to is a sum, and a chip has to show it as money. */
            'tip_amount_options' => collect($tipSettings->offeredAmounts())
                ->map(fn (int $amount) => [
                    'value' => $amount,
                    /* Never more than the work it is on: a flat tip larger
                       than the bill is somebody's typo, not a decision. */
                    'minor' => min($amount * 100, $discounted),
                    'label' => $totals->money(min($amount * 100, $discounted)),
                ])
                ->values()
                ->all(),
            'allow_no_tip' => (bool) $tipSettings->allow_no_tip,
            'require_selection' => (bool) $tipSettings->require_selection,
            /* What the screen opens on, and what it would go back to. Sent
               even once somebody has chosen, so the two never have to be
               worked out in two places. */
            'default_tip_percent' => $defaultPercent,
            'default_tip_minor' => $defaultTipMinor,
            /* Whether this figure is the business's answer or a person's.
               The screen needs to know which chip to light. */
            'tip_chosen' => $tipChosen,
            'tax_minor' => $totals->taxMinor,
            'tax' => $totals->money($totals->taxMinor),
            'total_minor' => $totals->totalMinor + $tipMinor,
            'total' => $totals->money($totals->totalMinor + $tipMinor),
        ]);
    }

    /**
     * What the business tips by default, as a percentage and as money.
     *
     * A percentage default lights a chip, so it comes back as one. A fixed
     * default is an amount and has no chip to light — it comes back with a
     * null percent and lands in the custom box instead.
     *
     * Never more than the work it is on: a flat tip larger than the bill is
     * somebody's typo, not a decision. The same rule Tips::defaultMinor
     * applies once a booking exists.
     *
     * @return array{0: ?int, 1: int}
     */
    private function defaultTip(TipSettings $settings, int $baseMinor): array
    {
        if (! $settings->is_enabled) {
            return [null, 0];
        }

        $value = (int) $settings->default_tip_value;

        if ($settings->default_tip_type === 'fixed') {
            return [null, min($value * 100, $baseMinor)];
        }

        return [$value, Tips::percentOf($baseMinor, $value)];
    }
}
