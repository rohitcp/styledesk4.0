{{--
    One checkbox or radio option, as a card.

    The label wraps the input, so the whole card is clickable without a line
    of JavaScript, and the control stays a real checkbox for the keyboard, for
    a screen reader, and for the form that posts it.

    `hint` is for the sentence under the name — what choosing this actually
    does. Anything richer goes in the slot, which replaces the label entirely.
--}}
@props([
    'name',
    'value' => '1',
    'label' => null,
    'hint' => null,
    'type' => 'checkbox',
    'checked' => false,
    'disabled' => false,
])

<label {{ $attributes->class('styledesk_choice') }}>
    <input type="{{ $type }}" name="{{ $name }}" value="{{ $value }}"
           class="{{ $type === 'radio' ? 'sd-radio' : 'sd-check' }}"
           @checked($checked) @disabled($disabled)>

    <span class="styledesk_choice__label">
        {{ $slot->isEmpty() ? $label : $slot }}
        @if ($hint)<span class="styledesk_choice__hint">{{ $hint }}</span>@endif
    </span>
</label>
