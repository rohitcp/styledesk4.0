{{--
    The pricing field, per §Pricing Behavior.

    One input when the business prices in one currency; a row per currency when
    it prices in several. The screen using this does not decide which — it asks
    for a price and gets whichever is right for this business, so a module
    written today keeps working when a business enables a second currency
    tomorrow.

    Nothing here converts. The business enters each price itself, so a rate
    moving overnight never changes what a client was quoted.

    Values post as name[CODE], which keeps the amount and the currency it is
    in together. A bare number would leave the reader of that column guessing
    which money it was, which is the ambiguity §Currency Display exists to
    remove.

    Written out rather than shown as markup: Blade compiles component tags
    before stripping comments, so an x-* tag inside a comment is compiled too.
--}}
@props([
    'name' => 'price',
    'label' => null,
    'values' => [],
    'hint' => null,
    'required' => false,
    /**
     * The service's one deposit rule, shown here rather than asked for here.
     *
     * It is configured once, above, and every price inherits it: a
     * percentage settles against whatever each price is, so twenty per cent
     * never has to be typed twice. This component only reports what that
     * comes to.
     *
     * ['required' => true, 'type' => 'percent', 'percent' => '20',
     *  'amounts' => ['USD' => '25.00']].
     */
    'deposit' => null,
    /**
     * The cash price, keyed by currency, where the screen has two.
     *
     * Null for a field that prices one way only — a deposit input has no
     * second price, and the component should not grow an empty box on every
     * screen that uses it.
     */
    'cashValues' => null,
])

@php
    $priceCurrencies = App\Support\Currencies::enabledFor(auth()->user()?->tenant);

    // old() first, then what is stored, so a failed submit never loses input.
    $priceValues = collect($priceCurrencies)
        ->mapWithKeys(fn (string $code) => [
            $code => old($name.'.'.$code, data_get($values, $code)),
        ]);

    $cashPriceValues = $cashValues === null ? null : collect($priceCurrencies)
        ->mapWithKeys(fn (string $code) => [
            $code => old('cash_price.'.$code, data_get($cashValues, $code)),
        ]);

    $depositOn = (bool) data_get($deposit, 'required', false);
    $depositType = (string) data_get($deposit, 'type', 'percent');

    /* What the rule comes to on one price, in words. Worked out here for the
       first paint and again in the browser as the price is typed, because a
       percentage of a number nobody has entered yet is not a figure worth
       printing. */
    /* What the rule comes to on one price, in words: "20% · $12.00", or
       just "$25.00" where it is a flat sum. Worked out here for the first
       paint and again in the browser as the price is typed — a percentage of
       a number nobody has entered yet is not a figure worth printing. */
    $depositWords = function (string $code, $price) use ($depositOn, $depositType, $deposit) {
        /* Nothing where there is no price: a currency this service is not
           priced in stores no deposit either, and printing one would
           advertise a figure the save is about to discard. */
        if (! $depositOn || ! is_numeric($price) || (float) $price <= 0) {
            return null;
        }

        $symbol = App\Support\Money::symbol($code);

        if ($depositType === 'fixed') {
            $amount = data_get($deposit, 'amounts.'.$code);

            return is_numeric($amount) ? $symbol.number_format((float) $amount, 2) : null;
        }

        $percent = data_get($deposit, 'percent');

        if (! is_numeric($percent)) {
            return null;
        }

        $words = rtrim(rtrim(number_format((float) $percent, 2, '.', ''), '0'), '.').'%';

        return $words.' · '.$symbol.number_format((float) $price * (float) $percent / 100, 2);
    };
@endphp

<div {{ $attributes }} data-price-input>
    @if ($label)
        <span class="block text-[13px] font-medium text-ink mb-1.5">
            {{ $label }}@if ($required) <span class="text-danger">*</span>@endif
        </span>
    @endif

    {{-- One block per currency: the prices, what the service's deposit rule
         comes to on them, and a rule between blocks. One loop rather than a
         single-currency branch and a multi-currency branch — the two used to
         be separate markup, which is two places for a field to go missing
         from. --}}
    <div class="space-y-4">
        @foreach ($priceCurrencies as $code)
            <div data-price-row data-currency="{{ $code }}">
                {{-- Price, type and amount on one line. items-end so the
                     three sit on a common baseline whatever their labels do,
                     and wrap so a narrow window stacks them rather than
                     squeezing three fields into a phone's width. --}}
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label for="price_{{ $code }}" class="block text-[12px] text-sub mb-1">
                            {{-- "Card price" only where there is a cash one
                                 beside it: a lone field labelled "card"
                                 would imply the business cannot take cash. --}}
                            {{ $priceCurrencies->count() > 1
                                ? $code.($cashPriceValues ? ' · '.__('services.card_price') : '')
                                : ($cashPriceValues ? __('services.card_price') : __('services.price')) }}
                        </label>

                        <div class="relative w-[160px]">
                            {{-- No symbol in the field: the label above says
                                 which money this is, and a second reading of
                                 it inside the box only crowds the amount. --}}
                            <input id="price_{{ $code }}" type="text" inputmode="decimal"
                                   name="{{ $name }}[{{ $code }}]"
                                   value="{{ $priceValues[$code] }}"
                                   class="sd-input"
                                   aria-label="{{ $label ? $label.' — '.$code : $code }}"
                                   autocomplete="off">
                        </div>
                    </div>

                    {{-- The cash price, beside the card one.

                         Two explicit prices rather than a discount off the
                         first: a business that charges the same either way,
                         or more for cash, is not doing anything wrong and a
                         stored percentage could not say so.

                         Blank means "the same as card" — which is what every
                         service priced before today means, and is why the
                         column is nullable rather than defaulted to nought. --}}
                    @if ($cashPriceValues)
                        <div>
                            <label for="cash_price_{{ $code }}" class="block text-[12px] text-sub mb-1">
                                {{ __('services.cash_price') }}
                            </label>

                            <div class="relative w-[160px]">
                                <input id="cash_price_{{ $code }}" type="text" inputmode="decimal"
                                       name="cash_price[{{ $code }}]"
                                       value="{{ $cashPriceValues[$code] }}"
                                       class="sd-input"
                                       aria-label="{{ __('services.cash_price').' — '.$code }}"
                                       autocomplete="off">
                            </div>
                        </div>
                    @endif

                </div>

                {{-- What the service's deposit rule comes to on this price.
                     Reported, not asked: it is set once above and every
                     price inherits it, so a percentage that changes there
                     changes here without anybody retyping it. Recomputed as
                     the price is typed. --}}
                @php $words = $depositWords($code, $priceValues[$code]); @endphp

                <p class="mt-2 text-[12px] text-sub" data-deposit-preview
                   data-symbol="{{ App\Support\Money::symbol($code) }}"
                   data-pattern="{{ __('services.deposit_of', ['amount' => '__AMOUNT__']) }}"
                   @unless ($words) hidden @endunless>{{ $words ? __('services.deposit_of', ['amount' => $words]) : '' }}</p>

                @error($name.'.'.$code)<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
            </div>

            {{-- Between blocks, never after the last: a rule under the final
                 row is a line with nothing below it. --}}
            @unless ($loop->last)
                <hr class="border-line">
            @endunless
        @endforeach
    </div>

    @if ($priceCurrencies->count() > 1)
        {{-- Said on the field itself, not only in settings. Someone typing
             three numbers is exactly the person who might assume the other two
             will fill themselves in. --}}
        <p class="mt-3 text-[12px] text-sub">{{ __('currency.no_conversion') }}</p>
    @endif

    @if ($deposit !== null && data_get($deposit, 'required'))
        {{-- Said once under the prices rather than beside each of them: the
             point is that nobody has to fill a deposit in here. --}}
        <p class="mt-3 text-[12px] text-faint">{{ __('services.deposit_inherits') }}</p>
    @endif

    @if ($hint)
        <p class="mt-1.5 text-[12px] text-sub">{{ $hint }}</p>
    @endif
</div>
