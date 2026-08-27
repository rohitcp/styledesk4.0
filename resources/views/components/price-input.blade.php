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
])

@php
    $priceCurrencies = App\Support\Currencies::enabledFor(auth()->user()?->tenant);

    // old() first, then what is stored, so a failed submit never loses input.
    $priceValues = collect($priceCurrencies)
        ->mapWithKeys(fn (string $code) => [
            $code => old($name.'.'.$code, data_get($values, $code)),
        ]);
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
