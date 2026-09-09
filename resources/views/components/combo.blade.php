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
    /**
     * More than one value, posted as name[] and shown as chips.
     *
     * The same island either way: the search, the panel, the outside-click
     * handling and the keyboard behaviour are identical, and only the
     * selection rule differs.
     */
    'multiple' => false,
    /**
     * Filter mode: the control reports "Location (3)" and the chosen values
     * are listed as chips below the toolbar. Without it a multiple combo
     * keeps its chips inside itself, which is what the settings screens want.
     */
    'summary' => null,
    /**
     * The accessible name, when the visible label is drawn by the caller.
     *
     * A field on a row of three wants the same small label as its neighbours,
     * so the caller sometimes writes its own — and the control still has to
     * announce itself as something other than its placeholder.
     */
    'ariaLabel' => null,
    /**
     * Live-validation rules for the chosen value. Carried into MultiSelect,
     * which puts them on the hidden input holding the answer — the control
     * itself is a button, and a button has no value to check.
     */
    'rules' => null,
])

@php
    // Option keys arrive as ints from a pluck and as strings from config; a
    // JSON object key is always a string, and MultiSelect compares with
    // includes(), so both sides are normalised here rather than at each call.
    $comboOptions = collect($options)->mapWithKeys(fn ($label, $key) => [(string) $key => $label])->all();

    /**
     * Whatever the caller passed, normalised to a list of strings: a single
     * value, an array of them, or nothing at all.
     */
    $comboSelected = collect($multiple ? (array) $selected : [$selected])
        ->filter(fn ($value) => $value !== null && $value !== '')
        ->map(fn ($value) => (string) $value)
        ->values()
        ->all();

    $comboProps = [
        'options' => $comboOptions,
        'modelValue' => $comboSelected,
        'name' => $name,
        'single' => ! $multiple,
        // No primary on a filter: the first location chosen does not outrank
        // the second, and badging it Primary would claim it does.
        'showPrimary' => false,
        'summaryLabel' => $summary ?? '',
        'placeholder' => $placeholder,
        'ariaLabel' => $ariaLabel ?? $label ?? $placeholder,
        /* Only meaningful on a single-value combo: a multiple posts name[]
           and has no one input to hang a rule on. */
        'rules' => $multiple ? '' : (string) $rules,
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

    {{-- Addressed to the hidden input, which is where the value is and so
         where SD.setError looks. --}}
    <p data-error-for="{{ $rules ? $name.'-value' : $name }}" role="alert"
       class="mt-1.5 text-[12px] text-danger"
       @unless ($errors->has($name)) hidden @endunless>{{ $errors->first($name) }}</p>
</div>
