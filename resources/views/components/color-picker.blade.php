{{--
    The colour picker: a row of swatches, and one empty card that opens the
    browser's own picker.

    One component rather than one per module. A service block and the room it
    occupies appear on the same calendar, so they are chosen from one palette
    and drawn from one file — two copies would eventually differ in a way only
    a reader could see.

    The empty card is the escape hatch rather than a button beside the row: a
    business that wants its own colour reaches for the row of colours, and an
    "Add colour" control next to eight swatches is a ninth thing to read
    before choosing one of the eight.

    Written out rather than shown as markup: Blade compiles component tags
    before stripping comments, so an x-* tag inside a comment is compiled too.
--}}
@props([
    'name' => 'color',
    'label' => null,
    'hint' => null,
    'value' => null,
    /** Overridable, but no caller should need to: the palette is shared. */
    'colors' => null,
])

@php
    $swatches = $colors ?? config('colors.palette');

    // old() first, so a submission the server refused comes back with the
    // colour that was chosen rather than with the first in the row.
    $chosen = old($name, $value ?? $swatches[0]);

    /* A colour the business picked itself rather than one of ours. The last
       card wears it, so reopening the form finds the record the colour it was
       actually saved with rather than snapping back to the palette. */
    $isCustom = $chosen && ! in_array($chosen, $swatches, true);
@endphp

<div {{ $attributes }} data-color-picker>
    @if ($label)
        <span class="block text-[13px] font-medium text-ink mb-1.5">{{ $label }}</span>
    @endif

    <div class="flex flex-wrap gap-1.5">
        @foreach ($swatches as $color)
            <label class="styledesk_swatchpick">
                <input type="radio" name="{{ $name }}" value="{{ $color }}" @checked($chosen === $color)>
                <span class="styledesk_swatchpick__dot" style="--service-color: {{ $color }}" aria-hidden="true"></span>
                <span class="sr-only">{{ $color }}</span>
            </label>
        @endforeach

        <label class="styledesk_swatchpick styledesk_swatchpick--custom" data-custom-color
               title="{{ __('common.custom_color') }}">
            <input type="radio" name="{{ $name }}" data-custom-radio
                   value="{{ $isCustom ? $chosen : '' }}" @checked($isCustom)>

            <span class="styledesk_swatchpick__dot {{ $isCustom ? '' : 'is-empty' }}"
                  data-custom-dot
                  style="--service-color: {{ $isCustom ? $chosen : 'transparent' }}" aria-hidden="true"></span>

            {{-- The native picker, opened by the card rather than shown beside
                 it. It carries no name: the radio above is what the form
                 posts, so the two cannot disagree about the chosen colour. --}}
            <input type="color" class="sr-only" data-custom-input
                   value="{{ $isCustom ? $chosen : $swatches[0] }}"
                   aria-label="{{ __('common.custom_color') }}">

            <span class="sr-only">{{ __('common.custom_color') }}</span>
        </label>
    </div>

    @if ($hint)
        <p class="mt-1.5 text-[12px] text-sub">{{ $hint }}</p>
    @endif

    @error($name)<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
</div>
