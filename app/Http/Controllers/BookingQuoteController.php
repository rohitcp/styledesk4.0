<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Service;
use App\Models\TipSettings;
use App\Support\BookingTotals;
use App\Support\Currencies;
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
            /* Either a percentage of the discounted subtotal, or an amount
               somebody typed. Never both — see the note in the tip block. */
            'tip_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'tip_amount' => ['nullable', 'numeric', 'min:0'],
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

        /* 3 — the coupon, against the price for this method. Ten per cent of
           the card price and ten per cent of the cash price are different
           amounts, and the client is owed the one they are actually paying. */
        $discount = 0;
        $coupon = null;
        $couponError = null;

        if (filled($data['coupon'] ?? null)) {
            $promotion = Promotions::byCode((string) $data['coupon']);

            if ($promotion === null) {
                $couponError = __('promotions.refused.unknown_code');
            } else {
                $client = isset($data['client_id']) ? Client::query()->find($data['client_id']) : null;

                $couponError = Promotions::refusal(
                    $promotion,
                    $lines,
                    $client,
                    isset($data['location_id']) ? (int) $data['location_id'] : null,
                );

                if ($couponError === null) {
                    $discount = Promotions::discountMinor($promotion, $lines);
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

        $discounted = max(0, $subtotal - $discount);

        /* 4 — the tip.
         *
         * A percentage is worked out again every time anything moves; an
         * amount somebody typed is left exactly as typed. That is the whole
         * difference between the two, and getting it wrong either silently
         * changes a number a person chose or freezes one they meant as a
         * share. */
        $tipSettings = TipSettings::forTenant($tenant);
        $tipMinor = 0;
        $tipPercent = $data['tip_percent'] ?? null;

        if ($tipSettings->is_enabled) {
            if (isset($data['tip_amount'])) {
                $tipMinor = (int) round(((float) $data['tip_amount']) * 100);
                $tipPercent = null;
            } elseif ($tipPercent !== null) {
                $tipMinor = Tips::percentOf($discounted, (int) $tipPercent);
            }
        }

        /* 5 & 6 — tax on the discounted work, and the total. The tip is not
           taxed: it is not the salon's revenue. */
        $totals = BookingTotals::of($lines->pluck('price_minor'), $currency, $discount);

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
            'tip_minor' => $tipMinor,
            'tip' => $totals->money($tipMinor),
            'tip_percent' => $tipPercent,
            'tips_enabled' => (bool) $tipSettings->is_enabled,
            'tip_percentages' => $tipSettings->offeredPercentages(),
            'allow_no_tip' => (bool) $tipSettings->allow_no_tip,
            'tax_minor' => $totals->taxMinor,
            'tax' => $totals->money($totals->taxMinor),
            'total_minor' => $totals->totalMinor + $tipMinor,
            'total' => $totals->money($totals->totalMinor + $tipMinor),
        ]);
    }
}
