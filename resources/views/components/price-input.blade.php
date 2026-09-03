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
     * A deposit per price rather than one per service.
     *
     * The deposit belongs to the price it is a deposit on: 20% of one price
     * and 20% of another are different amounts, and a single service-wide
     * setting cannot say "deposit on the premium price only".
     *
     * Keyed by currency: ['USD' => ['required' => true, 'type' => 'percent',
     * 'value' => '20']].
     */
    'deposits' => null,
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

    $depositFor = fn (string $code, string $key, $fallback = null) => old(
        'deposit.'.$code.'.'.$key,
        data_get($deposits, $code.'.'.$key, $fallback),
    );
@endphp

<div {{ $attributes }} data-price-input>
    @if ($label)
        <span class="block text-[13px] font-medium text-ink mb-1.5">
            {{ $label }}@if ($required) <span class="text-danger">*</span>@endif
        </span>
    @endif

    {{-- One block per currency, each a row of three fields with its own
         deposit switch beneath and a rule between blocks. One loop rather
         than a single-currency branch and a multi-currency branch: the two
         used to be separate markup and the deposit had to be written twice,
         which is two places for a field to go missing from. --}}
    <div class="space-y-4">
        @foreach ($priceCurrencies as $code)
            @php $depositOn = (bool) $depositFor($code, 'required', false); @endphp

            <div data-deposit-row>
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
                            {{-- The symbol sits in the field; the code sits
                                 beside it. Both, because the symbol is what
                                 makes the field read as money and the code is
                                 what says which money. --}}
                            <span class="styledesk_input__prefix pointer-events-none text-sub" aria-hidden="true">
                                {{ App\Support\Money::symbol($code) }}
                            </span>

                            <input id="price_{{ $code }}" type="text" inputmode="decimal"
                                   name="{{ $name }}[{{ $code }}]"
                                   value="{{ $priceValues[$code] }}"
                                   class="sd-input styledesk_input--prefixed"
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
                                <span class="styledesk_input__prefix pointer-events-none text-sub" aria-hidden="true">
                                    {{ App\Support\Money::symbol($code) }}
                                </span>

                                <input id="cash_price_{{ $code }}" type="text" inputmode="decimal"
                                       name="cash_price[{{ $code }}]"
                                       value="{{ $cashPriceValues[$code] }}"
                                       placeholder="{{ __('services.cash_price_same') }}"
                                       class="sd-input styledesk_input--prefixed"
                                       aria-label="{{ __('services.cash_price').' — '.$code }}"
                                       autocomplete="off">
                            </div>
                        </div>
                    @endif

                    {{-- Hidden until this price's own switch is on, so a
                         service that takes no deposit is a row of one
                         field. --}}
                    <div class="flex flex-wrap items-end gap-3" data-deposit-fields @unless ($depositOn) hidden @endunless>
                        {{-- The label is written here rather than passed to
                             the combo: the combo draws a heavier one, and
                             three fields on a row want three identical
                             labels or none of them look aligned. --}}
                        <div class="w-[150px]">
                            <span class="block text-[12px] text-sub mb-1">{{ __('services.deposit_type') }}</span>

                            <x-combo data-deposit-type
                                     :name="'deposit['.$code.'][type]'"
                                     :options="['fixed' => __('services.deposit_types.fixed'), 'percent' => __('services.deposit_types.percent')]"
                                     :selected="$depositFor($code, 'type', 'percent')"
                                     :ariaLabel="__('services.deposit_type')" />
                        </div>

                        {{-- The value field follows the type beside it: an
                             amount is money and wears the currency symbol; a
                             percentage is not, and wearing one would be a
                             field that lies about what it holds. Both the
                             label and the affordance change, because a reader
                             who has just chosen "percentage" and sees a $ in
                             the box will type dollars. --}}
                        @php $depositType = $depositFor($code, 'type', 'percent'); @endphp

                        <div data-deposit-value-field
                             data-amount-label="{{ __('services.deposit_amount') }}"
                             data-percent-label="{{ __('services.deposit_percent') }}">
                            <label for="deposit_{{ $code }}" class="block text-[12px] text-sub mb-1">
                                <span data-deposit-label>
                                    {{ $depositType === 'fixed'
                                        ? __('services.deposit_amount')
                                        : __('services.deposit_percent') }}
                                </span>
                            </label>

                            <div class="relative w-[130px]">
                                <span data-deposit-prefix aria-hidden="true"
                                      class="styledesk_input__prefix pointer-events-none text-sub"
                                      @unless ($depositType === 'fixed') hidden @endunless>
                                    {{ App\Support\Money::symbol($code) }}
                                </span>

                                <span data-deposit-suffix aria-hidden="true"
                                      class="styledesk_input__suffix pointer-events-none text-sub"
                                      @if ($depositType === 'fixed') hidden @endif>%</span>

                                <input id="deposit_{{ $code }}" type="text" inputmode="decimal"
                                       @class(['sd-input w-[130px]', 'styledesk_input--prefixed' => $depositType === 'fixed'])
                                       data-deposit-value
                                       name="deposit[{{ $code }}][value]"
                                       value="{{ $depositFor($code, 'value') }}"
                                       aria-label="{{ $depositType === 'fixed' ? __('services.deposit_amount') : __('services.deposit_percent') }}"
                                       autocomplete="off">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- The switch below the row it governs, and without a card
                     around it: this already sits inside the Price card, and a
                     box around one row inside another box reads as a panel
                     that failed to render. --}}
                <div class="mt-2.5">
                    <x-toggle class="styledesk_toggle--bare"
                              :name="'deposit['.$code.'][required]'" :label="__('services.deposit_required')"
                              :checked="$depositOn" data-deposit-toggle />
                </div>

                @error($name.'.'.$code)<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
                @error('deposit.'.$code.'.value')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
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

    @if ($hint)
        <p class="mt-1.5 text-[12px] text-sub">{{ $hint }}</p>
    @endif
</div>
