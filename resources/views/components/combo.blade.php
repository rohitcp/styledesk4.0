{{--
    A searchable single-select, matching every other dropdown in the app.

    Wraps the MultiSelect island so a form does not have to build its props by
    hand. That matters beyond tidiness: the json directive cannot take a call with nested
    parentheses — Blade's parser counts brackets rather than reading PHP — so
    every inline attempt has to assemble the array in a php block first, and one of
    them will eventually forget.

    Usage: an x-combo tag with name, label, options and selected.

    Two things must never appear inside a Blade comment, both learned here:
    an x-* component tag, and a directive written with its @ sign. Blade
    compiles component tags and directives before it strips comments, so both
    are still executed — this file broke itself twice, once documenting its
    own syntax and once naming the directive it exists to avoid.
--}}
@props([
    'name',
    'label' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => 'Choose an option',
    'required' => false,
    'hint' => null,
])

@php
    // Option keys arrive as ints from a pluck and as strings from config; a
    // JSON object key is always a string, and MultiSelect compares with
    // includes(), so both sides are normalised here rather than at each call.
    $comboOptions = collect($options)->mapWithKeys(fn ($label, $key) => [(string) $key => $label])->all();

    $comboProps = [
        'options' => $comboOptions,
        'modelValue' => ($selected === null || $selected === '') ? [] : [(string) $selected],
        'name' => $name,
        'single' => true,
        'placeholder' => $placeholder,
        'ariaLabel' => $label ?? $placeholder,
    ];
@endphp

<div {{ $attributes }}>
    @if ($label)
        <span class="block text-[13px] font-medium text-ink mb-1.5">
            {{ $label }}@if ($required) <span class="text-danger">*</span>@endif
        </span>
    @endif

    <div data-vue-component="MultiSelect" data-props='@json($comboProps)'></div>

    @if ($hint)
        <p class="mt-1.5 text-[12px] text-sub">{{ $hint }}</p>
    @endif

    @error($name)<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
</div>
