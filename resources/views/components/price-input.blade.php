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
])

@php
    $priceCurrencies = App\Support\Currencies::enabledFor(auth()->user()?->tenant);

    // old() first, then what is stored, so a failed submit never loses input.
    $priceValues = collect($priceCurrencies)
        ->mapWithKeys(fn (string $code) => [
            $code => old($name.'.'.$code, data_get($values, $code)),
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

    @if ($priceCurrencies->count() === 1)
        @php $only = $priceCurrencies->first(); @endphp

        <div class="relative max-w-[220px]">
            {{-- The symbol sits in the field; the code sits beside it. Both,
                 because the symbol is what makes the field read as money and
                 the code is what says which money. --}}
            <span class="styledesk_input__prefix pointer-events-none text-sub" aria-hidden="true">
                {{ App\Support\Money::symbol($only) }}
            </span>

            <input type="text" inputmode="decimal"
                   name="{{ $name }}[{{ $only }}]"
                   value="{{ $priceValues[$only] }}"
                   class="sd-input styledesk_input--prefixed"
                   aria-label="{{ $label ? $label.' — '.$only : $only }}"
                   autocomplete="off">
        </div>

        <p class="mt-1.5 text-[12px] text-sub">
            {{ $only }} · {{ App\Support\Currencies::name($only) }}
        </p>

        @error($name.'.'.$only)<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror

        {{-- The deposit for this price, beside the price it belongs to.
             Hidden until it is switched on, so a service that takes no
             deposit is three fields shorter. --}}
        <div class="mt-2.5" data-deposit-row>
            <x-toggle :name="'deposit['.$only.'][required]'" :label="__('services.deposit_required')"
                      :checked="(bool) $depositFor($only, 'required', false)" data-deposit-toggle />

            <div class="mt-2.5 grid sm:grid-cols-2 gap-3 max-w-[420px]" data-deposit-fields
                 @unless ($depositFor($only, 'required', false)) hidden @endunless>
                <x-combo :name="'deposit['.$only.'][type]'" :label="__('services.deposit_type')"
                         :options="['fixed' => __('services.deposit_types.fixed'), 'percent' => __('services.deposit_types.percent')]"
                         :selected="$depositFor($only, 'type', 'percent')" />

                <div>
                    <label class="block text-[13px] font-medium text-ink mb-1.5">{{ __('services.deposit_value') }}</label>
                    <input type="text" inputmode="decimal" class="sd-input"
                           name="deposit[{{ $only }}][value]"
                           value="{{ $depositFor($only, 'value') }}"
                           aria-label="{{ __('services.deposit_value') }}" autocomplete="off">
                </div>
            </div>

            @error('deposit.'.$only.'.value')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
        </div>
    @else
        <div class="space-y-2">
            @foreach ($priceCurrencies as $code)
                <div class="flex items-center gap-3">
                    <span class="w-[52px] shrink-0 text-[13px] font-mono text-ink">{{ $code }}</span>

                    <div class="relative w-[180px]">
                        <span class="styledesk_input__prefix pointer-events-none text-sub" aria-hidden="true">
                            {{ App\Support\Money::symbol($code) }}
                        </span>

                        <input type="text" inputmode="decimal"
                               name="{{ $name }}[{{ $code }}]"
                               value="{{ $priceValues[$code] }}"
                               class="sd-input styledesk_input--prefixed"
                               aria-label="{{ $label ? $label.' — '.$code : $code }}"
                               autocomplete="off">
                    </div>

                    <span class="min-w-0 flex-1 text-[12px] text-sub truncate">
                        {{ App\Support\Currencies::name($code) }}
                    </span>
                </div>

                @error($name.'.'.$code)<p class="text-[12px] text-danger">{{ $message }}</p>@enderror

                {{-- Its own deposit, stored independently: switching one on
                     must leave every other price alone. --}}
                <div class="pl-[64px]" data-deposit-row>
                    <x-toggle :name="'deposit['.$code.'][required]'" :label="__('services.deposit_required')"
                              :checked="(bool) $depositFor($code, 'required', false)" data-deposit-toggle />

                    <div class="mt-2 grid sm:grid-cols-2 gap-3 max-w-[420px]" data-deposit-fields
                         @unless ($depositFor($code, 'required', false)) hidden @endunless>
                        <x-combo :name="'deposit['.$code.'][type]'" :label="__('services.deposit_type')"
                                 :options="['fixed' => __('services.deposit_types.fixed'), 'percent' => __('services.deposit_types.percent')]"
                                 :selected="$depositFor($code, 'type', 'percent')" />

                        <div>
                            <label class="block text-[13px] font-medium text-ink mb-1.5">{{ __('services.deposit_value') }}</label>
                            <input type="text" inputmode="decimal" class="sd-input"
                                   name="deposit[{{ $code }}][value]"
                                   value="{{ $depositFor($code, 'value') }}"
                                   aria-label="{{ __('services.deposit_value') }}" autocomplete="off">
                        </div>
                    </div>

                    @error('deposit.'.$code.'.value')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>

        {{-- Said on the field itself, not only in settings. Someone typing
             three numbers is exactly the person who might assume the other two
             will fill themselves in. --}}
        <p class="mt-2 text-[12px] text-sub">{{ __('currency.no_conversion') }}</p>
    @endif

    @if ($hint)
        <p class="mt-1.5 text-[12px] text-sub">{{ $hint }}</p>
    @endif
</div>
